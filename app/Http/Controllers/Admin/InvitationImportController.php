<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvitationImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvitationImportRequest;
use App\Jobs\ProcessInvitationImport;
use App\Models\InvitationImport;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InvitationImportController extends Controller
{
    public function template(): StreamedResponse
    {
        Gate::authorize('create', UserInvitation::class);

        $content = "email,role,name\n"
            ."amara@example.com,entrepreneur,Amara Okafor\n"
            ."kwame@example.com,mentor,\n";

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            'invitations-template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    public function store(StoreInvitationImportRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        // Count data rows (non-blank lines below the header) for progress totals.
        $stream = fopen($file->getRealPath(), 'rb');
        fgetcsv($stream, escape: '');
        $totalRows = 0;
        while (($cells = fgetcsv($stream, escape: '')) !== false) {
            if (! ProcessInvitationImport::isBlankRow($cells)) {
                $totalRows++;
            }
        }
        fclose($stream);

        $import = InvitationImport::create([
            'imported_by' => $request->user()->id,
            'filename' => $file->getClientOriginalName(),
            'total_rows' => $totalRows,
        ]);

        Storage::disk(config('filesystems.private'))->putFileAs(
            'invitation-imports',
            $file,
            "{$import->id}.csv",
        );

        // Dispatching is what fails first when the queue broker is unreachable.
        // The file is already stored, so mark the import failed and say so
        // rather than throwing: the admin can re-upload once the queue is back.
        try {
            ProcessInvitationImport::dispatch($import);
        } catch (Throwable $e) {
            report($e);
            $import->update(['status' => InvitationImportStatus::Failed]);

            return redirect()
                ->route('admin.invitations.index')
                ->with('status', "Import of {$import->filename} could not be started because the queue is unavailable. Try again once it is back.");
        }

        return redirect()
            ->route('admin.invitations.index')
            ->with('status', "Import of {$import->filename} started.");
    }
}
