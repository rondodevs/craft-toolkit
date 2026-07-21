# Craft Toolkit

Internal Craft CMS plugin bundling a set of control panel utilities:

- **Site Config** — CP-editable overrides for site name/URL on top of env defaults, exposed via GraphQL.
- **KV Cache** — settings and controls to purge an external edge/KV cache (tags or full flush) when entries/assets change.
- **Static Labels** — CP-editable label overrides per site, exposed via GraphQL.
- **Average Color** — enable/disable and per-volume control for automatic average-color calculation on image assets, with a check of which volumes already have an `averageColor` field in their field layout. The `media` volume is selected by default when present.
- **Org Schema** — CP panel with per-site defaults for the schema.org identity (Organization, LocalBusiness, MedicalClinic/MedicalBusiness, Person, ...: name, logo, one or more addresses, `sameAs` social profiles, contact info), exposed via GraphQL for Nuxt SEO's [default schema.org identity](https://nuxtseo.com/docs/schema-org/guides/default-schema-org). Plus an **Org Schema** field type to attach one or more arbitrary schema.org pieces (Article, Product, Event, MedicalClinic, FAQPage, ...) to individual entries, each with type-specific basic fields (e.g. NAP + geo-coordinates + opening hours for locations) and a JSON escape hatch for anything else.
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

## Org Schema (schema.org / Nuxt SEO)

Two pieces work together to feed [Nuxt SEO's schema-org module](https://nuxtseo.com/docs/schema-org/getting-started/introduction):

- **Toolkit → Org Schema** (CP panel): per-site defaults for the site's schema.org
  identity — `Organization`, `Corporation`, `NGO`, `LocalBusiness`,
  `ProfessionalService`, `MedicalBusiness`, `MedicalClinic`,
  `EducationalOrganization` or `Person` — following the shape expected by Nuxt
  SEO's [default schema.org identity](https://nuxtseo.com/docs/schema-org/guides/default-schema-org)
  (`name`, `legalName`, `url`, `logoUrl`, `description`, `email`, `telephone`,
  `sameAs`, `addresses`). `logoUrl` is picked via a native Craft asset selector
  (upload or choose an existing image) rather than typed as a raw URL, and
  resolved to its URL for output. Fields shown in the CP adjust to what each
  type actually defines per schema.org (verified against schema.org directly):

  | Field | Shown for |
  | --- | --- |
  | `legalName`, `logoUrl` | `Organization`, `Corporation`, `NGO`, `LocalBusiness`, `ProfessionalService`, `MedicalBusiness`, `MedicalClinic`, `EducationalOrganization` — **not** `Person` (schema.org's `Person` has neither property) |
  | `priceRange` | `LocalBusiness`, `ProfessionalService`, `MedicalBusiness`, `MedicalClinic` — defined directly on `LocalBusiness` |
  | `openingHours` | the same `LocalBusiness` types, **plus** `EducationalOrganization` — `EducationalOrganization` inherits `openingHours` from `CivicStructure` (`Thing > Place > CivicStructure > EducationalOrganization`), but does *not* inherit `priceRange` from anywhere in its hierarchy |

  `addresses` supports **multiple locations** — for a multi-clinic/multi-branch
  business, add one per physical location. Since `priceRange` and
  `openingHours` are properties of a physical *place* in schema.org (not of
  the identity as a whole), they live on **each address** rather than on the
  identity, and each location can have its own value — so two locations of
  the same `MedicalClinic` can have different hours. `openingHours` is
  entered via a day-picker + open/close time per schedule and compiled into
  an array of schema.org strings, e.g.
  `["Mo-Fr 09:00-18:00", "Sa 10:00-14:00"]`. Exposed via GraphQL:

  ```graphql
  query OrgSchema($site: String!) {
    orgSchema(site: $site) {
      type
      name
      legalName
      url
      logoUrl
      sameAs
      addresses {
        streetAddress
        addressLocality
        addressRegion
        postalCode
        addressCountry
        priceRange
        openingHours
      }
    }
  }
  ```

  For a multi-location business (e.g. a medical group with several clinics),
  use `MedicalBusiness` here for the sitewide identity with one address (and
  its own price range/hours) per clinic. If a location also needs its own
  dedicated page, model it as its own entry instead and put its NAP/geo/hours
  on that entry via the **Org Schema field** below with `@type` set to
  `MedicalClinic` (or `Dentist`/`Physician`/`Hospital`/`Pharmacy`) — its basic
  fields cover name, telephone, street address/locality/region/postal
  code/country (merged into a nested `address`), latitude/longitude (merged
  into a nested `geo`), `openingHours` and `priceRange`.

- **Org Schema field**: a field type you can add to any field layout to attach one
  or more arbitrary schema.org pieces (`Article`, `Product`, `Event`, `FAQPage`,
  `HowTo`, ...) to a specific entry/element. Each row is a `@type` plus a JSON
  object of additional properties; GraphQL exposes each piece pre-merged and
  ready to parse:

  ```graphql
  {
    entry(slug: "my-entry") {
      ... on blog_default_Entry {
        orgSchema {
          json
        }
      }
    }
  }
  ```

In Nuxt, combine both with `useSchemaOrg()` — the site-wide identity via
`defineOrganization()`/`definePerson()`, and the entry-level pieces passed
through as raw schema.org objects:

```ts
const { data } = await useAsyncQuery(OrgSchemaQuery, { site: 'default' })
const { data: entry } = await useAsyncQuery(EntryQuery, { slug })

useSchemaOrg([
  defineOrganization({
    name: data.value.orgSchema.name,
    logo: data.value.orgSchema.logoUrl,
    sameAs: data.value.orgSchema.sameAs,
    address: data.value.orgSchema.addresses, // one, or an array for multi-location businesses
  }),
  // Each location entry's own Org Schema field (e.g. @type: MedicalClinic) already
  // contains a ready-to-use JSON-LD piece with NAP, geo, openingHours, priceRange...
  ...(entry.value.orgSchema ?? []).map((piece) => JSON.parse(piece.json)),
])
```

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
