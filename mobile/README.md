# SportsMIS Mobile App (`/mobile`)

Reserved for the mobile app (React Native or Flutter), planned as the next
enhancement after the upcoming event. Kept in this monorepo so API + app
changes ship atomically.

The app talks to the JSON API in `/api` (see `../api/README.md`) using bearer
tokens issued against the unified `users` account — never the web session
cookie.

When the app is scaffolded, keep it self-contained here (its own build,
lockfile and CI). If its release cadence later fights the web deploys, this
folder lifts cleanly into its own repo.
