<?php

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('a role mismatch renders the designed 403 page', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($entrepreneur)
        ->get('/admin/dashboard')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shared/Error')
            ->where('status', 403));
});

test('an unknown url renders the designed 404 page', function () {
    $this->get('/definitely/not/a/page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shared/Error')
            ->where('status', 404));
});

test('a server error renders the designed 500 page when debug is off', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('boom'));

    $this->withExceptionHandling()
        ->get('/_test/boom')
        ->assertServerError()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shared/Error')
            ->where('status', 500));
});

test('a server error keeps the framework debug page when debug is on', function () {
    config(['app.debug' => true]);
    Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('boom'));

    $response = $this->withExceptionHandling()->get('/_test/boom');

    $response->assertServerError();
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

test('json requests are not intercepted by the error page', function () {
    $this->getJson('/definitely/not/a/page')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('a dead invitation link renders the designed 410 page', function () {
    $token = Str::random(64);
    UserInvitation::factory()->expired()->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $this->get("/invitations/accept/{$token}")
        ->assertGone()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shared/Error')
            ->where('status', 410));
});

/**
 * The invitee is unauthenticated and holds only a token, so the page must not
 * confirm whether an account exists at the invited address. Every dead state
 * has to be indistinguishable from the others.
 */
test('a dead invitation link reveals neither the invited email nor which state it is in', function (string $state) {
    $token = Str::random(64);
    UserInvitation::factory()->{$state}()->create([
        'email' => 'invitee@example.com',
        'token_hash' => hash('sha256', $token),
    ]);

    $this->get("/invitations/accept/{$token}")
        ->assertGone()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shared/Error')
            ->where('status', 410)
            ->missing('invitation'))
        ->assertDontSee('invitee@example.com')
        ->assertDontSee($state);
})->with(['expired', 'revoked', 'accepted']);

/**
 * Statuses this application can abort() with, read out of the source itself.
 * Tokenized rather than grepped so a number inside a string, a comment, or a
 * condition like `abort_if($code === 404, 403)` cannot be mistaken for one.
 *
 * @return list<int>
 */
function abortStatusesInSource(): array
{
    $statusArgument = ['abort' => 0, 'abort_if' => 1, 'abort_unless' => 1];
    $statuses = [];

    foreach (['app', 'routes'] as $directory) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($directory)),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $tokens = array_values(array_filter(
                token_get_all(file_get_contents($file->getPathname())),
                fn ($token) => ! is_array($token) || ! in_array(
                    $token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true,
                ),
            ));

            foreach ($tokens as $index => $token) {
                if (! is_array($token) || $token[0] !== T_STRING) {
                    continue;
                }

                $callee = $token[1];

                if (! isset($statusArgument[$callee]) || ($tokens[$index + 1] ?? null) !== '(') {
                    continue;
                }

                // Skip methods and declarations that merely share the name.
                $previous = $tokens[$index - 1] ?? null;
                if (in_array($previous, ['->', '::', '?->'], true)
                    || (is_array($previous) && $previous[0] === T_FUNCTION)) {
                    continue;
                }

                $arguments = [[]];
                $depth = 0;

                for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                    $current = $tokens[$cursor];

                    if (in_array($current, ['(', '[', '{'], true)) {
                        $depth++;

                        if ($depth === 1) {
                            continue;
                        }
                    } elseif (in_array($current, [')', ']', '}'], true)) {
                        if (--$depth === 0) {
                            break;
                        }
                    } elseif ($current === ',' && $depth === 1) {
                        $arguments[] = [];

                        continue;
                    }

                    $arguments[count($arguments) - 1][] = $current;
                }

                $argument = $arguments[$statusArgument[$callee]] ?? [];

                if (count($argument) === 1 && is_array($argument[0]) && $argument[0][0] === T_LNUMBER) {
                    $statuses[] = (int) $argument[0][1];
                }
            }
        }
    }

    return array_values(array_unique(array_filter(
        $statuses,
        fn (int $status) => $status >= 400 && $status <= 599,
    )));
}

/**
 * A status the handler does not recognise falls through to Symfony's unbranded
 * page — which is how a real invitee once met a bare "410 Gone". Walking every
 * abort() in the source means a newly introduced status has to be designed for,
 * or explicitly excused here, before it can ever reach a user.
 */
test('every status the app aborts with has a designed error page', function () {
    // 422 is Inertia's validation channel: the form redraws in place with its
    // field errors, so a full-page takeover would break booking and reschedule.
    $handledByInertia = [422];

    $statuses = array_diff(abortStatusesInSource(), $handledByInertia);

    expect($statuses)->not->toBeEmpty()
        ->and($statuses)->toContain(410);

    foreach ($statuses as $status) {
        Route::middleware('web')->get("/_test/abort/{$status}", fn () => abort($status));

        $this->withExceptionHandling()
            ->get("/_test/abort/{$status}")
            ->assertStatus($status)
            ->assertInertia(fn (Assert $page) => $page
                ->component('shared/Error')
                ->where('status', $status));
    }
});

/**
 * The page falls back to its 404 entry for an unknown status, so a status the
 * handler renders but the page has no copy for shows the wrong words under the
 * right code — a silent failure the status assertions above cannot see.
 */
test('the error page carries its own copy for every status it is handed', function () {
    $source = file_get_contents(resource_path('js/pages/shared/Error.svelte'));

    // 500 never reaches an abort(): the handler renders it for uncaught throwables.
    $rendered = [...array_diff(abortStatusesInSource(), [422]), 500];

    foreach ($rendered as $status) {
        expect($source)->toMatch("/\b{$status}:\s*\{/");
    }
});
