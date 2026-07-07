# Mentor ⇄ Entrepreneur Chat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship real-time 1:1 messaging between paired mentors and entrepreneurs in a LinkedIn-style two-pane UI, with an in-thread "Schedule a call" CTA into the existing booking flow.

**Architecture:** A `Conversation` (one per `Pairing`) owns `Message`s and two `ConversationParticipant` rows. Writes persist over HTTP, then a queued `ShouldBroadcast` event fans out over Laravel Reverb on a private per-conversation channel plus a per-user channel (for the global unread badge). Inertia renders the page shell + first page of messages; Laravel Echo delivers live message/read/typing/presence events; a JSON endpoint serves cursor pagination.

**Tech Stack:** Laravel 12 (PHP 8.4), Pest, Inertia v3, Svelte 5 (runes), Tailwind v4, Laravel Reverb, `laravel-echo`, Redis (queue + Reverb scaling).

## Global Constraints

- **Never add Claude/AI attribution** to commits (no `Co-Authored-By`, no "Generated with").
- **Dates are `CarbonImmutable`** — never mutate in place; use returned values / `->copy()`.
- **App weekday convention:** 0 = Monday … 6 = Sunday (not needed here, but repo-wide).
- **Page width:** page content caps at `max-w-7xl` centered (`mx-auto w-full max-w-7xl px-6`); the two-pane messaging surface fills that width.
- **Theme:** dark sage design tokens (`text-ink`, `text-muted`, `text-faint`, `bg-surface`, `bg-elevated`, `bg-panel`, `border-line`, `bg-accent`, `text-on-accent`, `bg-accent-soft`, `text-accent`). Do **not** use raw shadcn tokens (`bg-popover`, `border-input`, …); they don't exist here.
- **Tests are Pest** (`test('…', function () { … })`) with `RefreshDatabase`; reuse existing helpers `completeEntrepreneur()`, `availableMentor()` (defined in `tests/Pest.php`/feature helpers).
- **Run backend tests:** `php artisan test --filter=<Name>`. **Type-check frontend:** `npm run types:check`.
- All new PHP passes `vendor/bin/pint --dirty`.

## Shared contracts (used across tasks — keep names exact)

- **Routes / names**
  - `GET  /{role}/messages` → `messages.index` (Inertia) · `{role}` ∈ `entrepreneur|mentor`
  - `GET  /{role}/messages/{conversation}` → `messages.show` (Inertia)
  - `GET  /conversations/{conversation}/messages` → `conversations.messages.index` (JSON, `?before=<id>`)
  - `POST /conversations/{conversation}/messages` → `conversations.messages.store` (JSON)
  - `POST /conversations/{conversation}/read` → `conversations.read` (JSON)
- **Broadcast channels:** `conversation.{id}` (private), `user.{id}` (private), `online` (presence).
- **Broadcast event names:** `message.sent`, `message.read`.
- **Message JSON shape** (`MessageResource`): `{ id, conversation_id, sender_id, type, body, created_at }` (`created_at` ISO-8601).
- **Conversation summary shape** (list item): `{ id, other: { id, name, initials, role }, last_message_preview, last_message_at, unread_count, is_active }`.

---

## Task 1: Schema, enum, and Eloquent models

**Files:**
- Create: `database/migrations/2026_07_07_000001_create_conversations_table.php`
- Create: `database/migrations/2026_07_07_000002_create_conversation_participants_table.php`
- Create: `database/migrations/2026_07_07_000003_create_messages_table.php`
- Create: `app/Enums/MessageType.php`
- Create: `app/Models/Conversation.php`, `app/Models/ConversationParticipant.php`, `app/Models/Message.php`
- Create: `database/factories/ConversationFactory.php`, `database/factories/MessageFactory.php`
- Test: `tests/Feature/Chat/ChatModelsTest.php`

**Interfaces:**
- Produces: `Conversation` (`pairing()`, `messages()`, `participants()`, `participantFor(User): ?ConversationParticipant`, `otherParticipant(User): ?ConversationParticipant`, `isActive(): bool`, `unreadCountFor(User): int`); `Message` (`conversation()`, `sender()`, casts `type`→`MessageType`); `ConversationParticipant` (`conversation()`, `user()`); enum `MessageType::{Text,System}`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Chat/ChatModelsTest.php

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Pairing;

test('a conversation has messages, participants, and a pairing', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);
    $conversation = Conversation::create(['pairing_id' => $pairing->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $entrepreneur->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $mentor->id]);
    $conversation->messages()->create([
        'sender_user_id' => $mentor->id, 'type' => MessageType::Text, 'body' => 'Hello there',
    ]);

    expect($conversation->pairing->is($pairing))->toBeTrue()
        ->and($conversation->messages)->toHaveCount(1)
        ->and($conversation->messages->first()->type)->toBe(MessageType::Text)
        ->and($conversation->participants)->toHaveCount(2)
        ->and($conversation->participantFor($mentor)->user_id)->toBe($mentor->id)
        ->and($conversation->otherParticipant($mentor)->user_id)->toBe($entrepreneur->id)
        ->and($conversation->isActive())->toBeTrue()
        ->and($conversation->unreadCountFor($entrepreneur))->toBe(1)
        ->and($conversation->unreadCountFor($mentor))->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChatModelsTest`
Expected: FAIL — `Class "App\Models\Conversation" not found`.

- [ ] **Step 3: Create the migrations**

```php
<?php // database/migrations/2026_07_07_000001_create_conversations_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pairing_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->string('last_message_preview', 180)->nullable();
            $table->foreignId('last_message_sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('conversations'); }
};
```

```php
<?php // database/migrations/2026_07_07_000002_create_conversation_participants_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });
    }
    public function down(): void { Schema::dropIfExists('conversation_participants'); }
};
```

```php
<?php // database/migrations/2026_07_07_000003_create_messages_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 16)->default('text');
            $table->text('body');
            $table->timestamp('created_at')->nullable();
            $table->index(['conversation_id', 'id']);
        });
    }
    public function down(): void { Schema::dropIfExists('messages'); }
};
```

- [ ] **Step 4: Create the enum and models**

```php
<?php // app/Enums/MessageType.php
namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case System = 'system';
}
```

```php
<?php // app/Models/Conversation.php
namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected $fillable = ['pairing_id', 'last_message_at', 'last_message_preview', 'last_message_sender_id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function pairing(): BelongsTo { return $this->belongsTo(Pairing::class); }
    public function messages(): HasMany { return $this->hasMany(Message::class); }
    public function participants(): HasMany { return $this->hasMany(ConversationParticipant::class); }

    public function participantFor(User $user): ?ConversationParticipant
    {
        return $this->participants()->where('user_id', $user->id)->first();
    }

    public function otherParticipant(User $user): ?ConversationParticipant
    {
        return $this->participants()->where('user_id', '!=', $user->id)->first();
    }

    public function isActive(): bool
    {
        return $this->pairing->ended_at === null;
    }

    public function unreadCountFor(User $user): int
    {
        $participant = $this->participantFor($user);

        return $this->messages()
            ->where('sender_user_id', '!=', $user->id)
            ->when($participant?->last_read_message_id, fn ($q, $id) => $q->where('id', '>', $id))
            ->count();
    }
}
```

```php
<?php // app/Models/ConversationParticipant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'last_read_at', 'last_read_message_id'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
```

```php
<?php // app/Models/Message.php
namespace App\Models;

