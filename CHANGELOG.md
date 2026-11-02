# Changelog

## Unreleased

- CP: Average Color, Streaming Video and Unused Assets are tabs of one "Assets" item in the Toolkit nav, Redirects and Root redirect of one "Redirects" item. Each tab keeps its URL (`toolkit/average-color`…); `toolkit/assets` and `toolkit/redirections` open the first one.

## 1.2.2

- Streaming Video: once a video's HLS streams are ready, the Streaming block has **Re-encode MP4** and **Re-encode HLS** instead of one Re-encode, to encode one of them again without the other. With HLS off, or streams failed or out of date, Re-encode still encodes everything.
- Fixed: releasing an encoding job from the queue (Queue Manager → Release) didn't stop it: the worker kept running ffmpeg to the end and the video stayed "Processing". ffmpeg is now stopped within a second, and the video shows "Failed" (Stopped from the queue), ready to be re-encoded.
- Security: the SEO rules posted with a field layout's settings slideout were read before Craft checked the request (login, CSRF, admin, `allowAdminChanges`), so requests from anyone reached that code, and cleared the GraphQL caches. They're now saved only for admins with admin changes allowed and a valid CSRF token.
- Security: the poster's image preview in the Streaming block checks that the editor can view that image's volume.

## 1.2.1

- Same code as 1.2.0, which Packagist published without the built CP app (Toolkit screens empty): update to 1.2.1. Releases are now tagged by the Release workflow itself (`gh workflow run release.yml -f version=…`), on the commit with the build.

## 1.2.0

- Streaming Video: every video gets a progressive MP4 (H.264/AAC, fast start), HLS on or off, in the highest selected quality up to 1080p, next to the streams; `mp4ProgressiveUrl` on assets in GraphQL returns it, null until it's encoded. With HLS on, the same job encodes the MP4 first, then the streams; switching HLS off deletes the streams and keeps the MP4. The poster is extracted at the start of the job instead of the end. The status reads "Ready" instead of "HLS Ready", and the Streaming block shows it with HLS off too.
- `EncodingProfile::VERSION` is 4: "Queue videos" queues every video, HLS or not, so they all get an MP4 (and re-encodes the streams of those with HLS on). On a large library that's a lot of encoding: run it when the worker is free.

## 1.1.0

- New "Redirects" screen: URL redirects for headless frontends, applied by `@studio-fes/nuxt-craft-toolkit` before rendering. Path, pattern (`blog/(.*)` → `news/$1`) or full-URL sources matched like Ether SEO's, in order; 301/302/307/308, per site or all; hits and last hit; search, reorder, CSV import/export, a URL tester. Stored in the database (`toolkit_redirects`, created on first use), like content. Changes clear the frontend's `redirects` cache tag through the KV Cache settings; "Frontend redirects" in Utilities → Caches does it by hand.
- Automatic redirects: when a live entry's URI changes, its old URI redirects to the new one and redirects to the old URI follow, so there are no chains. On by default (project config `toolkit.redirects.auto`).
- Ether SEO's redirects are imported into Redirects before it's uninstalled, from the migration and from the project config alike, instead of being dropped with its tables.
- `actions/toolkit/redirects-api/resolve` and `/hit` for the frontend; `actions/toolkit/seo-api/llms` lists the live pages with their AI summary for `/llms.txt`. The old "Redirect" screen (the site root to the frontend) is now "Root redirect".
- SEO field: the sections are tabs at the top (General, Social sharing, AI assistants, Structured data); the search and social previews open in a modal from any tab, and stay in sight in the entry's sidebar. Robots and the canonical URL live in the sidebar too, or in a "Search engines" tab where there's no sidebar.
- Structured data is checked as it's typed, against schema.org and Google's rich result rules for its type (`humanmade/schema-org-validator`): missing required properties, unknown ones, invalid values, Google's recommendations. Warnings only, never blocking a save. "Test the published page with Google" opens the Rich Results Test on live entries.
- Event structured data has place name and address fields, nested as a `Place` with a `PostalAddress`, as Google requires; an Event's old "Location" text becomes its place name.

## 1.0.0

- The CP is rewritten in Vue 3 + UnoCSS (`app/`, built by Vite): every Toolkit screen, the SEO and Org Schema field inputs, the Streaming block and the HLS preview. Same look, Craft's own classes; saves no longer reload the page. The CP needs the built app: release tags ship it, a checkout of `main` needs `pnpm install && pnpm build`.
- hls.js is bundled instead of loaded from jsDelivr.
- PHP: the settings tables share one `SettingsTable` helper (same tables and columns, existing settings are kept), actions answer JSON through `ApiController`, and each screen's data comes from `cp/screens/*Screen.php`. The `*Utility` classes, asset bundles and Twig screens are gone.
- Fixed: the Unused Assets screen failed with a PHP syntax error.

