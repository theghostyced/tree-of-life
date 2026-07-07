# Mentor ⇄ Entrepreneur Chat — Design Spec

**Date:** 2026-07-07
**Status:** Approved design; pending implementation plan

## 1. Purpose

Real-time 1:1 messaging between paired mentors and entrepreneurs in a LinkedIn-style
two-pane interface (conversation list + active thread), with the ability to jump from a
conversation into scheduling a call. Chat rides on the existing `Pairing` relationship:
one conversation exists per pairing.

## 2. Scope

### In (v1)
- Conversation list of the user's pairings — avatar, name, last-message preview,
  timestamp, unread badge, online presence dot.
- Real-time thread: text messages sent and received instantly.
- Unread counts: per-conversation and a **global nav badge** that updates live even when
  the relevant thread isn't open.
- **Read receipts** ("Seen").
- **Typing indicators** ("… is typing").
- **Online presence** ("Active now").
- **"Schedule a call"** CTA in the thread header that navigates to the existing booking
  flow, scoped to the pairing.
- **`system` messages** for scheduling events (e.g. "Call scheduled for Thu 3:00 PM").

### Out (deferred to v2+)
- Attachments (images/files) — requires object storage, signed uploads, MIME/size limits,
  virus scanning.
- Inline slide-over scheduler inside the thread (v1 navigates to the existing flow).
- Message search.
- Group conversations.

### Non-goals
- Admin/employee messaging. Chat exists only between the two members of a mentor↔entrepreneur pairing.

## 3. Architecture

- **Transport:** Laravel Reverb (self-hosted WebSocket server) + `laravel-echo` on the client.
- **Broadcasting:** domain events implement `ShouldBroadcast` and broadcast **via the queue**
  (Redis). The HTTP request that sends a message persists it and returns immediately; a
  queue worker fans the broadcast out to Reverb. No user request blocks on WebSocket delivery.
- **Scaling:** `REVERB_SCALING_ENABLED=true` (Redis pub/sub) so multiple Reverb nodes can run
  behind a load balancer and share channel state.
- **Shell vs. realtime split:** Inertia renders the Messages page server-side (conversation
  list + the first page of the open thread). Echo delivers live message / read / typing /
  presence events thereafter. A thin JSON endpoint serves older-message (cursor) pagination.

```
Browser (Svelte + Echo) ──ws──> Reverb <──redis pub/sub──> Reverb (n nodes)
      │  ^                                   ^
      │  │ Inertia (page shell, list)        │ queued broadcast
      v  │                                    │
   Laravel HTTP  ──persist──> DB     Queue worker ──MessageSent──> Reverb
```

## 4. Data model

### `conversations`
| column | type | notes |
|---|---|---|
| `id` | bigint PK | |
| `pairing_id` | bigint FK → `pairings`, **unique** | one conversation per pairing |
| `last_message_at` | timestamp, nullable, indexed | denormalized for list ordering |
| `last_message_preview` | string(180), nullable | denormalized preview text |
| `last_message_sender_id` | bigint FK → `users`, nullable | for "You: …" prefix |
| `created_at` / `updated_at` | timestamps | |

Ordering the list is one indexed query on `last_message_at`; no join to `messages` and no N+1.

### `conversation_participants`
| column | type | notes |
|---|---|---|
| `id` | bigint PK | |
| `conversation_id` | bigint FK, indexed | |
| `user_id` | bigint FK, indexed | |
| `last_read_at` | timestamp, nullable | drives "Seen" + unread count |
| `last_read_message_id` | bigint, nullable | precise read marker |
| unique | (`conversation_id`, `user_id`) | exactly two rows per conversation |