use App\Enums\MessageType;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'sender_user_id', 'type', 'body'];

    protected function casts(): array
    {
        return ['type' => MessageType::class, 'created_at' => 'datetime'];
    }

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_user_id'); }
}
```

- [ ] **Step 5: Create the factories**

```php
<?php // database/factories/ConversationFactory.php
namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Pairing;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return ['pairing_id' => Pairing::factory()];
    }
}
```

```php
<?php // database/factories/MessageFactory.php
namespace Database\Factories;

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Message> */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_user_id' => null,
            'type' => MessageType::Text,
            'body' => fake()->sentence(),
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ChatModelsTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Enums/MessageType.php app/Models/Conversation.php app/Models/ConversationParticipant.php app/Models/Message.php database/migrations/2026_07_07_00000*_*.php database/factories/ConversationFactory.php database/factories/MessageFactory.php tests/Feature/Chat/ChatModelsTest.php
git commit -m "Add chat schema and models (conversations, participants, messages)"
```

---

## Task 2: Auto-provision a conversation per pairing (+ backfill)

**Files:**
- Create: `app/Actions/Chat/ProvisionConversation.php`
- Create: `app/Observers/PairingObserver.php`
- Modify: `app/Models/Pairing.php` (add `#[ObservedBy(PairingObserver::class)]` + `conversation()` relation)
- Create: `app/Console/Commands/BackfillConversations.php`
- Test: `tests/Feature/Chat/ProvisionConversationTest.php`

**Interfaces:**
- Consumes: `Conversation`, `ConversationParticipant`, `Pairing` (Task 1).
- Produces: `ProvisionConversation::handle(Pairing): Conversation` (idempotent); `Pairing::conversation(): HasOne`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Chat/ProvisionConversationTest.php

use App\Console\Commands\BackfillConversations;
use App\Models\Conversation;
use App\Models\Pairing;
use Illuminate\Support\Facades\DB;

test('creating a pairing provisions a conversation with both participants', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();

    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);

    $conversation = $pairing->conversation()->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->participants()->pluck('user_id')->sort()->values()->all())
        ->toEqual(collect([$entrepreneur->id, $mentor->id])->sort()->values()->all());
});

test('backfill provisions conversations for pairings that lack one', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);
    // Simulate a legacy pairing with no conversation.
    Conversation::where('pairing_id', $pairing->id)->delete();
    expect($pairing->conversation()->exists())->toBeFalse();

    $this->artisan(BackfillConversations::class)->assertOk();

    expect($pairing->fresh()->conversation()->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProvisionConversationTest`
Expected: FAIL — `Call to undefined method App\Models\Pairing::conversation()`.

- [ ] **Step 3: Write the action**

```php
<?php // app/Actions/Chat/ProvisionConversation.php
namespace App\Actions\Chat;

use App\Models\Conversation;
use App\Models\Pairing;
use Illuminate\Support\Facades\DB;

class ProvisionConversation
{
    public function handle(Pairing $pairing): Conversation
    {
        return DB::transaction(function () use ($pairing) {
            $conversation = Conversation::firstOrCreate(['pairing_id' => $pairing->id]);

            foreach ([$pairing->entrepreneur_user_id, $pairing->mentor_user_id] as $userId) {
                $conversation->participants()->firstOrCreate(['user_id' => $userId]);
            }

            return $conversation;
        });
    }
}
```

- [ ] **Step 4: Write the observer and wire the model**

```php
<?php // app/Observers/PairingObserver.php
namespace App\Observers;

use App\Actions\Chat\ProvisionConversation;
use App\Models\Pairing;

class PairingObserver
{
    public function __construct(private ProvisionConversation $provision) {}

    public function created(Pairing $pairing): void
    {
        $this->provision->handle($pairing);
    }
}
```

In `app/Models/Pairing.php`, add the imports and attribute + relation:

```php
use App\Observers\PairingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(PairingObserver::class)]
class Pairing extends Model
{
    // ...existing code...

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }
}
```

- [ ] **Step 5: Write the backfill command**

```php
<?php // app/Console/Commands/BackfillConversations.php
namespace App\Console\Commands;

use App\Actions\Chat\ProvisionConversation;
use App\Models\Pairing;
use Illuminate\Console\Command;

class BackfillConversations extends Command
{
    protected $signature = 'chat:backfill-conversations';
    protected $description = 'Provision a conversation for every pairing that lacks one';

    public function handle(ProvisionConversation $provision): int
    {
        Pairing::query()->whereDoesntHave('conversation')->chunkById(200, function ($pairings) use ($provision) {
            foreach ($pairings as $pairing) {
                $provision->handle($pairing);
                $this->line("Provisioned conversation for pairing #{$pairing->id}");
            }
        });

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ProvisionConversationTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Actions/Chat/ProvisionConversation.php app/Observers/PairingObserver.php app/Models/Pairing.php app/Console/Commands/BackfillConversations.php tests/Feature/Chat/ProvisionConversationTest.php
git commit -m "Provision a conversation per pairing with backfill command"
```

---

## Task 3: Install & configure Reverb + Echo

**Files:**
- Modify: `composer.json` (adds `laravel/reverb`), `package.json` (adds `laravel-echo`, `pusher-js`)
- Create: `config/reverb.php`, `routes/channels.php` (scaffolded by the installer)
- Modify: `.env`, `.env.example` (broadcast + reverb + queue keys)
- Create: `resources/js/echo.ts`
- Modify: `resources/js/app.ts` (import echo bootstrap)

**Interfaces:**
- Produces: a configured `echo` singleton (`import { echo } from '@/echo'`) exposing `echo.private(name)`, `echo.join(name)`, `echo.leave(name)`; `BROADCAST_CONNECTION=reverb`.

> This task is infrastructure; it is verified by a connection check rather than a unit test.

- [ ] **Step 1: Install Reverb + broadcasting scaffolding**

Run:
```bash
composer require laravel/reverb
php artisan install:broadcasting --reverb
```
Expected: creates `routes/channels.php` and `config/reverb.php`, adds Reverb env keys, installs `laravel-echo` + `pusher-js`, and writes an Echo bootstrap. Accept the prompt to install Node deps.

- [ ] **Step 2: Set env keys**

In `.env` and `.env.example` ensure:
```dotenv
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis

REVERB_APP_ID=tolfund
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SCALING_ENABLED=true

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- [ ] **Step 3: Replace the generated Echo bootstrap with a typed singleton**

```ts
// resources/js/echo.ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}
window.Pusher = Pusher;

export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

Add to the top of `resources/js/app.ts`:
```ts
import '@/echo';
```

- [ ] **Step 4: Verify broadcasting is wired**

Run:
```bash
php artisan test --filter=ChatModelsTest   # still green after config changes
php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo config('broadcasting.default');"
```
Expected: prints `reverb`.

Start the server locally to smoke-test the socket (manual): `php artisan reverb:start` then `npm run dev` and confirm the browser console shows a Reverb connection with no errors.

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock package.json package-lock.json config/reverb.php routes/channels.php resources/js/echo.ts resources/js/app.ts .env.example
git commit -m "Install and configure Laravel Reverb broadcasting with Echo"
```

---

## Task 4: Channel authorization

**Files:**
- Modify: `routes/channels.php`
- Test: `tests/Feature/Chat/ChannelAuthorizationTest.php`

**Interfaces:**
- Consumes: `Conversation`, `ConversationParticipant` (Task 1).
- Produces: authorized channels `conversation.{conversationId}`, `user.{userId}`, presence `online`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Chat/ChannelAuthorizationTest.php

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Pairing;

function conversationFor($entrepreneur, $mentor): Conversation
{
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);

    return $pairing->conversation()->firstOrFail();
}

test('a participant can authorize the conversation channel', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = conversationFor($entrepreneur, $mentor);

    $this->actingAs($mentor)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$conversation->id}",
        ])->assertOk();
});

