<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvitationImportRequest;
use App\Jobs\ProcessInvitationImport;
use App\Models\InvitationImport;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvitationImportController extends Controller
{
    public function template(): StreamedResponse
    {
        Gate::authorize('create', UserInvitation::class);

        $content = "email,role,name\n"
            ."amara@example.com,entrepreneur,Amara Okafor\n"
            ."kwame@example.com,mentor,\n";

        return response()->streamDownload(
            fn () => print ($content),
            'invitations-template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    public function store(StoreInvitationImportRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        // Count data rows (non-blank lines below the header) for progress totals.
        $stream = fopen($file->getRealPath(), 'rb');
        fgetcsv($stream);
        $totalRows = 0;
        while (($cells = fgetcsv($stream)) !== false) {
            if ($cells !== [null] && trim((string) ($cells[0] ?? '')) !== '') {
                $totalRows++;
            }
        }
        fclose($stream);

        $import = InvitationImport::create([
            'imported_by' => $request->user()->id,
            'filename' => $file->getClientOriginalName(),
            'total_rows' => $totalRows,
        ]);

        Storage::disk('local')->putFileAs(
            'invitation-imports',
            $file,
            "{$import->id}.csv",
        );

        ProcessInvitationImport::dispatch($import);

        return redirect()
            ->route('admin.invitations.index')
            ->with('status', "Import of {$import->filename} started.");
    }
}
