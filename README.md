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
