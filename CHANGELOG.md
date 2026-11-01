# Changelog

## Unreleased

- The CP is rewritten in Vue 3 + UnoCSS (`app/`, built by Vite): every Toolkit screen, the SEO and Org Schema field inputs, the Streaming block and the HLS preview. Same look, Craft's own classes; saves no longer reload the page. The CP needs the built app: release tags ship it, a checkout of `main` needs `pnpm install && pnpm build`.
- hls.js is bundled instead of loaded from jsDelivr.
- PHP: the settings tables share one `SettingsTable` helper (same tables and columns, existing settings are kept), actions answer JSON through `ApiController`, and each screen's data comes from `cp/screens/*Screen.php`. The `*Utility` classes, asset bundles and Twig screens are gone.
- Fixed: the Unused Assets screen failed with a PHP syntax error.

- New SEO field type: title, description, social title/description/image, robots, canonical URL, AI summary and "Exclude from AI" (`noai`, `noimageai`). Whatever the editor leaves empty comes from rules: ordered title/description/image sources, then a Twig template, with a prefix/suffix (e.g. the site name) on automatic titles only: a custom title replaces the whole title, as with Ether. One global field; each entry type can set its own rules from the field's settings slideout in its field layout (project config `toolkit.seo.layoutElements`). Exposed as `ToolkitSeo` in GraphQL, every value resolved.
- Ether SEO migration: a CP alert while Ether SEO is installed, and "Migrate and uninstall Ether SEO" in Toolkit → SEO (or `php craft toolkit/seo/migrate-ether`) switches its fields to the SEO type in place and uninstalls it. Stored values are read as they are; titles and descriptions come out the same.
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
