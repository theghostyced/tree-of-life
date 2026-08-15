# Tree Of Life Fund

Laravel 13 + Inertia + Svelte. Mentors, entrepreneurs, funding programs, and
realtime chat.

## Requirements

| Tool | Version used |
| --- | --- |
| PHP | 8.4 |
| Node | 24.x |
| Redis | running on `127.0.0.1:6379` |
| Database | SQLite (`DB_CONNECTION=sqlite`, no server needed) |

Redis is **not optional** — it backs both the queue (`QUEUE_CONNECTION=redis`)
and Reverb's scaling layer (`REVERB_SCALING_ENABLED=true`).

## First-time setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

## Running the app

```bash
composer dev
```

That single command starts **all five** processes the app needs:

| Process | Command | Why it's needed |
| --- | --- | --- |
| `server` | `php artisan serve --host=localhost` | Serves the app on http://localhost:8000 |
| `vite` | `npm run dev` | Frontend assets + HMR |
| `queue` | `php artisan queue:listen --tries=1 --timeout=0` | Sends mail, **broadcasts chat messages** |
| `reverb` | `php artisan reverb:start` | Websocket server for realtime |
| `logs` | `php artisan pail --timeout=0` | Live log tail |

Stop everything with `Ctrl+C`.

To run them individually instead, use the commands from the table above in
separate terminals. `php artisan dev:list` shows the registered processes.

## Chat and realtime

Chat needs **three** things running: `reverb`, the **queue worker**, and Redis.
`composer dev` covers the first two. If any is missing, messages still save to
the database and the sender sees them — but they never reach the other person.

Chat events (`MessageSent`, `MessageRead`) are `ShouldBroadcast`, so the actual
publish to Reverb happens **inside the queue worker**, not in the web request.
A broadcast failure therefore shows up in `failed_jobs`, never in the HTTP
response. When chat looks broken, check there first:

```bash
php artisan queue:failed
```

### Ports

Reverb runs on **8081**, not the Laravel default of 8080.

Port 8080 is used by the Adminer container from the sibling `undp-adnois`
project. Because Docker binds IPv6 and Reverb binds IPv4, both can hold "port
8080" simultaneously with no error — and since macOS resolves `localhost` to
`::1` first, every broadcast silently went to Adminer and failed with
`Pusher\PusherException: Data decoding error`.

For the same reason, `REVERB_HOST` is `127.0.0.1` rather than `localhost`. Keep
it that way: `localhost` is ambiguous across IPv4/IPv6, `127.0.0.1` is not.

```env
REVERB_HOST=127.0.0.1
REVERB_PORT=8081
REVERB_SERVER_PORT=8081
```

If 8081 is ever taken, change `REVERB_PORT` and `REVERB_SERVER_PORT` together —
`REVERB_SERVER_PORT` is what Reverb binds, `REVERB_PORT` is what clients dial.

## Email

Local mail is caught by [Mailpit](https://mailpit.axllent.org/) instead of being
delivered. Start it once:

```bash
docker run -d --name mailpit --restart unless-stopped \
  -p 1025:1025 -p 8025:8025 axllent/mailpit
```

Read the caught mail at **http://localhost:8025**.

The relevant `.env` values:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

Set `MAIL_MAILER=log` instead to write rendered emails to
`storage/logs/laravel.log`. Production uses `MAIL_MAILER=resend`.

Invitation emails are queued (`Mail::to(...)->queue(...)`), so they need the
queue worker running too.

## After changing `.env`

Long-running processes read config **once at boot** and cache it in memory.
Editing `.env` does nothing to an already-running worker, Reverb, or Vite — this
is the most common cause of "I changed the config and nothing happened".

```bash
php artisan config:clear
```

Then restart `composer dev`. `VITE_*` values are compiled into the frontend
bundle, so Vite must restart too (and `npm run build` must be re-run if you are
serving built assets rather than the dev server).

## Troubleshooting

### `Failed to listen on "tcp://0.0.0.0:8081": Address already in use`

An earlier stack is still running. `composer dev` runs the five processes under
`concurrently`, so when one exits non-zero it SIGTERMs the rest — the whole
thing tears down and only Reverb's error is the real cause.

Find and stop whatever holds the port:

```bash
lsof -iTCP:8081 -sTCP:LISTEN -P -n     # and :8000 for the web server
kill <pid>
```

If a previous `composer dev` was backgrounded, kill the `concurrently` parent
rather than the children — otherwise it restarts them:

```bash
pkill -f "npm exec concurrently"
```

### Chat saves but never arrives

Check, in order: Reverb running, queue worker running, Redis up, then
`php artisan queue:failed`. A `Pusher\PusherException: Data decoding error`
there means broadcasts are reaching something that isn't Reverb — see the
Ports section above.

## Useful checks

```bash
php artisan test
npm run test
npm run build

php artisan queue:failed        # broadcast + mail failures land here
php artisan dev:list            # processes started by "composer dev"
```
