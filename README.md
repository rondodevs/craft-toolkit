# Craft Toolkit

Internal Craft CMS plugin bundling a set of control panel utilities:

- **Site Config** — CP-editable overrides for site name/URL on top of env defaults, exposed via GraphQL.
- **KV Cache** — settings and controls to purge an external edge/KV cache (tags or full flush) when entries/assets change.
- **Static Labels** — CP-editable label overrides per site, exposed via GraphQL.
- **Average Color** — enable/disable and per-volume control for automatic average-color calculation on image assets (and videos, from their Streaming Video poster), with a check of which volumes already have an `averageColor` field in their field layout. The `media` volume is selected by default when present.
- **Org Schema** — CP panel with per-site defaults for the schema.org identity (Organization, LocalBusiness, MedicalClinic/MedicalBusiness, Person, ...: name, logo, one or more addresses, `sameAs` social profiles, contact info), exposed via GraphQL for Nuxt SEO's [default schema.org identity](https://nuxtseo.com/docs/schema-org/guides/default-schema-org). The pieces of each entry (Article, Product, Event, MedicalClinic, FAQPage, ...) are in the SEO field's "Structured data".
- **SEO** — an SEO field type (title, description, social title/description/image, robots, canonical, AI summary, "exclude from AI" and schema.org structured data) that fills whatever the editor leaves empty from rules: an ordered list of fields to read the title, description and image from, then a Twig template. One global field; the rules can differ per entry type (in the field layout). Replaces Ether SEO, with a one-step migration that keeps the existing GraphQL queries working.
- **Redirects** — URL redirects for headless frontends, matched like Ether SEO's (paths, patterns, full URLs), with hits, CSV import/export and a URL tester; automatic ones when an entry's URI changes; Ether's imported when it's uninstalled. `@studio-fes/nuxt-craft-toolkit` applies them before rendering.
- **Unused Assets** — lists the assets of a volume (`media` by default) that no content uses and moves the ticked ones to the trash, restorable from there. Relations (drafts, revisions and trashed entries included), `{asset:ID}` reference tags in rich text and Link fields, user photos, the Org Schema logo and Streaming Video posters count as uses; uploads younger than 24 hours are left alone.
- **Streaming Video** — encodes every video to a progressive MP4 and, when switched on in its Streaming block, to HLS (adaptive streaming, up to 4K) in the queue, with a poster frame picker, and adds `hlsPlaylistUrl`, `mp4ProgressiveUrl` and `videoPoster` to assets in GraphQL. Off unless `CRAFT_STREAMING_VIDEO=true`, the same variable that installs FFmpeg in the craft-php worker. CP screen with status, qualities, a costs guide and help for Nuxt.
- **Email links** — Subject and Body, in a modal, on Craft's Link fields when the link is an email, so `url` is the full `mailto:` link. Saved in the field's URL suffix when it has "URL Suffix" under "Advanced fields", in the link's value otherwise (which counts toward the field's Max Length).
- Misc handlers: a CP alert when the default GraphQL route is missing, and a site-request redirect controller.

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later
- To work on the CP screens: Node 24 and pnpm (only to build them: release tags ship them built)

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