test('a non-participant is rejected from the conversation channel', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $intruder = availableMentor();
    $conversation = conversationFor($entrepreneur, $mentor);

    $this->actingAs($intruder)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$conversation->id}",
        ])->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChannelAuthorizationTest`
Expected: FAIL — the conversation channel is undefined, so auth is forbidden for the participant too (first test fails).

- [ ] **Step 3: Define the channels**

Append to `routes/channels.php`:
```php
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    return Conversation::where('id', $conversationId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('online', function (User $user) {
    return ['id' => $user->id, 'name' => $user->name];
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ChannelAuthorizationTest`
Expected: PASS (participant authorized, intruder forbidden).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add routes/channels.php tests/Feature/Chat/ChannelAuthorizationTest.php
git commit -m "Authorize conversation, user, and presence broadcast channels"
```

---

## Task 5: Send a message (endpoint + queued broadcast)

**Files:**
- Create: `app/Http/Requests/Chat/StoreMessageRequest.php`
- Create: `app/Http/Resources/MessageResource.php`
- Create: `app/Actions/Chat/PostMessage.php`
- Create: `app/Events/MessageSent.php`
- Create: `app/Policies/ConversationPolicy.php`
- Create: `app/Http/Controllers/Chat/SendMessageController.php`
- Modify: `routes/web.php` (add `conversations.messages.store`)
- Test: `tests/Feature/Chat/SendMessageTest.php`

**Interfaces:**
- Consumes: `Conversation`, `Message`, `MessageType` (Task 1).
- Produces: `PostMessage::handle(Conversation, User $sender, string $body): Message`; event `MessageSent` (channels `conversation.{id}` + `user.{recipientId}`, name `message.sent`); `MessageResource` (shape in Shared Contracts); policy abilities `view`, `sendMessage`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Chat/SendMessageTest.php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Pairing;
use Illuminate\Support\Facades\Event;

function activeConversation(): array
{
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);

    return [$entrepreneur, $mentor, $pairing->conversation()->firstOrFail()];
}

test('a participant can send a message and it broadcasts', function () {
    Event::fake([MessageSent::class]);
    [$entrepreneur, $mentor, $conversation] = activeConversation();

    $this->actingAs($entrepreneur)
        ->postJson("/conversations/{$conversation->id}/messages", ['body' => 'Hi mentor'])
        ->assertCreated()
        ->assertJsonPath('message.body', 'Hi mentor')
        ->assertJsonPath('message.sender_id', $entrepreneur->id);

    $conversation->refresh();
    expect($conversation->messages()->count())->toBe(1)
        ->and($conversation->last_message_preview)->toBe('Hi mentor')
        ->and($conversation->last_message_sender_id)->toBe($entrepreneur->id);

    Event::assertDispatched(MessageSent::class, fn ($e) => $e->recipientId === $mentor->id);
});

test('a non-participant cannot send a message', function () {
    [$entrepreneur, $mentor, $conversation] = activeConversation();
    $intruder = availableMentor();

    $this->actingAs($intruder)
        ->postJson("/conversations/{$conversation->id}/messages", ['body' => 'Sneaky'])
        ->assertForbidden();
});

test('a message cannot be sent on an ended pairing', function () {
    [$entrepreneur, $mentor, $conversation] = activeConversation();
    $conversation->pairing->update(['ended_at' => now()]);

    $this->actingAs($entrepreneur)
        ->postJson("/conversations/{$conversation->id}/messages", ['body' => 'Still there?'])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SendMessageTest`
Expected: FAIL — route `conversations/{id}/messages` not defined (404/405).

- [ ] **Step 3: Write request, resource, policy**

```php
<?php // app/Http/Requests/Chat/StoreMessageRequest.php
namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }
}
```

```php
<?php // app/Http/Resources/MessageResource.php
namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_user_id,
            'type' => $this->type->value,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

```php
<?php // app/Policies/ConversationPolicy.php
namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()->where('user_id', $user->id)->exists();
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation) && $conversation->isActive();
    }
}
```

- [ ] **Step 4: Write the broadcast event**

```php
<?php // app/Events/MessageSent.php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public int $recipientId,
        public int $recipientUnreadCount,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->message->conversation_id}"),
            new PrivateChannel("user.{$this->recipientId}"),
        ];
    }

    public function broadcastAs(): string { return 'message.sent'; }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_user_id,
                'type' => $this->message->type->value,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at->toIso8601String(),
            ],
            'conversation' => [
                'id' => $this->message->conversation_id,
                'last_message_preview' => Str::limit($this->message->body, 120),
                'last_message_at' => $this->message->created_at->toIso8601String(),
            ],
            'recipient_unread_count' => $this->recipientUnreadCount,
        ];
    }
}
```

- [ ] **Step 5: Write the action and controller, register the route**

```php
<?php // app/Actions/Chat/PostMessage.php
namespace App\Actions\Chat;

use App\Enums\MessageType;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostMessage
{
    public function handle(Conversation $conversation, User $sender, string $body): Message
    {
        $message = DB::transaction(function () use ($conversation, $sender, $body) {
            $message = $conversation->messages()->create([
                'sender_user_id' => $sender->id,
                'type' => MessageType::Text,
                'body' => $body,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_preview' => Str::limit($body, 180),
                'last_message_sender_id' => $sender->id,
            ])->save();

            return $message;
        });

        $recipient = $conversation->otherParticipant($sender);
        $recipientUnread = $conversation->messages()
            ->where('sender_user_id', '!=', $recipient->user_id)
            ->when($recipient->last_read_message_id, fn ($q, $id) => $q->where('id', '>', $id))
            ->count();

        MessageSent::dispatch($message, $recipient->user_id, $recipientUnread);

        return $message;
    }
}
```

```php
<?php // app/Http/Controllers/Chat/SendMessageController.php
namespace App\Http\Controllers\Chat;

use App\Actions\Chat\PostMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class SendMessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation, PostMessage $action): JsonResponse
    {
        $this->authorize('sendMessage', $conversation);

        $message = $action->handle($conversation, $request->user(), $request->string('body')->trim()->toString());

        return MessageResource::make($message)->response()->setStatusCode(201);
    }
}
```

Add inside the `Route::middleware('auth')->group(...)` block in `routes/web.php`:
```php
use App\Http\Controllers\Chat\SendMessageController;

Route::post('/conversations/{conversation}/messages', [SendMessageController::class, 'store'])
    ->name('conversations.messages.store');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SendMessageTest`
Expected: PASS (send works, non-participant + ended-pairing forbidden, event dispatched to the mentor).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/Chat/StoreMessageRequest.php app/Http/Resources/MessageResource.php app/Actions/Chat/PostMessage.php app/Events/MessageSent.php app/Policies/ConversationPolicy.php app/Http/Controllers/Chat/SendMessageController.php routes/web.php tests/Feature/Chat/SendMessageTest.php
git commit -m "Send chat messages with queued broadcast and policy checks"
```

---

## Task 6: Read receipts (endpoint + broadcast)

**Files:**
- Create: `app/Events/MessageRead.php`
- Create: `app/Http/Controllers/Chat/MarkReadController.php`
- Modify: `routes/web.php` (add `conversations.read`)
- Test: `tests/Feature/Chat/MarkReadTest.php`

**Interfaces:**
- Consumes: `Conversation`, `ConversationParticipant` (Task 1), policy `view` (Task 5).
- Produces: event `MessageRead` (channel `conversation.{id}`, name `message.read`, payload `{ reader_id, last_read_message_id }`).

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Chat/MarkReadTest.php

use App\Events\MessageRead;
use App\Models\Pairing;
use Illuminate\Support\Facades\Event;

test('marking a conversation read updates the participant and broadcasts', function () {
    Event::fake([MessageRead::class]);
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ])->conversation()->firstOrFail();
    $message = $conversation->messages()->create([
        'sender_user_id' => $mentor->id, 'type' => 'text', 'body' => 'Hello',
    ]);

    $this->actingAs($entrepreneur)
        ->postJson("/conversations/{$conversation->id}/read")
        ->assertOk();

    $participant = $conversation->participantFor($entrepreneur);
    expect($participant->last_read_message_id)->toBe($message->id)
        ->and($participant->last_read_at)->not->toBeNull()
        ->and($conversation->unreadCountFor($entrepreneur))->toBe(0);

    Event::assertDispatched(MessageRead::class, fn ($e) => $e->readerId === $entrepreneur->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MarkReadTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the event**

```php
<?php // app/Events/MessageRead.php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $readerId,
        public ?int $lastReadMessageId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string { return 'message.read'; }

    public function broadcastWith(): array
    {
        return ['reader_id' => $this->readerId, 'last_read_message_id' => $this->lastReadMessageId];
    }
}
```

- [ ] **Step 4: Write the controller and route**

```php
<?php // app/Http/Controllers/Chat/MarkReadController.php
namespace App\Http\Controllers\Chat;

use App\Events\MessageRead;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkReadController extends Controller
{
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $user = $request->user();
        $latestId = $conversation->messages()->max('id');

        $conversation->participants()->where('user_id', $user->id)->update([
            'last_read_at' => now(),
            'last_read_message_id' => $latestId,
        ]);

        MessageRead::dispatch($conversation->id, $user->id, $latestId);

        return response()->json(['ok' => true]);
    }
}
```

Add to the auth group in `routes/web.php`:
```php
use App\Http\Controllers\Chat\MarkReadController;

