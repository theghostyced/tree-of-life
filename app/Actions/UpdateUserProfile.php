<?php

namespace App\Actions;

use App\Models\User;
use App\Support\RoleBio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UpdateUserProfile
{
    /**
     * A new picture replaces (and deletes) the previous one; removal deletes it
     * outright. The email is never touched here.
     */
    public function handle(
        User $user,
        string $name,
        ?string $bio,
        ?UploadedFile $avatar,
        bool $removeAvatar = false,
    ): void {
        DB::transaction(function () use ($user, $name, $bio, $avatar, $removeAvatar): void {
            $user->name = $name;

            if ($avatar !== null || $removeAvatar) {
                $this->deleteCurrentAvatar($user);
                $user->avatar_path = $avatar === null ? null : $this->storeAvatar($user, $avatar);
            }

            $user->save();

            RoleBio::store($user, $bio);
        });
    }

    private function storeAvatar(User $user, UploadedFile $avatar): string
    {
        $path = $avatar->store("avatars/{$user->id}", config('filesystems.private'));

        if ($path === false) {
            throw new RuntimeException('The profile picture could not be stored.');
        }

        return $path;
    }

    private function deleteCurrentAvatar(User $user): void
    {
        if ($user->avatar_path !== null) {
            Storage::disk(config('filesystems.private'))->delete($user->avatar_path);
        }
    }
}
