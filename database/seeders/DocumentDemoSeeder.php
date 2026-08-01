<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo documents for local review of the admin document viewer.
 *
 * UserDocumentFactory records a path but never writes a file, so factory-made
 * documents fail on download. This seeder writes a real, openable PDF for every
 * row it touches, and repairs any pre-existing row whose file is missing, so
 * every Download link on the admin user page actually resolves.
 *
 * Local demo data only. Run explicitly:
 *   php artisan db:seed --class=DocumentDemoSeeder
 */
class DocumentDemoSeeder extends Seeder
{
    private const DISK = 'local';

    public function run(): void
    {
        $users = User::query()
            ->whereIn('role', [UserRole::Entrepreneur->value, UserRole::Mentor->value])
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $types = DocumentType::requiredFor($user->role);

            // One retired type per entrepreneur so the "No longer required"
            // row on the admin page has something to render.
            if ($user->role === UserRole::Entrepreneur) {
                $types[] = DocumentType::BusinessCertificate;
            }

            foreach ($types as $type) {
                $this->attach($user, $type);
            }
        }

        $repaired = $this->repairMissingFiles();

        $this->command?->info(sprintf(
            'Documents ready for %d users: %d rows, %d pre-existing files repaired.',
            $users->count(),
            UserDocument::count(),
            $repaired,
        ));
    }

    /** Write a real file and point a document row at it. */
    private function attach(User $user, DocumentType $type): void
    {
        $path = "user-documents/{$user->id}/{$type->value}.pdf";
        $contents = $this->pdf($type->label(), "{$user->name} ({$user->email})");

        Storage::disk(self::DISK)->put($path, $contents);

        UserDocument::updateOrCreate(
            ['user_id' => $user->id, 'document_type' => $type],
            [
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => Str::slug($type->label()).'.pdf',
                'mime_type' => 'application/pdf',
                'size' => strlen($contents),
                'uploaded_at' => now(),
            ],
        );
    }

    /**
     * Older factory rows point at paths that were never written. Give each one
     * a real file at the path it already records, rather than moving it.
     */
    private function repairMissingFiles(): int
    {
        $repaired = 0;

        foreach (UserDocument::with('user')->get() as $document) {
            if (Storage::disk($document->disk)->exists($document->path)) {
                continue;
            }

            Storage::disk($document->disk)->put($document->path, $this->pdf(
                $document->document_type->label(),
                $document->user?->name ?? 'Unknown user',
            ));

            $repaired++;
        }

        return $repaired;
    }

    /**
     * A minimal but valid single-page PDF carrying the document label and the
     * owner's name, so an opened file is identifiable. Object offsets are
     * computed as the file is assembled, so the xref table is correct and
     * viewers open it without complaint.
     */
    private function pdf(string $title, string $subtitle): string
    {
        $text = 'BT /F1 15 Tf 40 92 Td ('.$this->escape($title).') Tj '
            .'/F1 10 Tf 0 -24 Td ('.$this->escape($subtitle).') Tj '
            .'0 -18 Td (Demo document for local testing.) Tj ET';

        $objects = [
            1 => '<</Type/Catalog/Pages 2 0 R>>',
            2 => '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            3 => '<</Type/Page/Parent 2 0 R/MediaBox[0 0 460 150]/Contents 4 0 R'
                .'/Resources<</Font<</F1 5 0 R>>>>>>',
            4 => '<</Length '.strlen($text).">>stream\n".$text."\nendstream",
            5 => '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number.' 0 obj'.$body."endobj\n";
        }

        $startXref = strlen($pdf);
        $size = count($objects) + 1;

        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        foreach (array_keys($objects) as $number) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }
        $pdf .= "trailer<</Size {$size}/Root 1 0 R>>\nstartxref\n{$startXref}\n%%EOF\n";

        return $pdf;
    }

    /** Escape the characters that are structural inside a PDF string literal. */
    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $value);
    }
}
