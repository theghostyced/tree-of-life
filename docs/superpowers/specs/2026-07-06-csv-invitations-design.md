# CSV Bulk Invitations — Design

**Date:** 2026-07-06
**Status:** Approved

## Goal

Let admins invite many people at once by uploading a CSV, alongside the existing
single-invite form. Admins can download a template, upload a filled file, and
watch the import run. Rows whose email already belongs to a user (or already has
an active invitation) are skipped — never errors — and the admin gets a summary
plus a per-row report of everything that was skipped or invalid.

## Approach

Native PHP CSV parsing (`fgetcsv`) — no new Composer dependency — with a small
`InvitationImport` model for tracking and a queued job for processing, sized for
imports of thousands of rows. Row creation reuses the existing
`App\Actions\CreateUserInvitation` action and `UserInvitationMail`, so single
and bulk invitations cannot drift apart.

Rejected: `maatwebsite/excel` (heavy PhpSpreadsheet dependency for a 3-column
CSV; CLAUDE.md forbids new dependencies the structure can support without);
synchronous processing with a row cap (ruled out by expected import size).

## Data

New table `invitation_imports`:

| column          | type                                            |
| --------------- | ----------------------------------------------- |
| `id`            | bigint PK                                       |
| `imported_by`   | FK → `users.id`                                 |
| `filename`      | string (original client filename, display only) |
| `status`        | string, backed enum `InvitationImportStatus`: `pending` / `processing` / `completed` / `failed` |
| `total_rows`    | unsigned int (data rows, excluding header)      |
| `invited_count` | unsigned int, default 0                         |
| `skipped_count` | unsigned int, default 0                         |
| `invalid_count` | unsigned int, default 0                         |
| `row_errors`    | JSON array of `{row, email, reason}`, capped at 1,000 entries |
| timestamps      |                                                 |

The uploaded file is stored on the private local disk at
`invitation-imports/{import-id}.csv` and deleted when the job reaches a
terminal state.

## CSV contract

- Template served at `GET /admin/invitations/import/template` as a streamed
  download `invitations-template.csv`:

  ```csv
  email,role,name
  amara@example.com,entrepreneur,Amara Okafor
  kwame@example.com,mentor,
  ```

- Header row is required and must contain exactly `email,role,name`
  (case-insensitive, order-sensitive). Anything else rejects the upload with a
  validation error naming the expected header.
- `role` must be one of `UserRole::invitable()` (entrepreneur, mentor, admin),
  case-insensitive. `name` is optional.
- Upload limits: mime `csv/plain text`, max 10 MB.

## Processing rules (per row, in order)

1. Blank line → ignored silently (not counted).
2. Malformed email or unknown role → **invalid**, recorded
   (`row N: invalid email` / `row N: unknown role "x"`).
3. Email already in `users` → **skipped**, recorded (`already a user`).
4. Email has an active invitation for the same role (not accepted, not revoked,
   not expired) → **skipped**, recorded (`already invited`).
5. Email seen earlier in this same file → **skipped**, recorded
   (`duplicate row in file`).
6. Otherwise → **invited**: `CreateUserInvitation` runs and
   `UserInvitationMail` is queued, exactly like a single invite.

Skips and invalids never stop the import; the job always proceeds to the next
row. Counts (`invited/skipped/invalid`) persist every 25 rows so the UI can
poll progress. An unexpected exception marks the import `failed`, recording the
row number it died on; rows already invited stay invited.

## HTTP surface

All under the existing `role:admin` group:

- `GET  /admin/invitations/import/template` — streamed template CSV.
- `POST /admin/invitations/import` — multipart upload; validates file, counts
  rows, creates `InvitationImport`, stores file, dispatches
  `ProcessInvitationImport`; redirects back with a flash.
- The invitations index (`InvitationController@index`) additionally shares
  `activeImport` — the latest import (id, filename, status, counts,
  total_rows, row_errors) or null.

Authorization: same `create` gate on `UserInvitation` as single invites.

## UI (invitations page)

- The invite slide-over gains two tabs: **Send directly** (existing form,
  unchanged) and **Upload CSV** — a template download link, file input
  (drop-zone styling per the design tokens), a short plain-language note on the
  skip rules, and an Upload button.
- While an import is `pending/processing`, the index shows a progress strip:
  filename, `n of total rows`, live via Inertia partial-reload polling (~2 s),
  stopping automatically at a terminal status.
- On `completed`: summary line — `Invited 240 · Skipped 12 · Invalid 3` — with
  an expandable list of skipped/invalid rows (`row, email, reason`), styled as
  a quiet table. On `failed`: error-toned strip naming the failing row.
- The latest import's outcome stays visible until a new upload replaces it or
  the admin dismisses it (dismissal is client-side only).
- New invitations appear in the invitations table as rows process (polling
  refreshes the list alongside the import).

## Error handling

- Upload-time: standard Laravel validation errors in the slide-over (wrong
  mime, too big, wrong header, empty file).
- Run-time: per-row problems are data (`row_errors`), never exceptions;
  unexpected exceptions → import `failed` + log entry.
- `row_errors` caps at 1,000 entries; if exceeded, the report notes
  `…and N more` (count carried by `invalid_count`/`skipped_count`, which keep
  incrementing past the cap).

## Testing

Feature tests (Pest, matching existing conventions):

- Template downloads with the exact header and example rows.
- Upload validation: non-CSV mime, >10 MB, wrong header, empty file.
- Job outcomes: invited / skipped-existing-user / skipped-active-invitation /
  invalid-email / invalid-role / in-file-duplicate, with exact counts and
  `row_errors` contents.
- Mail queued only for invited rows.
- File deleted after completion; import marked `failed` on a poisoned file.
- Non-admins cannot reach template, upload, or import state.

Browser verification: upload a real mixed CSV, watch progress, confirm the
report and the new rows in the table.
