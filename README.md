# Craft Toolkit

Internal Craft CMS plugin bundling a set of control panel utilities:

- **Site Config** — CP-editable overrides for site name/URL on top of env defaults, exposed via GraphQL.
- **KV Cache** — settings and controls to purge an external edge/KV cache (tags or full flush) when entries/assets change.
- **Static Labels** — CP-editable label overrides per site, exposed via GraphQL.
- **Average Color** — enable/disable and per-volume control for automatic average-color calculation on image assets, with a check of which volumes already have an `averageColor` field in their field layout. The `media` volume is selected by default when present.
- Misc handlers: a CP alert when the default GraphQL route is missing, and a site-request redirect controller.

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later

## Getting started (development)

Requires [DDEV](https://ddev.com) and Docker/OrbStack running locally.

1. Clone the repo and start DDEV (spins up `web`/`db` containers, no host PHP needed):

   ```bash
   git clone git@github.com:rondodevs/craft-toolkit.git
   cd craft-toolkit
   ddev start
   ```

2. Install the plugin's own dependencies, so your IDE resolves `craft\...` classes:

   ```bash
   ddev composer install
   ```

3. Install the playground's dependencies (symlinks this plugin in via the `path`
   repository already committed in `playground/composer.json`):

   ```bash
   ddev exec --dir /var/www/html/playground composer install
   ```

4. Create the playground's local env file (DB credentials are injected
   automatically by DDEV; leave `CRAFT_APP_ID`/`CRAFT_SECURITY_KEY` blank,
   `craft install` fills them in):

   ```bash
   cp playground/.env.example.dev playground/.env
   ```

5. Install Craft — since `playground/config/project/project.yaml` is already
   committed, this provisions the DB schema and applies that project config
   automatically:

   ```bash
   ddev exec --dir /var/www/html/playground php craft install \
     --interactive=0 \
     --username=admin \
     --email=you@example.com \
     --password=changeme123 \
     --site-name="Toolkit Playground" \
     --site-url="https://craft-toolkit-playground.ddev.site" \
     --language=en-US

   ddev exec --dir /var/www/html/playground php craft plugin/install toolkit
   ```

6. Open <https://craft-toolkit-playground.ddev.site/admin/login> and log in.

From here, edit anything under `src/` and reload the CP — changes are live
via the symlinked plugin, no reinstall step. See [DDEV playground](#ddev-playground)
below for how the playground itself was scaffolded and how to regenerate it
from scratch if it ever needs a reset.

## Installation

### From Packagist (once published)

```bash
composer require rondodevs/craft-toolkit
```

Then install the plugin in the Craft control panel, or via:

```bash
php craft plugin/install toolkit
```

### Local development (path repository)

While developing this plugin alongside a Craft project, add a `path` repository
to the consuming project's `composer.json` so Composer symlinks it instead of
downloading a tagged release:

```jsonc
{
    "repositories": [
        {
            "type": "path",
            "url": "../../studio-fes/craft-toolkit",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "rondodevs/craft-toolkit": "*"
    }
}
```

Adjust the `url` to the relative (or absolute) path to this directory, then run
`composer update rondodevs/craft-toolkit`. Changes made here are picked up
immediately in the consuming project without republishing.

### DDEV playground

For interactive testing (CP, GraphQL, utilities) this repo ships a DDEV
project rooted at the repo root with its docroot at `playground/web`. The
`playground/` directory holds a full Craft install that requires this plugin
via a local `path` repository (symlinked), so edits under `src/` are picked
up immediately — no reinstall needed. It's gitignored; regenerate it anytime
with:

```bash
ddev start
rm -rf playground   # only if it already exists and you want a clean slate
ddev exec composer create-project craftcms/craft playground --no-interaction
```

Then edit `playground/composer.json`:

```jsonc
{
    "repositories": [
        { "type": "path", "url": "..", "options": { "symlink": true } }
    ],
    "require": {
        "rondodevs/craft-toolkit": "*"
    }
}
```

```bash
ddev exec --dir /var/www/html/playground composer update --no-interaction

ddev exec --dir /var/www/html/playground php craft install \
  --interactive=0 \
  --username=admin \
  --email=you@example.com \
  --password=changeme123 \
  --site-name="Toolkit Playground" \
  --site-url="https://craft-toolkit-playground.ddev.site" \
  --language=en-US

ddev exec --dir /var/www/html/playground php craft plugin/install toolkit
```

Then visit `https://craft-toolkit-playground.ddev.site/admin/login`.

## Publishing to Packagist

1. Push this repository to GitHub (e.g. `github.com/rondodevs/craft-toolkit`).
2. Tag a release, e.g. `git tag v1.0.0 && git push --tags`.
3. On [packagist.org](https://packagist.org), submit the GitHub repository URL.
4. Enable the GitHub Packagist webhook (Packagist "Settings" tab -> instructions,
   or add it manually under the GitHub repo's Settings -> Webhooks) so new tags
   are picked up automatically.
5. From then on, `composer require rondodevs/craft-toolkit` resolves the
   package for anyone with access to the repository.

## License

MIT