Route::post('/conversations/{conversation}/read', [MarkReadController::class, 'store'])
    ->name('conversations.read');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=MarkReadTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Events/MessageRead.php app/Http/Controllers/Chat/MarkReadController.php routes/web.php tests/Feature/Chat/MarkReadTest.php
git commit -m "Add read receipts with broadcast on conversation channel"
```

---

## Task 7: Message pagination endpoint (cursor)

**Files:**
- Create: `app/Http/Controllers/Chat/ConversationMessagesController.php`
- Modify: `routes/web.php` (add `conversations.messages.index`)
- Test: `tests/Feature/Chat/MessagePaginationTest.php`

**Interfaces:**
- Consumes: policy `view` (Task 5), `MessageResource` (Task 5).
- Produces: `GET /conversations/{id}/messages?before=<id>` → `{ messages: MessageResource[] }` oldest→newest, 30/page.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Chat/MessagePaginationTest.php

use App\Models\Message;
use App\Models\Pairing;

test('messages paginate oldest-to-newest with a before cursor', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ])->conversation()->firstOrFail();

    $ids = collect(range(1, 40))->map(fn ($n) => $conversation->messages()->create([
        'sender_user_id' => $mentor->id, 'type' => 'text', 'body' => "msg {$n}",
    ])->id);

    $firstPage = $this->actingAs($entrepreneur)
        ->getJson("/conversations/{$conversation->id}/messages")
        ->assertOk()->json('messages');

    expect($firstPage)->toHaveCount(30)
        ->and($firstPage[0]['id'])->toBe($ids[10])          // oldest of the newest 30
        ->and(end($firstPage)['id'])->toBe($ids[39]);       // newest overall

    $oldest = $firstPage[0]['id'];
    $secondPage = $this->actingAs($entrepreneur)
        ->getJson("/conversations/{$conversation->id}/messages?before={$oldest}")
        ->assertOk()->json('messages');

    expect($secondPage)->toHaveCount(10)
        ->and(end($secondPage)['id'])->toBe($ids[9]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MessagePaginationTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the controller and route**

```php
<?php // app/Http/Controllers/Chat/ConversationMessagesController.php
namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationMessagesController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $before = $request->integer('before');

        $messages = $conversation->messages()
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->sortBy('id')
            ->values();

        return response()->json(['messages' => MessageResource::collection($messages)]);
    }
}
```

Add to the auth group in `routes/web.php`:
```php
use App\Http\Controllers\Chat\ConversationMessagesController;

Route::get('/conversations/{conversation}/messages', [ConversationMessagesController::class, 'index'])
    ->name('conversations.messages.index');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MessagePaginationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Chat/ConversationMessagesController.php routes/web.php tests/Feature/Chat/MessagePaginationTest.php