Two rows per conversation (the pairing's mentor + entrepreneur). Kept as a table rather than
two columns on `conversations` so read state is symmetric and future-proof.

### `messages`
| column | type | notes |
|---|---|---|
| `id` | bigint PK | cursor for pagination |
| `conversation_id` | bigint FK | |
| `sender_user_id` | bigint FK → `users` | null for pure system messages allowed |
| `type` | enum `text` \| `system` | `system` renders as an inline card |
| `body` | text | |
| `created_at` | timestamp | |
| index | (`conversation_id`, `id`) | cursor pagination + newest-first scans |

### Lifecycle
- A `Conversation` (+ two `ConversationParticipant` rows) is **created when a `Pairing` is
  created/accepted** (hook in the pairing creation path) and **backfilled** for existing
  pairings via a one-off migration/command.
- When a pairing ends (`pairings.ended_at`), its conversation becomes **read-only**
  (composer disabled, history preserved).

### Eloquent
- `Conversation` — `belongsTo(Pairing)`, `hasMany(Message)`, `hasMany(ConversationParticipant)`;
  helpers `participantFor(User)`, `otherParticipant(User)`, `unreadCountFor(User)`.
- `Message` — `belongsTo(Conversation)`, `belongsTo(User, 'sender_user_id')`.
- `User` — `hasMany(ConversationParticipant)` and a `conversations()` accessor through pairings.

## 5. Realtime channels & events

### Channels
- **`conversation.{id}`** — private. New messages, read receipts. Authorized to the two
  participant `user_id`s only.
- **`user.{id}`** — private. Per-user firehose for the **global unread badge** and list
  reorder, so a user is notified of new messages in conversations they don't currently have open.
- **`presence-online`** — presence channel; `.here()/.joining()/.leaving()` drive "Active now".

### Events (all `ShouldBroadcast`, queued)
- **`MessageSent`** — broadcasts on `conversation.{id}` **and** `user.{recipientId}`. Payload:
  message id, conversation id, sender, body, type, created_at, and the recipient's new unread
  count. The conversation listener appends the bubble; the user listener updates the badge and
  moves the conversation to the top of the list.
- **`MessageRead`** — broadcasts on `conversation.{id}`. Payload: reader id, `last_read_at`.
  The sender's thread updates its "Seen" marker.

### Client-only signals (no DB)
- **Typing:** `Echo.private('conversation.{id}').whisper('typing', {...})`, throttled, auto-expiring ~3s.
- **Presence:** `Echo.join('online')` membership; a conversation shows a dot when the other
  participant is in the presence set.

### Reconciliation
If a user is actively viewing conversation X when `MessageSent` arrives on `user.{id}`, the
badge is **not** incremented — the thread marks the message read immediately and emits `MessageRead`.

## 6. HTTP endpoints

Role-aware but backed by a single controller set; the current user's role only selects the layout.

| method | path | purpose |
|---|---|---|
| GET | `/{role}/messages` | Inertia page: conversation list + (optionally) first thread |
| GET | `/{role}/messages/{conversation}` | Inertia page with that thread selected/deep-linked |
| GET | `/conversations/{conversation}/messages?before={id}` | JSON: older messages (cursor, 30/page) |
| POST | `/conversations/{conversation}/messages` | send a message |
| POST | `/conversations/{conversation}/read` | mark read up to latest (updates `last_read_at`) |

`{role}` ∈ `entrepreneur`, `mentor`. Send/read/pagination endpoints are role-agnostic and
guarded by policy.

## 7. Authorization

- **Channel auth** (`routes/channels.php`): `conversation.{id}` authorizes only if the user is one
  of the conversation's two participants; `user.{id}` only if `id === auth id`.
- **Policy** (`ConversationPolicy`): `view`, `sendMessage`, `markRead` all require participant
  membership **and** an active (non-ended) pairing for `sendMessage`.
- Input: message `body` required, trimmed, max length (e.g. 5,000 chars); rate-limited per user.

## 8. Frontend (Inertia + Svelte)

- **Routes/pages:** `resources/js/pages/messages/Index.svelte` rendered inside the role layout
  (`EntrepreneurLayout` / `MentorLayout`). Two-pane: list left, thread right; on mobile the list
  and thread are separate views (list → tap → thread, back button).
- **Components:**
  - `ConversationList` + `ConversationListItem` (avatar, name, preview, relative time, unread
    badge, presence dot).
  - `Thread` — header (name, presence, **"Schedule a call"** button), message list with day
    separators, `MessageBubble` (own vs. other, `system` card variant, "Seen" marker), and a
    `Composer` (textarea, send, typing whisper).
- **Echo bootstrap:** add `laravel-echo` + Reverb config to `resources/js/app.ts` (keys from
  `VITE_REVERB_*`). A small `useEcho`-style module exposes `Echo` and subscribes to `user.{id}`
  globally (for the badge) and to the open `conversation.{id}` while a thread is mounted.
- **Nav:** add a **"Messages"** item with a live unread badge to both `EntrepreneurLayout` and
  `MentorLayout` (fed by the `user.{id}` channel).
- **Optimistic send:** the composer appends the bubble immediately with a pending state; the
  `MessageSent`/HTTP response reconciles (id, timestamp) or shows a retry on failure.
- **Theme:** dark sage tokens; content capped per the project **max-w-7xl** page-width rule
  (the two-pane surface fills that width).

## 9. Booking integration

- The thread header shows **"Schedule a call"**. For an **entrepreneur** it navigates
  (`Inertia.visit`) to their existing Meetings/booking surface, pre-filtered to this pairing's
  mentor and that mentor's `MentorAvailabilitySlot`s → `BookMeeting`. For a **mentor** it points
  to the availability/propose surface for that pairing.
- When a meeting is booked/confirmed for the pairing, emit a **`system` message** into the
  conversation ("📅 Call scheduled for …") so both sides see it inline. This reuses the existing
  meeting events; no new booking logic.
- v2: replace the navigation with an inline slide-over scheduler that books without leaving the thread.

## 10. Scale & performance

- Broadcasts run on the **Redis queue**; the send request never waits on WebSocket delivery.
- **Reverb scales horizontally** via Redis pub/sub (`REVERB_SCALING_ENABLED`).
- **Cursor pagination** for messages (`WHERE conversation_id = ? AND id < ? ORDER BY id DESC LIMIT 30`)
  on the `(conversation_id, id)` index — no offset scans.
- Denormalized `last_message_*` on `conversations` → the list is a single indexed query.
- Unread count via `last_read_at` + indexed count; if the global-badge query ever gets hot,
  promote to a maintained `unread_count` column on `conversation_participants` updated on send/read.
- Presence and typing carry **no DB cost** (live connection state / client whispers).

## 11. Testing

- **Backend (Pest feature tests):**
  - Sending persists a message, updates `conversations.last_message_*`, and dispatches
    `MessageSent` (`Event::fake` / `Broadcast::assert…`) on the right channels.
  - Read endpoint updates `last_read_at` and broadcasts `MessageRead`.
  - Channel authorization: a non-participant is rejected from `conversation.{id}`.
  - Policy: cannot send on an ended pairing; cannot view others' conversations.
  - Cursor pagination returns correct pages/order; unread count is accurate.
  - Conversation auto-created on pairing creation; backfill command covers existing pairings.
- **Frontend:** component tests for optimistic send + realtime append/read; extend the existing
  browser E2E journey suite with a two-user "send → seen → schedule" flow.

## 12. Rollout / infra

- Add `laravel/reverb`; `php artisan reverb:install`. Env: `BROADCAST_CONNECTION=reverb`,
  `REVERB_*`, `REVERB_SCALING_ENABLED=true`, Redis for queue + scaling.
- Run **three processes** in prod: web (php-fpm), **queue worker**, and **Reverb**
  (`php artisan reverb:start`), plus Redis. Document in the deploy config.
- Migrations: `conversations`, `conversation_participants`, `messages`, + backfill.

## 13. Open questions / future

- Presence granularity: a single global `online` channel (simple) vs. last-seen timestamps
  ("Active 5m ago"). v1 uses the global channel; last-seen is a small follow-up.
- Notifications when offline (email/push digest of unread) — out of scope for v1, natural v2.
- Retention/archival policy for messages on very old ended pairings.
