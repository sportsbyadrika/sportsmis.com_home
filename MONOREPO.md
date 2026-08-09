# SportsMIS monorepo layout

One repository holds the public site, the web app, the mobile API and (later)
the mobile app. They **share one domain layer** (`app/models`, `app/services`)
so business logic exists in exactly one place.

```
/
  site/            Public landing page  ->  sportsmis.com
    index.php        the landing page (move your marketing site here)
    assets/          landing images / css
  app/             Existing web app     ->  app.sportsmis.com
    public/          <-- docroot for app.sportsmis.com (front controller)
    core/ models/ services/ controllers/ views/ config/   the domain + web layer
  api/             JSON API for mobile  ->  api.sportsmis.com  (scaffold)
    public/          <-- docroot for api.sportsmis.com
    src/             Api\ classes (no domain logic — calls app/ services)
  mobile/          Mobile app (reserved, next phase)
  vendor/          Composer libraries (shared)
  database/ cron/ docs/ storage/
```

## Subdomain -> document-root mapping (cPanel)

| Host | Document root (inside the deployed repo) |
|------|------------------------------------------|
| `sportsmis.com` (main) | `site/` |
| `app.sportsmis.com`    | `app/public/` |
| `api.sportsmis.com`    | `api/public/` |

The repo is git-deployed to `app.sportsmis.com` (`.cpanel.yml` copies the whole
tree there). To serve the landing page and API from the same deploy, set each
subdomain's **Document Root** in cPanel to the folder above — e.g. point
`sportsmis.com` at `.../app.sportsmis.com/site`. No file needs to move again;
only the docroot settings change.

> The only manual step to "bring the landing page into the repo" is pointing the
> `sportsmis.com` document root at `site/`. The page itself now lives at
> `site/index.php` and is version-controlled here.

## The one rule

Controllers (web **and** API) stay thin. All business logic lives in
`app/services` / `app/models` and is called from both. When you add the mobile
app, it talks to `/api`, which talks to those same services.