- New SEO field type: title, description, social title/description/image, robots, canonical URL, AI summary and "Exclude from AI" (`noai`, `noimageai`). Whatever the editor leaves empty comes from rules: ordered title/description/image sources, then a Twig template, with a prefix/suffix (e.g. the site name) on automatic titles only: a custom title replaces the whole title, as with Ether. One global field; each entry type can set its own rules from the field's settings slideout in its field layout (project config `toolkit.seo.layoutElements`). Exposed as `ToolkitSeo` in GraphQL, every value resolved.
- Ether SEO migration: a CP alert while Ether SEO is installed, and "Migrate and uninstall Ether SEO" in Toolkit → SEO (or `php craft toolkit/seo/migrate-ether`) switches its fields to the SEO type in place and uninstalls it. Stored values are read as they are; titles and descriptions come out the same.
- The SEO field has a "Structured data" section: the schema.org pieces the Org Schema field had (type-specific basic fields or JSON), returned as `seo { schema { type json } }`.
- The Org Schema field is deprecated. A migration (schema version 1.1.0) moves each entry type's pieces into its SEO field, drafts and revisions included, then removes the Org Schema field from the layouts (and its tab, when left empty) and deletes it. It runs before the project config is applied: the environment that updates writes the project config, the others only move their content. Entry types without an SEO field keep it, listed in Toolkit → SEO. **Breaking for frontends** that query `orgSchema` on entries: query `seo { schema { json } }`.
- Streaming Video: the frontend checklist, Help and Costs are in a modal ("Frontend, help and costs") instead of tabs; the section icon is a Font Awesome one like the others.
- The Ether SEO GraphQL queries keep working after the migration: `Ether_SeoData` becomes an interface of `ToolkitSeo`. Its `social`, `advanced` and `keywords` fields are deprecated and logged in Deprecation Warnings.

## 0.0.20

- Streaming Video: the Streaming field is replaced by a Streaming block that every video's edit form gets on its own, in every volume, with nothing in the layouts or the project config. HLS on/off and the picked frame are kept in the toolkit's table; videos already encoded stay on. Remove the `streamingVideo` field from the project config: its field type no longer exists.

## 0.0.19

- Streaming Video: with `CRAFT_STREAMING_VIDEO=true` and admin changes allowed, the Streaming field is added to the `media` volume's layout on its own, on video files only (once: removed afterwards, it stays removed). The Overview tab lists the volumes and adds the field to the others.

## 0.0.18

- New "Unused Assets" screen: finds the assets of a volume that no content uses (relations, reference tags, user photos, Org Schema logo, video posters, uploads younger than 24 hours all count as uses) and moves the ticked ones to the trash.
- New "Streaming Video" screen: HLS encoding of video assets in the queue, `hlsPlaylistUrl` on assets in GraphQL, conversion status in the asset sidebar. It replaces the separate craft-streaming-video plugin. Off unless `CRAFT_STREAMING_VIDEO=true`, the same variable that installs FFmpeg in the craft-php worker; the GraphQL field is always in the schema and is `null` while off.
- Streaming Video: Overview tab with videos by status, the latest failures and "Queue videos"; Settings tab with qualities; Costs tab on hosting, object storage and video services; Help tab for Nuxt with `@studio-fes/layer-craft`.
- Streaming Video field: HLS switched on per video (off by default), status, Re-encode, and a poster frame picker with a slider and 3 suggested moments found by scene detection.
- Streaming Video: the poster is an image asset, a frame of the video or an image uploaded or chosen in the field, returned as `videoPoster` in GraphQL (`videoPoster { ...Media }`) and shown as the video's CP thumbnail.
- Streaming Video: "Above 1080p" adds 1440p and 4K at their own bitrates, up to the source height.
- Streaming Video: the CP preview plays the HLS stream once the video is ready.
- DDEV playground: FFmpeg installed and Streaming Video switched on.

## 0.0.17

- Org Schema: opening hours are no longer dropped after saving. They are stored as schema.org strings (e.g. `Mo-Fr 09:00-18:00`), which the read path discarded, so the CP picker and the GraphQL `openingHours` field always came back empty.

## 0.0.16