6. Build the CP screens (see [CP app](#cp-app)):

   ```bash
   pnpm install
   pnpm dev
   ```

7. Open <https://craft-toolkit-playground.ddev.site/admin/login> and log in.

From here, edit anything under `src/` or `app/` and reload the CP — changes are live
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

## Frontend settings (Toolkit → Frontend)

The frontend's runtime settings, so a frontend on `@studio-fes/layer-craft` 2.1 only needs `NUXT_PUBLIC_BACKEND_URL`:

| Setting | Default |
| --- | --- |
| Data cache | on, off in dev mode |
| Page cache, its max age and the path prefixes it skips | off, 1 day |
| CDN headers (`CDN-Cache-Control`, `Cache-Tag`) | off |
| Redirects, `llms.txt` | on |
| Internal URL | `$CRAFT_INTERNAL_URL`, else `http://<container hostname>:$CRAFT_INTERNAL_PORT` (the craft-php image sets the port) |

- The frontend reads them from `actions/toolkit/frontend-api/config` (public: switches, addresses and Craft's public key, nothing secret) at startup and every minute. Saving the screen tells it right away with a signed `POST /__toolkit/config` to the KV Cache Frontend URL; a cache switched back on is flushed first, since the frontend kept its old items while it was off.
- Internal URL: on the same Docker network the frontend sends its server-side requests there once it answers with this Craft's public key, and falls back to the public URL when it can't be reached (locally, standalone, after a redeploy with a new hostname). The container's hostname is its short id, which other containers on the network resolve.
- A variable set on the frontend (`NUXT_CACHE_ENABLED`, `NUXT_MULTI_CACHE_ROUTE`, `NUXT_CDN_CACHE_ENABLED`, `NUXT_ROUTE_CACHE_*`, `NUXT_CRAFT_TOOLKIT_REDIRECTS`, `NUXT_CRAFT_TOOLKIT_LLMS`, `NUXT_CRAFT_INTERNAL_URL`) still wins over its switch here; the frontend's `/__toolkit/health` lists where each setting comes from (`frontendConfig.sources`).

## KV Cache: what is purged

On every entry or asset save, delete, structure move, and when a future post or expiry date comes, the frontend gets a purge by tag with the tags its pages carry (`@studio-fes/layer-craft` 1.3 builds them from the GraphQL queries, so they match in every project; craft-toolkit 1.8):

| Saved | Tags |
| --- | --- |
| an entry | `entry:<id>`, the entries it's nested in (block → column → page) and related to, its assets; for a top-level entry also `archive:<section>`, `archive:type:<type>` and `archive:*` (every list of its section, of its type, and of any section) |
| an asset | `asset:<id>`, the entries using it and their owners, `archive:asset:<volume>`, `archive:asset:<volumeId>`, `archive:asset:*` |

- Entries are found whatever their status, so disabling an entry, or one that's pending or expired, removes it from the pages that showed it.
- An entry saved with a post date or an expiry date in the future gets one more purge then (a delayed queue job): Craft saves nothing when the date comes.
- Moving an entry in a structure purges it and its lists, whose order changed.
- Saving the SEO field's settings or a site flushes everything: they reach every page under no element's tag.

## KV Cache: Cloudflare purge

After every Nuxt cache purge (on entry/asset save or delete, manual tag purge, full flush) the plugin can also purge the Cloudflare edge cache. It is switched on and off from its own **Toolkit → Cloudflare** screen, with:

- **Zone ID** and **API token**: both accept `$ENV_VAR` and default to these env vars. The token needs the "Zone → Cache Purge" permission on that zone to purge, plus "Zone → Cache Rules → Edit" for the edge cache check to read, create and fix the Cache Rule; no Cloudflare login or OAuth is involved.

  ```env
  CLOUDFLARE_ZONE_ID="..."
  CLOUDFLARE_API_TOKEN="..."
  ```

- **Create token on Cloudflare**: opens the Cloudflare token form with the "Cache Purge" and "Cache Rules → Edit" permissions and a name already filled in; narrow *Zone Resources* to the site's zone there, then paste the token.
- **Verify saved Cloudflare settings**: purges one URL that is never cached, to check the zone ID and the token's permission without touching any cached page.

Until the switch is saved once, it is on when both env vars are set and Craft is not in dev mode, so a local install never purges the production zone.

**Edge cache check** requests the Frontend URL like a visitor (following redirects, so apex → www ends on the host that serves the pages) and reports:

- **Frontend CDN cache**: the home page sends `CDN-Cache-Control`, i.e. `NUXT_CDN_CACHE_ENABLED=true` with the data cache on. Cloudflare passes this header on to visitors, so it is seen from outside.
- **Cloudflare proxy**: the response has a `cf-ray` header (the DNS record is proxied, and the Frontend URL is the public one, not the origin).
- **Edge cache of HTML**: a second request comes back `HIT` (or `STALE`, `UPDATING`, `REVALIDATED`); `DYNAMIC` means no Cache Rule makes the HTML cacheable.
- **Craft previews**: a URL with `x-craft-preview` asked twice is never served from the cache.
- **Cache Rule**: read through the API, the rule for the host is enabled, *Eligible for cache*, Edge TTL `bypass_by_default` ("Use cache-control header if present, bypass cache if not"), Browser TTL *Respect origin*, keeps the query string in the cache key (`_payload.json?_b=<build id>`) and leaves out `/__nuxt_multi_cache/`, `/api/`, `x-craft-preview`, `x-craft-live-preview` and `token=`. This needs the token to also have the "Zone → Cache Rules → Edit" permission; without it the row is skipped.

A rule for the host is never duplicated. When the zone has none, **Create Cache Rule** adds the `HTML e payload` rule last, so it wins over the rules before it. When the rule is there but wrong (the one with that name, or else the first cache rule naming the host), the button becomes **Fix Cache Rule** and rewrites that rule in place. When it is right, the button does not show, and the action changes nothing. It only runs when the host is proxied, since a host outside the proxy most likely belongs to another zone. The screen also shows the expression, to create the rule by hand.

**View Cache Rules** opens a modal with the zone's Cache Rules in the order Cloudflare applies them: name, enabled or not, settings in the dashboard's words and expression. The rule the check uses for the frontend's host is highlighted with what is wrong in it, and the enabled rules after it are marked, since for the same setting the last matching rule wins. It needs the same "Zone → Cache Rules → Edit" permission (read is enough).

- Tag purges send the same tags to `POST /zones/:zone_id/purge_cache` (`{"tags": [...]}`, in chunks of 100); a full flush sends `{"purge_everything": true}`.
- The purge runs as a queue job, always after the Nuxt one: purging Cloudflare first would let it refetch a still-stale page (with the frontend's page cache on, the stale HTML itself). Purges are buffered for 60 seconds, so a burst of saves becomes one request. A frontend on `@studio-fes/layer-craft` 1.2 (nuxt-multi-cache 5, `cacheTagInvalidationDelay: 0`) has applied its purge when the request returns, so Cloudflare follows with no further wait; with an older frontend, or one Health can't read, Craft waits 10 seconds after the last purge (or the frontend's delay plus 5 seconds when longer). The delay comes from the frontend's `/__toolkit/health`, read at most every 10 minutes.
- The frontend must send `Cache-Tag` comma-separated (Cloudflare does not allow spaces in tags), as `@studio-fes/layer-craft` does.
- A failing Cloudflare purge never fails the Nuxt purge; the job is retried by the queue and the edge cache still expires on its own `max-age`.
- Emergency purges run right away, even with automatic invalidation or Cloudflare purging switched off. The **Advanced** screen has *Purge all caches* (Craft's caches, then KV, then the whole zone: purged first, Cloudflare would refetch pages built from stale data) and one button per layer; the Cloudflare screen also has *Purge Cloudflare cache* for the zone alone.

## Redirects

Craft is headless and never serves the pages, so it can't redirect visitors itself: before rendering a page, the frontend (`@studio-fes/nuxt-craft-toolkit`, in `@studio-fes/layer-craft`) asks `actions/toolkit/redirects-api/resolve?url=…&site=…`, caches the answer per URL and follows chains, so visitors get one redirect. Every change here clears that cache (the `redirects` tag, through the KV Cache settings).

- **Sources** match like Ether SEO's, in the list's order, first match wins: a path (`old-page`, slashes around it ignored, `?query` included when written); a pattern (`blog/(.*)` → `news/$1`, matched whole and case-insensitively) or a regex (`/^blog\/(\d+)$/i`); a full URL (`https://old-domain.com/page`) for frontends serving several domains.
- **Destinations**: a path on the redirect's site (its Base URL, the frontend's in headless projects), or a full URL.
- **Automatic redirects**: when a live entry's URI changes, its old URI redirects to the new one, redirects pointing at the old URI are updated, and a redirect from the new URI is removed. On by default; project config `toolkit.redirects.auto`.
- **From Ether SEO**: its redirects are imported, in order and without duplicates, whenever Ether is uninstalled, by the migration or by the project config on another environment.
- **CSV**: source, destination, status code (301 when empty), site handle (all sites when empty).

Redirects live in the database (`toolkit_redirects`), like content: each environment has its own.

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
  on that entry via the SEO field's **Structured data** with `@type` set to
  `MedicalClinic` (or `Dentist`/`Physician`/`Hospital`/`Pharmacy`) — its basic
  fields cover name, telephone, street address/locality/region/postal
  code/country (merged into a nested `address`), latitude/longitude (merged
  into a nested `geo`), `openingHours` and `priceRange`.

- **Structured data** (in the SEO field): one or more schema.org pieces
  (`Article`, `Product`, `Event`, `FAQPage`, `HowTo`, ...) per entry. Each row
  is a `@type` plus type-specific basic fields or a JSON object of properties;
  GraphQL exposes each piece pre-merged and ready to parse, as `seo { schema { json } }`.

In Nuxt, `useCraftSEO()` from `@studio-fes/layer-craft` registers an entry's
pieces with `useSchemaOrg()`; the site-wide identity goes through
`useCraftOrgSchemaIdentity()`:

```ts
const { data } = await useAsyncQuery(OrgSchemaQuery, { site: 'default' })
useCraftOrgSchemaIdentity(data.value.orgSchema)

const page = await useCraftEntry<PageEntryFragment>({ slug: [route.params.slug] })
useCraftSEO(page) // meta tags + the entry's schema.org pieces
```

The **Org Schema field** that used to hold these pieces is deprecated. Updating
the toolkit to 1.0.0 runs a migration that moves each entry type's pieces into
its SEO field (drafts and revisions too), removes the Org Schema field from the
layouts and deletes it. The migration runs before the project config is applied:
the environment where you update moves the content and writes the project
config, the others move their own content during `craft up` and get the field
removed from the YAML. Entry types without an SEO field keep their Org Schema
field, listed in Toolkit → SEO. Frontends querying `orgSchema { ... }` on
entries have to switch to `seo { schema { ... } }`.

## SEO

One **SEO** field for the whole site. Editors can type a title, description, social title/description/image, robots directives, a canonical URL, an AI summary and schema.org structured data pieces; whatever they leave empty is filled from rules:

- **Title**: custom title, as typed; else first non-empty *title source* → *title template* → entry title, wrapped in the field's *prefix* and *suffix* (Twig, e.g. ` - {{ siteName }}`; spaces kept). The suffix is only the automatic title's: a custom title replaces the whole title, so editors can drop the site name. Title and description come prefilled with their automatic values, editable in place as in Ether: untouched (or typed back, or Reset) they stay automatic and follow the entry, changed they're kept as typed.
- **Description**: custom → first non-empty *description source* (plain text, rich text stripped, cut at a word to *maximum length*) → *description template*.
- **Image**: custom → first image of the *image sources* → the field's default image.
- **Social title/description** fall back to title/description, the **AI summary** to the description. **Exclude from AI** adds `noai, noimageai` to the robots directives.

Sources and templates are set in the field settings (the defaults) and can be replaced per entry type: in the field layout designer, click the SEO field and its settings slideout has an "SEO rules for this layout" part, reading that layout's fields. They're saved on Apply, in the project config under `toolkit.seo.layoutElements.<layout element UID>` (not in the layout config: an unknown key there would break the layouts if the toolkit were disabled). Toolkit → SEO lists which entry types have their own rules. Prefix, suffix, default image and default robots always come from the field.

In the entry form the field has tabs: General (title, description), Social sharing, AI assistants, Structured data. The search and social previews open in a modal from any tab and stay in the entry's sidebar, which also holds robots and the canonical URL (a "Search engines" tab where there's no sidebar, as in inline nested entries).

**Structured data** pieces are checked as they're typed, against schema.org and Google's rich result rules for their type ([humanmade/schema-org-validator](https://github.com/humanmade/schema-org-validator), only the most specific profile: a clinic gets Local business, not Organization too): errors, warnings and Google's recommendations under each piece, never blocking the save. On live entries, "Test the published page with Google" opens the Rich Results Test.

GraphQL returns everything resolved and as plain text:

```graphql
fragment SEO on ToolkitSeo {
  title
  description
  ogTitle
  ogDescription
  image { ...Media }
  robots          # [String!]!
  robotsContent   # "noindex, nofollow" or null
  noindex
  nofollow
  canonical
  ai { summary exclude }
  schema { type json } # schema.org pieces, each a JSON-LD string
}
```

`@studio-fes/layer-craft`'s `useCraftSEO()` reads it, and registers `schema` with `useSchemaOrg()`.

### Moving off Ether SEO

While Ether SEO is installed, the CP shows an alert pointing to **Toolkit → SEO**. "Migrate and uninstall Ether SEO" (or `php craft toolkit/seo/migrate-ether`) does both in one step, in development:

1. every Ether SEO field becomes an SEO field **in place**: same ID, UID and handle, so entry content and the `seo` GraphQL field stay where they are. The field settings are taken from Ether's (title tokens → template/prefix/suffix, description template, social image, robots);
2. Ether SEO is uninstalled. Its redirects are imported into Toolkit → Redirects first (on every environment, the project config's uninstall included); its sitemap goes, Nuxt SEO serves one.

Then `composer remove ether/seo`, commit the project config and deploy: `craft up` applies the same change elsewhere.

Ether's stored values are read as they are, nothing is rewritten. Ether's CP saved its computed defaults as if typed, so a value only stays custom when it differs from what the field computes: titles and descriptions come out the same, and entries follow title changes again. Facebook and Twitter collapse into one set of social values (Facebook wins); keywords and the score are dropped.

**Old queries keep working.** Without Ether installed, the toolkit serves `Ether_SeoData` as an interface that `ToolkitSeo` implements (plus `Ether_SeoSocialNetworks`, `Ether_SeoSocialData`, `Ether_SeoAdvanced`, `Ether_SEOKeyword`), so `fragment SEO on Ether_SeoData` still validates and returns the same values; `__typename` becomes `ToolkitSeo`. `social`, `advanced` and `keywords` are deprecated and will be removed: each query still using them is logged in **Utilities → Deprecation Warnings**.

## Streaming Video (HLS)

Converts uploaded videos to HLS with several qualities, so viewers get one that fits their connection, without YouTube/Vimeo embeds and with the files on your own server. Headless: no player, the frontend plays the playlist with hls.js.

### Switching it on

Set `CRAFT_STREAMING_VIDEO=true` on the app and redeploy. On the craft-php image the same variable installs FFmpeg in the worker image, as long as the project's `docker-compose.yaml` passes it as a build arg (see the craft-php README):

```yaml
args:
  - install_ffmpeg=${CRAFT_STREAMING_VIDEO:-false}
```

Without it nothing is encoded and `hlsPlaylistUrl` is always `null`, but the field stays in the GraphQL schema, so the same frontend query works everywhere. HLS files are deleted with their asset either way. In DDEV, add `ffmpeg` to `webimage_extra_packages` and the variable to `web_environment` (the playground already does).

### Setting it up in a project

Nothing to add: with `CRAFT_STREAMING_VIDEO=true`, every video's edit form, in every volume, gets a **Streaming** block at the end of its first tab. No field and nothing in the layouts or the project config: the block is added when the form is built, and its values are kept in the toolkit's table.

### What it does

- Every video is queued on save (`PrepareHlsJob`) for its **progressive MP4**, HLS on or off. **Convert to HLS** in the Streaming block is off by default: switched on and saved, the same job also encodes the streams. A save that changes neither the file, the settings nor the switch doesn't re-encode; switching HLS off deletes the streams and keeps the MP4, without encoding it again. **Re-encode** queues it again anyway, MP4 included; once the streams are ready, **Re-encode MP4** and **Re-encode HLS** encode one of them only. Releasing the job from the queue stops ffmpeg, and the video shows "Failed".
- The worker encodes each selected quality up to the source height (never upscaled) and writes the files next to the video, in `__hls__/{asset-uid}/` in the same volume, then the master playlist last. Up to 1080p the qualities are picked one by one; **Above 1080p** adds 1440p and 2160p (4K) to the sources that have them.
- The MP4 is H.264/AAC with `-movflags +faststart`, in the highest selected quality up to 1080p, in the same directory as `video_{tag}.mp4`: what the frontend plays without HLS, and for downloads, social and email embeds. It's encoded first, so `mp4ProgressiveUrl` is there before the streams; on a re-encode the previous one keeps being served until the new one is uploaded.
- `hlsPlaylistUrl` on assets returns the master playlist URL once the streams are ready, `mp4ProgressiveUrl` the MP4's once it's encoded, and Craft's GraphQL cache for that asset is cleared.
- **Poster**, HLS on or off: FFmpeg finds the 3 strongest scene changes in the video, and the best one becomes an image asset in `video-posters/`. In the block, editors scrub the video with a slider or pick one of the 3 suggested moments, then save; or upload an image, or choose one from the assets, which stays until they pick a frame again. `videoPoster` in GraphQL returns the poster asset (`videoPoster { ...Media }`).
- The poster is the video's thumbnail in the CP.
- Once a video's streams are ready, the CP preview plays the HLS stream with hls.js (loaded from jsDelivr), with the quality it's playing shown below the video.
- The asset sidebar shows the status of the encoding (Queued, Processing, Ready, Failed).

### CP screen (Toolkit → Assets → Streaming Video)

- **Overview**: on/off state, videos by status, the latest failures with their error, and **Queue videos**, which queues only what needs it (new, replaced, failed, without an MP4, or encoded with other settings).
- **Settings**: qualities. Saved in the database of each environment, like the other Toolkit settings. Changing them affects new uploads; queue the videos to re-encode the existing ones.
- **Frontend, help and costs** (a modal): the frontend checklist; the fields to create, the GraphQL fragment, `<CraftMedia>` and troubleshooting; what streaming costs on Hetzner/Coolify, when to buy object storage, when Mux is the better choice.

### Code

| Where | What |
|---|---|
| `services/StreamingVideoService.php` | On/off, settings, queueing, playlist URL: the entry point for everything else |
| `streamingvideo/StreamingVideoFormElement.php` | The Streaming block on video forms: HLS switch, status, Re-encode, poster |
| `streamingvideo/StreamingVideoBehavior.php` | The `toolkitStreaming` attribute the block posts to, on assets |
| `handlers/StreamingVideoHandlers.php` | GraphQL fields, asset save/delete, sidebar status, CP thumbnail and preview |
| `jobs/PrepareHlsJob.php` | The poster frame, the MP4, then the HLS qualities one at a time |
| `jobs/PreparePosterJob.php` | Extracts the poster frame without re-encoding |
| `streamingvideo/Poster.php` | Suggested moments and the poster frame asset |
| `streamingvideo/HlsPreview.php` | CP preview that plays the HLS stream |
| `streamingvideo/EncodingProfile.php` | Bitrate ladder, segment length, `VERSION` (bump it when the encoding changes) |
| `streamingvideo/FFmpeg.php` | ffmpeg/ffprobe calls, restricted to safe containers |
| `streamingvideo/HlsStorage.php` | Paths, URLs, uploads and deletion of the streams and the MP4 on the volume's filesystem |
| `streamingvideo/ConversionStatuses.php` | Status table (`toolkit_streaming_video_status`), created on first use |

### Security

Only MP4/MOV, MKV/WebM and MPEG-TS are converted: other containers can make FFmpeg read local files into the public output, so they are refused and keep playing from the original. FFmpeg runs with `-protocol_whitelist file`. Encoding is CPU and disk intensive: uploads should come from trusted users only.

## CP app

Every Toolkit screen, the SEO and Org Schema fields' inputs and the Streaming block are a Vue 3 app in `app/`, styled with Craft's own classes (`btn`, `field`, `lightswitch`…) and UnoCSS for the rest, built by Vite to `src/web/dist/`.

```bash
pnpm dev         # rebuilds on every change (unminified, with sourcemaps)
pnpm build       # production build
pnpm lint        # @studio-fes/eslint-config
pnpm typecheck
```

How it fits together:

- PHP renders a mount point with its props: `Island::render('seo-field', [...])` → `<div data-tk-app="seo-field" data-props="{…}">`. Toolkit screens are `cp/screens/*Screen.php` (their props) and `app/components/templates/screens/*Screen.vue`; `ToolkitSections` lists them, and groups some under one nav item as tabs (Assets: Average Color, Streaming Video, Unused Assets; Advanced: Purge caches, Root redirect).
- `src/web/ToolkitAsset.php` loads `toolkit.js` on every CP page (about 1 KB): it mounts the islands it finds, also in slideouts and modals added later, and loads Vue and each island's code only there.
- Actions answer JSON through `controllers/ApiController.php`; a save sends the screen's props back, so the screen shows what was stored.
- Field inputs post real form inputs with the name Craft namespaced (`fields[seo][title]`), so drafts, autosave and "unsaved changes" work as with any field. Assets are picked with Craft's own element selector modal.
- Components follow the frontends' layout: `atoms/`, `molecules/`, `organisms/`, `templates/`, auto-imported like in Nuxt. UnoCSS classes go behind `:uno:` (compiled to one `tk-*` class); Craft's classes stay outside it.
- No UnoCSS preflights: the theme values are written into the utilities at build time, so nothing touches Craft's own CSS variables (`--spacing`, `--radius-sm`…).

`src/web/dist/` is not committed. CI builds it into every release tag (see below); a checkout of `main` without `pnpm build` leaves the Toolkit screens empty and logs a warning, the rest of the CP keeps working.

## Releases

1. Add the version to `CHANGELOG.md` (`## 1.2.1`), commit and push `main`.
2. Run the release: `gh workflow run release.yml -f version=1.2.1` (or Actions → Release → Run workflow).
3. `.github/workflows/release.yml` builds the CP app, commits `src/web/dist/` on top of `main` and creates the `v1.2.1` tag on that commit, so Composer installs it ready to use. `main` never carries build output.

Never push a `v*` tag by hand: Packagist publishes it within seconds, and a stable version can't change afterwards, so it would ship without the CP app (it happened to 1.2.0).

`.github/workflows/ci.yml` lints, typechecks and builds every push to `main` and every pull request.

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
