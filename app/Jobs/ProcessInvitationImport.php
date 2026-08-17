<?php

namespace App\Jobs;

use App\Actions\CreateUserInvitation;
use App\Actions\SendInvitationMail;
use App\Enums\InvitationImportStatus;
use App\Enums\UserRole;
use App\Models\InvitationImport;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessInvitationImport implements ShouldQueue
{
    use Queueable;

    /** Row problems are data, not retries; a rerun would double-count. */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public InvitationImport $import) {}

    /**
     * A line fgetcsv yields for a blank row: [null], or a single empty cell.
     * The upload endpoint and validator must count rows by this same rule
     * so total_rows always matches what the job will process.
     *
     * @param  array<int, string|null>  $cells
     */
    public static function isBlankRow(array $cells): bool
    {
        return $cells === [null] || (count($cells) === 1 && trim((string) $cells[0]) === '');
    }

    public function handle(CreateUserInvitation $createInvitation): void
    {
        $this->import->update(['status' => InvitationImportStatus::Processing]);

        $disk = Storage::disk(config('filesystems.private'));
        $line = 1;
        $stream = null;

        try {
            $stream = $disk->readStream($this->import->storagePath());

            if ($stream === null) {
                throw new \RuntimeException('Import file is missing.');
            }

            fgetcsv($stream, escape: ''); // header, validated at upload time
            $processed = 0;
            $seen = [];

            while (($cells = fgetcsv($stream, escape: '')) !== false) {
                $line++;

                if (self::isBlankRow($cells)) {
                    continue;
                }

                $this->processRow($createInvitation, $cells, $line, $seen);

                if (++$processed % 25 === 0) {
                    $this->import->save();
                }
            }

            $this->import->status = InvitationImportStatus::Completed;
            $this->import->save();
        } catch (Throwable $e) {
            Log::error('Invitation import failed', [
                'import_id' => $this->import->id,
                'exception' => $e,
            ]);

            // Spec: a failed import records the row it died on. A crash marker
            // is not a skipped/invalid row, so it bypasses the counters.
            $errors = $this->import->row_errors ?? [];
            $errors[] = ['row' => $line, 'email' => '', 'reason' => 'import stopped unexpectedly at this row'];
            $this->import->row_errors = $errors;
            $this->import->status = InvitationImportStatus::Failed;
            $this->import->save();
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            $disk->delete($this->import->storagePath());
        }
    }

    /**
     * A timeout hard-kill skips handle()'s catch/finally entirely; without
     * this hook the import would sit at Processing forever while every
     * admin's browser keeps polling it.
     */
    public function failed(?Throwable $exception): void
    {
        $import = $this->import->fresh() ?? $this->import;

        $errors = $import->row_errors ?? [];
        $errors[] = ['row' => 0, 'email' => '', 'reason' => 'import stopped unexpectedly'];

        $import->row_errors = $errors;
        $import->status = InvitationImportStatus::Failed;
        $import->save();

        Storage::disk(config('filesystems.private'))->delete($import->storagePath());
    }

    /**
     * Apply the spec's per-row rules, in order. Skips and invalids record a
     * report entry and never stop the import.
     *
     * @param  array<int, string|null>  $cells
     * @param  array<string, true>  $seen
     */
    private function processRow(CreateUserInvitation $createInvitation, array $cells, int $line, array &$seen): void
    {
        $email = strtolower(trim((string) ($cells[0] ?? '')));
        $roleRaw = strtolower(trim((string) ($cells[1] ?? '')));
        $name = trim((string) ($cells[2] ?? ''));
        $name = $name === '' ? null : $name;

        if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->record('invalid_count', $line, $email, 'invalid email');

            return;
        }

        $role = UserRole::tryFrom($roleRaw);

        if ($role === null || ! in_array($role, UserRole::invitable(), true)) {
            $this->record('invalid_count', $line, $email, sprintf('unknown role "%s"', $roleRaw));

            return;
        }

        if ($name !== null && strlen($name) > 255) {
            $this->record('invalid_count', $line, $email, 'name too long');

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->record('skipped_count', $line, $email, 'already a user');

            return;
        }

        $activeInvitation = UserInvitation::query()
            ->where('email', $email)
            ->where('role', $role->value)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($activeInvitation) {
            $this->record('skipped_count', $line, $email, 'already invited');

            return;
        }

        if (isset($seen[$email])) {
            $this->record('skipped_count', $line, $email, 'duplicate row in file');

            return;
        }

        $seen[$email] = true;

        [$invitation, $token] = $createInvitation->handle(
            email: $email,
            role: $role,
            invitedBy: $this->import->importer,
            name: $name,
        );

        if (! app(SendInvitationMail::class)->handle($invitation, $token)) {
            // The row is still a real invitation, so it counts as invited; the
            // admin retries the delivery from the invitations list.
            $this->record('invited_count', $line, $email, 'invited, but the email could not be delivered');

            return;
        }

        $this->import->invited_count++;
    }

    private function record(string $counter, int $line, string $email, string $reason): void
    {
        $this->import->{$counter}++;

        $errors = $this->import->row_errors ?? [];

        if (count($errors) < InvitationImport::MAX_ROW_ERRORS) {
            $errors[] = ['row' => $line, 'email' => $email, 'reason' => $reason];
            $this->import->row_errors = $errors;
        }
    }
}
