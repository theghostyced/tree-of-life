<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\MarkOnboardingComplete;
use App\Actions\StoreUserDocument;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\UploadDocumentRequest;
use App\Models\UserDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function store(UploadDocumentRequest $request): RedirectResponse
    {
        app(StoreUserDocument::class)->handle(
            $request->user(),
            DocumentType::from($request->validated('document_type')),
            $request->file('file'),
        );

        app(MarkOnboardingComplete::class)->handle($request->user());

        return back()->with('status', 'Document uploaded.');
    }

    public function show(UserDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    /** The same private file served inline so it can render in an iframe. */
    public function preview(UserDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_unless($document->isPreviewable(), 404);

        return Storage::disk($document->disk)->response(
            $document->path,
            $this->headerSafeFilename($document->original_name),
            [
                'Content-Type' => $document->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    /** Strip path, control characters (CRLF injection) and header delimiters. */
    private function headerSafeFilename(string $original): string
    {
        $filename = preg_replace(
            '#[\x00-\x1F\x7F:;",/\\\\]+#',
            '',
            basename($original),
        ) ?? '';

        return trim($filename) !== '' ? trim($filename) : 'document';
    }
}
