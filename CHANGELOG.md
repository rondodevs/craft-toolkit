# Changelog

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
