# Cloudflare Cirino

Cloudflare Cirino is a focused WordPress plugin that keeps Cloudflare edge cache aligned with real content changes.

It is intentionally lean: native wp-admin UI, WordPress HTTP API, no Composer dependency, and no build step.

## Project overview

This plugin listens to WordPress content lifecycle events and runs Cloudflare purge requests using either targeted URL invalidation or full-zone purge.

It is designed for teams that want predictable cache invalidation behavior without adding a heavy plugin stack.

## Problem this plugin solves

When Cloudflare caching is aggressive, editors can publish updates in WordPress while visitors still receive stale content from edge nodes.

Without reliable invalidation, teams get inconsistent page states across devices/locations and spend time manually clearing cache.

## Why this matters for WordPress sites using aggressive caching

- Editorial updates often happen throughout the day and need to propagate quickly.
- Cache staleness can break trust in time-sensitive pages (announcements, pricing, campaigns, legal updates).
- Manual purge workflows are easy to forget and hard to audit.

## Core features

- Automatic purge on relevant public post lifecycle changes.
- Two purge strategies: targeted URLs or purge everything.
- Manual admin actions for **Test Connection** and **Purge Now**.
- Runtime status visibility (last test, last purge, message).
- Bounded recent activity log in wp-admin.

## Architecture overview

The codebase is intentionally split by responsibility, not by artificial layers:

- `includes/class-content-hooks.php`: converts WordPress events into purge intents.
- `includes/class-purge-service.php`: decides strategy and executes purge flow.
- `includes/class-api-client.php`: wraps Cloudflare API requests and auth headers.
- `includes/class-options.php`: settings + runtime option access.
- `includes/class-activity-log.php`: short, bounded event history.
- `includes/Admin/class-settings.php`: settings registration and sanitization.
- `includes/Admin/class-actions.php`: capability + nonce protected admin actions.
- `includes/Admin/class-settings-page.php`: page rendering and scoped assets.

## Authentication modes

### API Token (recommended)

- Sends `Authorization: Bearer <token>`.
- Requires zone-level cache purge permission.

### Legacy Email + Global API Key

- Supported for compatibility with older Cloudflare account setups.
- Available behind the advanced section in settings.

Credentials are not re-rendered in plaintext after saving. Blank credential fields keep existing saved values.

## Purge strategies

### Targeted

For post-related events, targeted purge includes:

- post permalink
- homepage
- post type archive (if available)
- related public taxonomy archive URLs

For manual purge, targeted mode clears a site-level set (home + public archives).

### Purge Everything

Clears the full Cloudflare zone cache and is useful when broad template/global content changes invalidate most pages.

## Admin experience

- Lives under **Tools → Cloudflare Cirino**.
- Shows connection summary and last operation timestamps.
- Provides one-click test connection and manual purge actions.
- Uses nonce and capability checks for privileged actions.

## Technical decisions

- Uses WordPress-native APIs (`wp_remote_request`, options/settings APIs, admin-post handlers).
- Skips autosaves/revisions and non-public transitions to reduce purge noise.
- Keeps logic testable by separating hooks, orchestration, API transport, and admin UI.
- Avoids background queues to keep behavior transparent and easy to reason about.

## Limitations / trade-offs

- Purges run inline with the triggering request (no async worker/queue).
- No multisite network-level settings screen.
- No per-post-type purge policy UI.

## Installation

1. Copy this plugin folder to `wp-content/plugins/cloudflare-cirino`.
2. Activate **Cloudflare Cirino** in WordPress.
3. Open **Tools → Cloudflare Cirino**.

## Configuration

1. Set Cloudflare Zone ID.
2. Choose authentication mode and provide credentials.
3. Choose purge mode (`targeted` or `everything`).
4. Save settings.
5. Click **Test Connection**.

## Screenshots

Add screenshots to `assets/screenshots/` using these filenames:

1. `01-settings-overview.png` — full settings page.
2. `02-connection-test-status.png` — successful/failed connection state.
3. `03-recent-activity-log.png` — recent purge/test events.
4. `04-targeted-vs-everything.png` — purge mode selection and manual purge action.

> The repository includes `assets/screenshots/README.md` with capture guidance.

## Roadmap

- Add a small filter/reference section to docs with practical customization examples.
- Add optional WP-CLI command for manual purge execution.
- Evaluate non-blocking purge execution as an opt-in mode.

## Changelog

Full changelog lives in `CHANGELOG.md`.

### 1.0.1

- Fixed activity log ordering.
- Improved supported post type filtering.
- Prevented non-public permanent delete purge noise.
- Refined settings validation and documentation.

### 1.0.0

- Initial public release.
