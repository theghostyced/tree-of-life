<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    /**
     * Pictures live on the private disk, so they are streamed here rather than
     * linked directly; the content type comes from the stored bytes.
     */
    public function show(User $user): StreamedResponse
    {
        Gate::authorize('viewAvatar', $user);

        $disk = Storage::disk(config('filesystems.private'));

        abort_if($user->avatar_path === null || ! $disk->exists($user->avatar_path), 404);

        return $disk->response($user->avatar_path, 'avatar', [
            'Content-Type' => $disk->mimeType($user->avatar_path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ], 'inline');
    }
}