git commit -m "Add cursor-paginated message history endpoint"
```

---

## Task 8: Messages page (Inertia controller + routes)

**Files:**
- Create: `app/Http/Controllers/Chat/MessagesController.php`
- Modify: `routes/web.php` (add `messages.index` + `messages.show` under both role prefixes)
- Test: `tests/Feature/Chat/MessagesPageTest.php`

**Interfaces:**
- Consumes: `Conversation`, `MessageResource`, policy `view`.
- Produces: Inertia page `messages/Index` with props `{ conversations: ConversationSummary[], selectedId: number|null, thread: { conversation, messages } | null, currentUserId: number }`. `ConversationSummary` per Shared Contracts.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Chat/MessagesPageTest.php

use App\Models\Pairing;
use Inertia\Testing\AssertableInertia as Assert;

test('the messages page lists the user conversations with unread counts', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ])->conversation()->firstOrFail();
    $conversation->messages()->create(['sender_user_id' => $mentor->id, 'type' => 'text', 'body' => 'Welcome!']);

    $this->actingAs($entrepreneur)
        ->get('/entrepreneur/messages')
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/Index')
            ->where('currentUserId', $entrepreneur->id)
            ->has('conversations', 1, fn (Assert $c) => $c
                ->where('id', $conversation->id)
                ->where('other.id', $mentor->id)
                ->where('other.name', $mentor->name)
                ->where('unread_count', 1)
                ->where('is_active', true)
                ->etc()));
});

test('opening a conversation returns its thread and forbids non-participants', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ])->conversation()->firstOrFail();
    $conversation->messages()->create(['sender_user_id' => $mentor->id, 'type' => 'text', 'body' => 'Hi']);

    $this->actingAs($entrepreneur)
        ->get("/entrepreneur/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selectedId', $conversation->id)->has('thread.messages', 1));

    $this->actingAs(availableMentor())
        ->get("/entrepreneur/messages/{$conversation->id}")
        ->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MessagesPageTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the controller**

```php
<?php // app/Http/Controllers/Chat/MessagesController.php
namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MessagesController extends Controller
{
    public function index(Request $request, ?Conversation $conversation = null): Response
    {
        $user = $request->user();

        if ($conversation !== null) {
            $this->authorize('view', $conversation);
        }

        return Inertia::render('messages/Index', [
            'currentUserId' => $user->id,
            'conversations' => $this->summaries($user),
            'selectedId' => $conversation?->id,
            'thread' => $conversation ? $this->thread($conversation, $user) : null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function summaries(User $user): array
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->with(['pairing.entrepreneur', 'pairing.mentor', 'participants'])
            ->orderByRaw('last_message_at IS NULL, last_message_at DESC')
            ->get();

        return $conversations->map(function (Conversation $conversation) use ($user) {
            $otherId = $conversation->otherParticipant($user)->user_id;
            $other = $conversation->pairing->entrepreneur->id === $otherId
                ? $conversation->pairing->entrepreneur
                : $conversation->pairing->mentor;

            return [
                'id' => $conversation->id,
                'other' => $this->userSummary($other),
                'last_message_preview' => $conversation->last_message_preview,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'unread_count' => $conversation->unreadCountFor($user),
                'is_active' => $conversation->isActive(),
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function thread(Conversation $conversation, User $user): array
    {
        $otherId = $conversation->otherParticipant($user)->user_id;
        $other = $conversation->pairing->entrepreneur->id === $otherId
            ? $conversation->pairing->entrepreneur
            : $conversation->pairing->mentor;

        $messages = $conversation->messages()->orderByDesc('id')->limit(30)->get()->sortBy('id')->values();

        return [
            'conversation' => [
                'id' => $conversation->id,
                'other' => $this->userSummary($other),
                'is_active' => $conversation->isActive(),
                'pairing_id' => $conversation->pairing_id,
                'other_last_read_message_id' => $conversation->otherParticipant($user)->last_read_message_id,
            ],
            'messages' => MessageResource::collection($messages)->toArray($this->request()),
        ];
    }

    /** @return array<string, mixed> */
    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'initials' => Str::of($user->name)->explode(' ')->take(2)->map(fn ($p) => Str::substr($p, 0, 1))->implode(''),
            'role' => $user->role->value,
        ];
    }

    private function request(): Request
    {
        return request();
    }
}
```

- [ ] **Step 4: Register routes under both role prefixes**

Inside the `entrepreneur` group in `routes/web.php`:
```php
use App\Http\Controllers\Chat\MessagesController;

Route::get('/messages', [MessagesController::class, 'index'])->middleware('account.active')->name('entrepreneur.messages');
Route::get('/messages/{conversation}', [MessagesController::class, 'index'])->middleware('account.active')->name('entrepreneur.messages.show');
```
Inside the `mentor` group:
```php
Route::get('/messages', [MessagesController::class, 'index'])->middleware('account.active')->name('mentor.messages');
Route::get('/messages/{conversation}', [MessagesController::class, 'index'])->middleware('account.active')->name('mentor.messages.show');
```

> The tests reference the paths `/entrepreneur/messages` and `/entrepreneur/messages/{id}`; the names differ by role. The Svelte page uses the current URL, not the names, so a single component serves both.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=MessagesPageTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Chat/MessagesController.php routes/web.php tests/Feature/Chat/MessagesPageTest.php
git commit -m "Add Inertia messages page controller and role routes"
```

---

## Task 9: Frontend types + Echo helper

**Files:**
- Create: `resources/js/pages/messages/types.ts`
- Create: `resources/js/lib/chat.ts` (subscribe/teardown helpers over `echo`)
- Test: `npm run types:check`

**Interfaces:**
- Consumes: `echo` (Task 3).
- Produces: types `Message`, `ConversationSummary`, `Thread`, `MessagePageProps`; helpers `subscribeConversation(id, handlers)`, `subscribeUser(id, handlers)`, `joinPresence(handlers)`, each returning an unsubscribe function; `whisperTyping(id, payload)`.

- [ ] **Step 1: Write the types**

```ts
// resources/js/pages/messages/types.ts
export type ChatUser = { id: number; name: string; initials: string; role: string };

export type Message = {
    id: number;
    conversation_id: number;
    sender_id: number | null;
    type: 'text' | 'system';
    body: string;
    created_at: string;
};

export type ConversationSummary = {
    id: number;
    other: ChatUser;
    last_message_preview: string | null;
    last_message_at: string | null;
    unread_count: number;
    is_active: boolean;
};

export type Thread = {
    conversation: {
        id: number;
        other: ChatUser;
        is_active: boolean;
        pairing_id: number;
        other_last_read_message_id: number | null;
    };
    messages: Message[];
};

export type MessagePageProps = {
    currentUserId: number;
    conversations: ConversationSummary[];
    selectedId: number | null;
    thread: Thread | null;
};
```

- [ ] **Step 2: Write the Echo helpers**

```ts
// resources/js/lib/chat.ts
import { echo } from '@/echo';
import type { Message } from '@/pages/messages/types';

type MessageSentPayload = {
    message: Message;
    conversation: { id: number; last_message_preview: string; last_message_at: string };
    recipient_unread_count: number;
};
type MessageReadPayload = { reader_id: number; last_read_message_id: number | null };

export function subscribeConversation(
    id: number,
    handlers: {
        onMessage: (p: MessageSentPayload) => void;
        onRead: (p: MessageReadPayload) => void;
        onTyping: (p: { user_id: number }) => void;
    },
): () => void {
    const channel = echo.private(`conversation.${id}`);
    channel.listen('.message.sent', handlers.onMessage);
    channel.listen('.message.read', handlers.onRead);
    channel.listenForWhisper('typing', handlers.onTyping);
    return () => echo.leave(`conversation.${id}`);
}

export function subscribeUser(id: number, onMessage: (p: MessageSentPayload) => void): () => void {
    echo.private(`user.${id}`).listen('.message.sent', onMessage);
    return () => echo.leave(`user.${id}`);
}

export function joinPresence(handlers: {
    here: (users: { id: number }[]) => void;
    joining: (u: { id: number }) => void;
    leaving: (u: { id: number }) => void;
}): () => void {
    echo.join('online').here(handlers.here).joining(handlers.joining).leaving(handlers.leaving);
    return () => echo.leave('online');
}

export function whisperTyping(id: number, userId: number): void {
    echo.private(`conversation.${id}`).whisper('typing', { user_id: userId });
}
```

- [ ] **Step 3: Type-check**

Run: `npm run types:check`
Expected: 0 new errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/messages/types.ts resources/js/lib/chat.ts
git commit -m "Add chat frontend types and Echo subscription helpers"
```

---

## Task 10: Messages page UI (two-pane, realtime)

**Files:**
- Create: `resources/js/pages/messages/Index.svelte`
- Create: `resources/js/pages/messages/ConversationList.svelte`
- Create: `resources/js/pages/messages/Thread.svelte`
- Create: `resources/js/pages/messages/MessageBubble.svelte`
- Create: `resources/js/pages/messages/Composer.svelte`
- Test: `npm run types:check` + manual browser check via a temporary preview route

**Interfaces:**
- Consumes: types (Task 9), `subscribeConversation`/`subscribeUser`/`joinPresence`/`whisperTyping` (Task 9), endpoints from Shared Contracts, `router` from `@inertiajs/svelte`.
- Produces: the `messages/Index` page rendered by `MessagesController`.

- [ ] **Step 1: Build the leaf components**

```svelte
<!-- resources/js/pages/messages/MessageBubble.svelte -->
<script lang="ts">
    import type { Message } from './types';
    let { message, mine, seen = false }: { message: Message; mine: boolean; seen?: boolean } = $props();
    const time = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(message.created_at));
</script>

{#if message.type === 'system'}
    <div class="my-2 flex justify-center">
        <span class="rounded-full bg-elevated px-3 py-1 text-xs text-muted">{message.body}</span>
    </div>
{:else}
    <div class="flex flex-col {mine ? 'items-end' : 'items-start'} gap-0.5">
        <div class="max-w-[75%] rounded-2xl px-3.5 py-2 text-[15px] {mine ? 'bg-accent text-on-accent' : 'bg-elevated text-ink'}">
            {message.body}
        </div>
        <span class="px-1 text-[11px] text-faint">{time}{#if mine && seen} · Seen{/if}</span>
    </div>
{/if}
```

```svelte
<!-- resources/js/pages/messages/Composer.svelte -->
<script lang="ts">
    import { Send } from '@lucide/svelte';
    let { disabled = false, onsend, ontyping }: { disabled?: boolean; onsend: (body: string) => void; ontyping: () => void } = $props();
    let body = $state('');
    let lastTyped = 0;

    function submit(e: Event) {
        e.preventDefault();
        const text = body.trim();
        if (!text) return;
        onsend(text);
        body = '';
    }
    function onInput() {
        const now = Date.now();
        if (now - lastTyped > 1500) { ontyping(); lastTyped = now; }
    }
</script>

<form onsubmit={submit} class="flex items-end gap-2 border-t border-line p-3">
    <textarea
        bind:value={body}
        oninput={onInput}
        {disabled}
        rows="1"
        placeholder={disabled ? 'This mentorship has ended' : 'Write a message…'}
        class="max-h-32 flex-1 resize-none rounded-lg border border-line bg-surface px-3 py-2 text-[15px] text-ink outline-none placeholder:text-faint focus:border-accent disabled:opacity-60"
        onkeydown={(e) => { if (e.key === 'Enter' && !e.shiftKey) submit(e); }}
    ></textarea>
    <button type="submit" {disabled} aria-label="Send" class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-on-accent transition-colors hover:bg-accent-strong disabled:opacity-50">
        <Send class="size-4" strokeWidth={2} />
    </button>
</form>
```

- [ ] **Step 2: Build the conversation list**

```svelte
<!-- resources/js/pages/messages/ConversationList.svelte -->
<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';
    import type { ConversationSummary } from './types';

    let { conversations, selectedId, onlineIds, rolePrefix }:
        { conversations: ConversationSummary[]; selectedId: number | null; onlineIds: Set<number>; rolePrefix: string } = $props();

    function open(id: number) {
        router.visit(`/${rolePrefix}/messages/${id}`, { preserveState: true, preserveScroll: true });
    }
    const rel = (iso: string | null) => iso ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(iso)) : '';
</script>

<ul class="divide-y divide-line overflow-y-auto">
    {#each conversations as c (c.id)}
        <li>
            <button
                onclick={() => open(c.id)}
                class={cn('flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-elevated/50', selectedId === c.id && 'bg-elevated')}
            >
                <div class="relative shrink-0">
                    <div class="flex size-11 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent">{c.other.initials}</div>
                    {#if onlineIds.has(c.other.id)}<span class="absolute right-0 bottom-0 size-3 rounded-full border-2 border-panel bg-emerald-500"></span>{/if}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="truncate text-sm font-medium text-ink">{c.other.name}</p>
                        <span class="shrink-0 text-[11px] text-faint">{rel(c.last_message_at)}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <p class={cn('truncate text-xs', c.unread_count > 0 ? 'font-medium text-ink' : 'text-muted')}>{c.last_message_preview ?? 'No messages yet'}</p>
                        {#if c.unread_count > 0}<span class="flex min-w-5 shrink-0 items-center justify-center rounded-full bg-accent px-1.5 text-[11px] font-semibold text-on-accent">{c.unread_count}</span>{/if}
                    </div>
                </div>
            </button>
        </li>
    {/each}
</ul>
```

- [ ] **Step 3: Build the thread pane**

```svelte
<!-- resources/js/pages/messages/Thread.svelte -->
<script lang="ts">
    import { onDestroy } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import { CalendarPlus } from '@lucide/svelte';
    import { subscribeConversation, whisperTyping } from '@/lib/chat';
    import type { Message, Thread } from './types';
    import MessageBubble from './MessageBubble.svelte';
    import Composer from './Composer.svelte';

    let { thread, currentUserId, rolePrefix }:
        { thread: Thread; currentUserId: number; rolePrefix: string } = $props();

    let messages = $state<Message[]>(thread.messages);
    let otherLastRead = $state<number | null>(thread.conversation.other_last_read_message_id);
    let typing = $state(false);
    let typingTimer: ReturnType<typeof setTimeout>;
    let teardown: () => void = () => {};

    // Re-subscribe whenever the open conversation changes.
    $effect(() => {
        const id = thread.conversation.id;
        messages = thread.messages;
        otherLastRead = thread.conversation.other_last_read_message_id;
        markRead(id);
        teardown();
        teardown = subscribeConversation(id, {
            onMessage: ({ message }) => {
                if (message.sender_id !== currentUserId) { messages = [...messages, message]; markRead(id); }
            },
            onRead: ({ last_read_message_id }) => (otherLastRead = last_read_message_id),
            onTyping: () => { typing = true; clearTimeout(typingTimer); typingTimer = setTimeout(() => (typing = false), 3000); },
        });
        return () => teardown();
    });
    onDestroy(() => teardown());

    async function send(body: string) {
        const optimistic: Message = { id: Date.now(), conversation_id: thread.conversation.id, sender_id: currentUserId, type: 'text', body, created_at: new Date().toISOString() };
        messages = [...messages, optimistic];
        const res = await fetch(`/conversations/${thread.conversation.id}/messages`, {
            method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ body }),
        });
        if (res.ok) { const { id } = (await res.json()); messages = messages.map((m) => m.id === optimistic.id ? { ...m, id } : m); }
    }
    function onTyping() { whisperTyping(thread.conversation.id, currentUserId); }
    function markRead(id: number) { fetch(`/conversations/${id}/read`, { method: 'POST', headers: jsonHeaders() }); }
    function schedule() { router.visit(`/${rolePrefix}/meetings?mentor=${thread.conversation.pairing_id}`); }
    function jsonHeaders() {
        return { 'Content-Type': 'application/json', Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') };
    }
    const lastMineId = $derived(messages.filter((m) => m.sender_id === currentUserId).at(-1)?.id ?? -1);
</script>

<div class="flex h-full flex-col">
    <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <div class="flex items-center gap-3">
            <div class="flex size-9 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent">{thread.conversation.other.initials}</div>
            <p class="text-sm font-semibold text-ink">{thread.conversation.other.name}</p>
        </div>
        <button onclick={schedule} class="inline-flex items-center gap-1.5 rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink transition-colors hover:bg-elevated">
            <CalendarPlus class="size-3.5" strokeWidth={1.75} /> Schedule a call
        </button>
    </header>

    <div class="flex flex-1 flex-col gap-1.5 overflow-y-auto px-4 py-4">
        {#each messages as m (m.id)}
            <MessageBubble message={m} mine={m.sender_id === currentUserId} seen={m.id === lastMineId && otherLastRead !== null && otherLastRead >= m.id} />
        {/each}
        {#if typing}<p class="px-1 text-xs text-faint">{thread.conversation.other.name} is typing…</p>{/if}
    </div>

    <Composer disabled={!thread.conversation.is_active} onsend={send} ontyping={onTyping} />
</div>
```

- [ ] **Step 4: Build the page shell**

```svelte
<!-- resources/js/pages/messages/Index.svelte -->
<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import EntrepreneurLayout from '@/components/layout/EntrepreneurLayout.svelte';
    import MentorLayout from '@/components/layout/MentorLayout.svelte';
    import { subscribeUser, joinPresence } from '@/lib/chat';
    import type { MessagePageProps } from './types';
    import ConversationList from './ConversationList.svelte';
    import ThreadPane from './Thread.svelte';

    let { currentUserId, conversations, selectedId, thread }: MessagePageProps = $props();

    const rolePrefix = window.location.pathname.split('/')[1]; // 'entrepreneur' | 'mentor'
    const Layout = rolePrefix === 'mentor' ? MentorLayout : EntrepreneurLayout;

    let list = $state(conversations);
    let onlineIds = $state(new Set<number>());
    let userTeardown: () => void = () => {};
    let presenceTeardown: () => void = () => {};

    $effect(() => { list = conversations; });

    onMount(() => {
        userTeardown = subscribeUser(currentUserId, ({ message, conversation, recipient_unread_count }) => {
            list = list.map((c) => c.id === conversation.id
                ? { ...c, last_message_preview: conversation.last_message_preview, last_message_at: conversation.last_message_at,
                    unread_count: c.id === selectedId ? 0 : recipient_unread_count }
                : c).sort((a, b) => (b.last_message_at ?? '').localeCompare(a.last_message_at ?? ''));
        });
        presenceTeardown = joinPresence({
            here: (users) => (onlineIds = new Set(users.map((u) => u.id))),
            joining: (u) => (onlineIds = new Set([...onlineIds, u.id])),
            leaving: (u) => { const n = new Set(onlineIds); n.delete(u.id); onlineIds = n; },
        });
    });
    onDestroy(() => { userTeardown(); presenceTeardown(); });
</script>

<Layout title="Messages">
    <div class="mx-auto flex h-[calc(100vh-3.5rem)] w-full max-w-7xl">
        <aside class="flex w-full max-w-sm flex-col border-r border-line bg-panel {thread ? 'hidden md:flex' : 'flex'}">
            <div class="border-b border-line px-4 py-4"><h1 class="text-lg font-semibold text-ink">Messages</h1></div>
            <ConversationList conversations={list} {selectedId} {onlineIds} {rolePrefix} />
        </aside>
        <section class="flex-1 {thread ? 'flex' : 'hidden md:flex'}">
            {#if thread}
                {#key thread.conversation.id}
                    <ThreadPane {thread} {currentUserId} {rolePrefix} />
                {/key}
            {:else}
                <div class="flex flex-1 items-center justify-center text-sm text-muted">Select a conversation to start messaging.</div>
            {/if}
        </section>
    </div>
</Layout>
```

- [ ] **Step 5: Type-check and manual verify**

Run: `npm run types:check`
Expected: 0 new errors.

Manual: temporarily add `Route::inertia('/__preview/messages', 'messages/Index')` is **not** possible (props required), so verify via a seeded login instead — run `php artisan reverb:start`, `php artisan queue:work`, `npm run dev`, log in as a paired entrepreneur, open `/entrepreneur/messages`, and confirm: list renders, opening a thread loads messages, sending appears instantly, and a second logged-in browser (the mentor) receives it live with a typing indicator and "Seen".

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/messages/
git commit -m "Build two-pane realtime messages UI"
```

---

## Task 11: Nav "Messages" item + global unread badge

**Files:**
- Modify: `resources/js/components/layout/EntrepreneurLayout.svelte`, `resources/js/components/layout/MentorLayout.svelte`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (share `unreadMessages` count)
- Test: `tests/Feature/Chat/UnreadShareTest.php`

**Interfaces:**
- Consumes: `Conversation::unreadCountFor` (Task 1), `subscribeUser` (Task 9).
- Produces: Inertia shared prop `auth.unreadMessages: number`; a "Messages" nav item with a live badge in both layouts.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Chat/UnreadShareTest.php

use App\Models\Pairing;
use Inertia\Testing\AssertableInertia as Assert;

test('the total unread message count is shared to Inertia', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ])->conversation()->firstOrFail();
    $conversation->messages()->create(['sender_user_id' => $mentor->id, 'type' => 'text', 'body' => 'a']);
    $conversation->messages()->create(['sender_user_id' => $mentor->id, 'type' => 'text', 'body' => 'b']);

    $this->actingAs($entrepreneur)
        ->get('/entrepreneur/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.unreadMessages', 2));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UnreadShareTest`
Expected: FAIL — `auth.unreadMessages` missing.

- [ ] **Step 3: Share the count**

In `app/Http/Middleware/HandleInertiaRequests.php`, inside the `share()` `'auth'` array, add:
```php
'unreadMessages' => $request->user()
    ? \App\Models\Message::query()
        ->whereHas('conversation.participants', fn ($q) => $q->where('user_id', $request->user()->id))
        ->where('sender_user_id', '!=', $request->user()->id)
        ->whereNotExists(function ($q) use ($request) {
            $q->selectRaw('1')->from('conversation_participants as cp')
              ->whereColumn('cp.conversation_id', 'messages.conversation_id')
              ->where('cp.user_id', $request->user()->id)
              ->whereColumn('cp.last_read_message_id', '>=', 'messages.id');
        })->count()
    : 0,
```

- [ ] **Step 4: Add the nav item + live badge to both layouts**

In each layout's `links` array add (entrepreneur path shown; use `/mentor/messages` in `MentorLayout`):
```js
{ label: 'Messages', href: '/entrepreneur/messages', icon: MessageSquare, badgeKey: 'messages' },
```
Import `MessageSquare` from `@lucide/svelte`. In the script, wire the live badge:
```svelte
<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { subscribeUser } from '@/lib/chat';
    // ...existing imports...
    let unread = $state($page.props.auth.unreadMessages ?? 0);
    onMount(() => subscribeUser($page.props.auth.user.id, () => (unread += 1)));
</script>
```
Render the badge next to the Messages item when `unread > 0` (mirror the existing active-item markup, adding):
```svelte
{#if link.badgeKey === 'messages' && unread > 0}
    <span class="ml-auto flex min-w-5 items-center justify-center rounded-full bg-accent px-1.5 text-[11px] font-semibold text-on-accent">{unread}</span>
{/if}
```

> Reset `unread` to 0 when the user navigates to a messages route (`$effect` on `$page.url`): `$effect(() => { if ($page.url.includes('/messages')) unread = 0; });`

- [ ] **Step 5: Run test + type-check**

Run: `php artisan test --filter=UnreadShareTest` → PASS
Run: `npm run types:check` → 0 new errors

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/components/layout/EntrepreneurLayout.svelte resources/js/components/layout/MentorLayout.svelte tests/Feature/Chat/UnreadShareTest.php
git commit -m "Add Messages nav item with live global unread badge"
```

---

## Task 12: Booking integration — system message on scheduled call

**Files:**
- Create: `app/Actions/Chat/PostSystemMessage.php`
- Modify: `app/Actions/Mentorship/BookMeeting.php` (emit a system message after booking)
- Test: `tests/Feature/Chat/BookingSystemMessageTest.php`

**Interfaces:**
- Consumes: `Conversation`, `MessageType`, `MessageSent` (via `PostMessage` pattern), `Meeting`/`Pairing` (existing).
- Produces: `PostSystemMessage::handle(Conversation, string $body): Message`; a `system` message appears in the pairing's conversation whenever `BookMeeting::handle` books a meeting.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Chat/BookingSystemMessageTest.php

use App\Actions\Mentorship\BookMeeting;
use App\Enums\MessageType;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;

test('booking a meeting posts a system message into the conversation', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);
    $slot = MentorAvailabilitySlot::factory()->for($mentor, 'mentor')->create([
        'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '10:00', 'is_active' => true,
    ]);

    app(BookMeeting::class)->handle($pairing, $slot);

    $conversation = $pairing->conversation()->firstOrFail();
    $system = $conversation->messages()->where('type', MessageType::System)->first();
    expect($system)->not->toBeNull()
        ->and($system->body)->toContain('Call scheduled');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BookingSystemMessageTest`
Expected: FAIL — no system message created.

- [ ] **Step 3: Write the system-message action**

```php
<?php // app/Actions/Chat/PostSystemMessage.php
namespace App\Actions\Chat;

use App\Enums\MessageType;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostSystemMessage
{
    public function handle(Conversation $conversation, string $body): Message
    {
        $message = DB::transaction(function () use ($conversation, $body) {
            $message = $conversation->messages()->create([
                'sender_user_id' => null,
                'type' => MessageType::System,
                'body' => $body,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_preview' => Str::limit($body, 180),
                'last_message_sender_id' => null,
            ])->save();

            return $message;
        });

        // Broadcast to both participants; unread count is per-recipient but a
        // system line is informational, so count it for each side's badge.
        foreach ($conversation->participants as $participant) {
            $unread = $conversation->messages()
                ->where('id', '>', $participant->last_read_message_id ?? 0)
                ->count();
            MessageSent::dispatch($message, $participant->user_id, $unread);
        }

        return $message;
    }
}
```

- [ ] **Step 4: Emit from BookMeeting**

In `app/Actions/Mentorship/BookMeeting.php`, after the `Meeting::create([...])` call, capture the meeting and post the system message before returning it:

```php
use App\Actions\Chat\PostSystemMessage;
// ...

$meeting = Meeting::create([ /* ...existing... */ ]);

if ($conversation = $pairing->conversation()->first()) {
    $when = $meeting->starts_at->timezone($meeting->timezone)->format('D j M, g:i A');
    app(PostSystemMessage::class)->handle($conversation, "📅 Call scheduled for {$when}");
}

return $meeting;
```

> Keep this defensive (`if ($conversation …)`) so bookings never fail if a legacy pairing has no conversation.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=BookingSystemMessageTest`
Expected: PASS.

- [ ] **Step 6: Run the full chat suite + commit**

Run: `php artisan test --filter=Chat`
Expected: all chat tests PASS.

```bash
vendor/bin/pint --dirty
git add app/Actions/Chat/PostSystemMessage.php app/Actions/Mentorship/BookMeeting.php tests/Feature/Chat/BookingSystemMessageTest.php
git commit -m "Post a system message into the conversation when a call is scheduled"
```

---

## Deployment note (not a code task)

Production must run **three** processes plus Redis: `php-fpm` (web), `php artisan queue:work` (delivers broadcasts), and `php artisan reverb:start` (WebSocket server). Set `REVERB_SCALING_ENABLED=true` to run multiple Reverb nodes behind a load balancer. Add these to the deploy/process manager config (Supervisor/systemd) and run `php artisan chat:backfill-conversations` once after deploy.

## Self-review notes

- **Spec coverage:** conversation-per-pairing + backfill (T2), Reverb+queue (T3), channels/auth (T4, policy T5), send (T5), read receipts (T6), pagination (T7), Inertia page/list/thread (T8/T10), presence + typing (T9/T10), global unread badge (T11), booking CTA + system message (T10 button, T12 message). Attachments/search correctly absent (v2).
- **Type consistency:** message JSON shape (`{id, conversation_id, sender_id, type, body, created_at}`) is identical in `MessageResource`, `MessageSent`, and the TS `Message` type. Channel names and event names match between `channels.php`, the events, and `lib/chat.ts`. Route paths match between `routes/web.php`, controllers, and the Svelte `fetch`/`router.visit` calls.
- **Deferred to execution:** the exact insertion lines in `HandleInertiaRequests.php` and the two layout files depend on their current structure; the implementer reads the file and follows the described placement.
