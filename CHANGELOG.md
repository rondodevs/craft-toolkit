# Changelog

## 0.0.5

- KV Cache: detect when the Nuxt frontend cache endpoint is unreachable and temporarily skip purges (element save/delete handlers and the deferred purge job) until it comes back online, with a CP alert while this is active.
- Redirect Utility: require an authenticated (non-guest) user before resolving redirect settings, closing a guest-accessible path.

## 0.0.2

- Require PHP >= 8.4 to avoid platform conflicts with consuming projects.

## 0.0.1

- Initial release, extracted from the `modules/toolkit` module in `starter-craft`.
