# SportsMIS API (`/api`)

JSON API for the mobile app. **Scaffold only** for now — the full endpoint set
lands with the mobile-app phase.

## Principle: one domain layer

The API reuses the web app's `app/models` and `app/services` (see
`public/index.php`'s autoloader). **Do not re-implement business logic here.**
Results, medal tally and scoring must be computed by the same Services the web
app uses (`Services\TrackMedal`, `Models\ScoreEntry`, …) so web and mobile can
never disagree.

- `public/` — web-facing docroot for `api.sportsmis.com`. Front controller +
  `.htaccess`.
- `src/` — API-only classes (namespace `Api\`): controllers, request auth,
  serializers. No domain logic.

## Deploy

Point the `api.sportsmis.com` subdomain's document root at this repo's
`api/public`. (Alternatively serve it under `app.sportsmis.com/api` — but a
dedicated subdomain keeps CORS and tokens clean.)

## Auth

The API will authenticate with bearer tokens issued against the unified
`users` account (see the account-merge work), NOT session cookies.
