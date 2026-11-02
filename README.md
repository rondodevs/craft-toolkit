# Craft Toolkit

Internal Craft CMS plugin bundling a set of control panel utilities:

- **Site Config** — CP-editable overrides for site name/URL on top of env defaults, exposed via GraphQL.
- **KV Cache** — settings and controls to purge an external edge/KV cache (tags or full flush) when entries/assets change.
- **Static Labels** — CP-editable label overrides per site, exposed via GraphQL.
- **Average Color** — enable/disable and per-volume control for automatic average-color calculation on image assets, with a check of which volumes already have an `averageColor` field in their field layout. The `media` volume is selected by default when present.
- **Org Schema** — CP panel with per-site defaults for the schema.org identity (Organization, LocalBusiness, MedicalClinic/MedicalBusiness, Person, ...: name, logo, one or more addresses, `sameAs` social profiles, contact info), exposed via GraphQL for Nuxt SEO's [default schema.org identity](https://nuxtseo.com/docs/schema-org/guides/default-schema-org). The pieces of each entry (Article, Product, Event, MedicalClinic, FAQPage, ...) are in the SEO field's "Structured data".
- **SEO** — an SEO field type (title, description, social title/description/image, robots, canonical, AI summary, "exclude from AI" and schema.org structured data) that fills whatever the editor leaves empty from rules: an ordered list of fields to read the title, description and image from, then a Twig template. One global field; the rules can differ per entry type (in the field layout). Replaces Ether SEO, with a one-step migration that keeps the existing GraphQL queries working.
- **Unused Assets** — lists the assets of a volume (`media` by default) that no content uses and moves the ticked ones to the trash, restorable from there. Relations (drafts, revisions and trashed entries included), `{asset:ID}` reference tags in rich text and Link fields, user photos, the Org Schema logo and Streaming Video posters count as uses; uploads younger than 24 hours are left alone.
- **Streaming Video** — encodes the videos switched on in its field to HLS (adaptive streaming, up to 4K) in the queue, with a poster frame picker, and adds `hlsPlaylistUrl` and `videoPoster` to assets in GraphQL. Off unless `CRAFT_STREAMING_VIDEO=true`, the same variable that installs FFmpeg in the craft-php worker. CP screen with status, qualities, a costs guide and help for Nuxt.
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
- The purge runs as a queue job 10 seconds after the Nuxt one, because nuxt-multi-cache applies tag purges after its `cacheTagInvalidationDelay` (3s): purging Cloudflare first would let it refetch a still-stale page.
- The frontend must send `Cache-Tag` comma-separated (Cloudflare does not allow spaces in tags), as `@studio-fes/layer-craft` does.
- A failing Cloudflare purge never fails the Nuxt purge; the job is retried by the queue and the edge cache still expires on its own `max-age`.
- Emergency purges run right away, even with automatic invalidation or Cloudflare purging switched off. The **Advanced** screen has *Purge all caches* (Craft's caches, then KV, then the whole zone: purged first, Cloudflare would refetch pages built from stale data) and one button per layer; the Cloudflare screen also has *Purge Cloudflare cache* for the zone alone.

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
the toolkit to 0.0.21 runs a migration that moves each entry type's pieces into
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
2. Ether SEO is uninstalled (its redirects and sitemap tables go with it: the page warns when there are redirects).

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

- **Convert to HLS** in the Streaming block is off by default: switched on and saved, the video is queued (`PrepareHlsJob`). A save that changes neither the file nor the settings doesn't re-encode; switching it off deletes the streams. **Re-encode** queues it again anyway.
- The worker encodes each selected quality up to the source height (never upscaled) and writes the files next to the video, in `__hls__/{asset-uid}/` in the same volume, then the master playlist last. Up to 1080p the qualities are picked one by one; **Above 1080p** adds 1440p and 2160p (4K) to the sources that have them.
- `hlsPlaylistUrl` on assets returns the master playlist URL once the video is ready, and Craft's GraphQL cache for that asset is cleared.
- **Poster**, HLS on or off: FFmpeg finds the 3 strongest scene changes in the video, and the best one becomes an image asset in `video-posters/`. In the block, editors scrub the video with a slider or pick one of the 3 suggested moments, then save; or upload an image, or choose one from the assets, which stays until they pick a frame again. `videoPoster` in GraphQL returns the poster asset (`videoPoster { ...Media }`).
- The poster is the video's thumbnail in the CP.
- Once a video is HLS Ready, the CP preview plays the HLS stream with hls.js (loaded from jsDelivr), with the quality it's playing shown below the video.
- The asset sidebar shows the status (Queued, Processing, HLS Ready, Failed).

### CP screen (Toolkit → Streaming Video)

- **Overview**: on/off state, videos by status, the latest failures with their error, and **Queue videos**, which queues only what needs it (new, replaced, failed, or encoded with other settings).
- **Settings**: qualities. Saved in the database of each environment, like the other Toolkit settings. Changing them affects new uploads; queue the videos to re-encode the existing ones.
- **Frontend, help and costs** (a modal): the frontend checklist; the fields to create, the GraphQL fragment, `<CraftMedia>` and troubleshooting; what streaming costs on Hetzner/Coolify, when to buy object storage, when Mux is the better choice.

### Code

| Where | What |
|---|---|
| `services/StreamingVideoService.php` | On/off, settings, queueing, playlist URL: the entry point for everything else |
| `streamingvideo/StreamingVideoFormElement.php` | The Streaming block on video forms: HLS switch, status, Re-encode, poster |
| `streamingvideo/StreamingVideoBehavior.php` | The `toolkitStreaming` attribute the block posts to, on assets |
| `handlers/StreamingVideoHandlers.php` | GraphQL fields, asset save/delete, sidebar status, CP thumbnail and preview |
| `jobs/PrepareHlsJob.php` | The encoding, one quality at a time, then the poster frame |
| `jobs/PreparePosterJob.php` | Extracts the poster frame without re-encoding |
| `streamingvideo/Poster.php` | Suggested moments and the poster frame asset |
| `streamingvideo/HlsPreview.php` | CP preview that plays the HLS stream |
| `streamingvideo/EncodingProfile.php` | Bitrate ladder, segment length, `VERSION` (bump it when the encoding changes) |
| `streamingvideo/FFmpeg.php` | ffmpeg/ffprobe calls, restricted to safe containers |
| `streamingvideo/HlsStorage.php` | Paths, URL, uploads and deletion on the volume's filesystem |
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

- PHP renders a mount point with its props: `Island::render('seo-field', [...])` → `<div data-tk-app="seo-field" data-props="{…}">`. Toolkit screens are `cp/screens/*Screen.php` (their props) and `app/components/templates/screens/*Screen.vue`; `ToolkitSections` lists them.
- `src/web/ToolkitAsset.php` loads `toolkit.js` on every CP page (about 1 KB): it mounts the islands it finds, also in slideouts and modals added later, and loads Vue and each island's code only there.
- Actions answer JSON through `controllers/ApiController.php`; a save sends the screen's props back, so the screen shows what was stored.
- Field inputs post real form inputs with the name Craft namespaced (`fields[seo][title]`), so drafts, autosave and "unsaved changes" work as with any field. Assets are picked with Craft's own element selector modal.
- Components follow the frontends' layout: `atoms/`, `molecules/`, `organisms/`, `templates/`, auto-imported like in Nuxt. UnoCSS classes go behind `:uno:` (compiled to one `tk-*` class); Craft's classes stay outside it.
- No UnoCSS preflights: the theme values are written into the utilities at build time, so nothing touches Craft's own CSS variables (`--spacing`, `--radius-sm`…).

`src/web/dist/` is not committed. CI builds it into every release tag (see below); a checkout of `main` without `pnpm build` leaves the Toolkit screens empty and logs a warning, the rest of the CP keeps working.

## Releases

1. Add the version to `CHANGELOG.md` and commit.
2. Tag and push: `git tag v0.1.0 && git push origin v0.1.0`.
3. `.github/workflows/release.yml` builds the CP app, commits `src/web/dist/` on top of the tag and moves the tag onto that commit, so Composer installs it ready to use. `main` never carries build output.

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