- Cloudflare: tag purges are now batched. Every purge in a 60-second window goes out together as one request (or one per 100 tags), instead of one request per saved entry or asset, which hit Cloudflare's rate limit (HTTP 429) on bulk saves. A rate-limited purge is put back in the batch and retried.

## 0.0.15

- New "Advanced" screen, last in the Toolkit menu: "Purge all caches" clears Craft's caches (everything in Craft's Caches utility, GraphQL included), then flushes the KV cache, then purges the whole Cloudflare zone. Each layer can also be purged on its own. It replaces the KV Cache screen's emergency purge and the `toolkit/kv-cache/purge-everything` action.
- Cloudflare: the screen is split into tabs like KV Cache: Settings, Edge Cache (check and Cache Rules) and Purge.
- Cloudflare: the purge of the whole zone moved from the KV Cache screen to the Cloudflare screen, as "Purge Cloudflare cache".
- Cloudflare: new "Check edge cache" on the Cloudflare screen. It checks that the frontend sends `CDN-Cache-Control`, that the site is proxied, that pages come back `HIT` and previews never do, and, with a token that can read Cache Rules, that the zone's Cache Rule is set correctly.
- Cloudflare: "Create Cache Rule" creates the `HTML e payload` Cache Rule through the API, and "Fix Cache Rule" rewrites an existing wrong one in place: a rule for the host is never duplicated (needs the "Zone → Cache Rules → Edit" permission).
- Cloudflare: "View Cache Rules" shows the zone's Cache Rules in a modal, in the order they apply, marking the one the check uses and the rules after it that can override it.
- Cloudflare: "Create token on Cloudflare" now fills in "Cache Rules → Edit" too, next to "Cache Purge". When the saved token cannot read Cache Rules, the check and the modal link to the same form.
- The modal styles moved from the KV cache screen to the shared `toolkit.css` (`toolkit-modal-*` classes).

## 0.0.14

- Cloudflare now appears in the Toolkit menu in the CP sidebar. The sidebar is built from the same list as the Toolkit nav bar, so new screens show up in both.

## 0.0.13

- KV Cache: the manual flush is now an "Emergency purge" with three buttons, each asking for confirmation: "Flush KV only", "Purge Cloudflare only" and "Purge all" (KV first, then the whole Cloudflare zone). The Cloudflare purges run right away, even when Cloudflare purging is switched off, and show Cloudflare's error in the CP.
- Cloudflare: a skipped purge (switched off, or Zone ID / API token missing) is now logged.

## 0.0.12

- Cloudflare: new Toolkit screen to switch the Cloudflare purge on and off, with its own Zone ID and API token fields (`$ENV_VAR` supported, defaulting to `CLOUDFLARE_ZONE_ID` and `CLOUDFLARE_API_TOKEN`), a button that opens the Cloudflare token form pre-filled with the "Cache Purge" permission, and a button to verify them. Off by default in dev mode. Existing installs get the new settings columns automatically.
- KV Cache: the screen now opens on the Settings tab.

## 0.0.11

- KV Cache: purge the Cloudflare edge cache by tag (or everything on full flush) after each Nuxt cache purge, when `CLOUDFLARE_ZONE_ID` and `CLOUDFLARE_API_TOKEN` are set. Runs as a delayed queue job so it lands after nuxt-multi-cache's own tag purge.

## 0.0.10

- Settings > Plugins now shows a working "Settings" link for Toolkit that opens the plugin's own CP screen.
- Added a shared nav bar across all Toolkit CP screens (Site Config, KV Cache, Static Labels, Average Color, Redirect, Org Schema), each with an icon, so you can jump between sections without leaving the page.
- KV Cache: automatic on-save cache invalidation is now disabled by default when Craft is running in dev mode. It can still be turned on manually from the KV Cache settings screen; an explicitly saved value always takes precedence over the dev-mode default.

## 0.0.6

- Redirect Utility: allow anonymous access to the site redirect action, fixing a 403 Forbidden that guests hit on the homepage.

## 0.0.5

- KV Cache: detect when the Nuxt frontend cache endpoint is unreachable and temporarily skip purges (element save/delete handlers and the deferred purge job) until it comes back online, with a CP alert while this is active.
- Redirect Utility: require an authenticated (non-guest) user before resolving redirect settings, closing a guest-accessible path.

## 0.0.2

- Require PHP >= 8.4 to avoid platform conflicts with consuming projects.

## 0.0.1

- Initial release, extracted from the `modules/toolkit` module in `starter-craft`.
