# Changelog

## Unreleased

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
