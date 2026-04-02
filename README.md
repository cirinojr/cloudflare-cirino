# Cloudflare Cirino

Cloudflare Cirino keeps Cloudflare cache in sync with WordPress content changes.

It is a focused plugin: native wp-admin settings page, no build step, and no external runtime dependencies.

## What it does

- Purges Cloudflare when public content is published, updated, unpublished, or deleted.
- Supports two purge strategies:
   - **Targeted**: affected permalink + related public URLs.
   - **Everything**: full zone purge.
- Provides manual admin actions for **Test Connection** and **Purge Now**.
- Stores recent runtime signals (last test, last purge, recent activity).

## Architecture

Core classes are intentionally small and single-purpose:

- `includes/class-api-client.php` – Cloudflare HTTP requests and auth headers.
- `includes/class-purge-service.php` – purge orchestration and result tracking.
- `includes/class-content-hooks.php` – maps post lifecycle events to purge calls.
- `includes/class-options.php` – settings/runtime option access.
- `includes/class-activity-log.php` – bounded activity history.
- `includes/Admin/class-settings.php` – settings registration/sanitization.
- `includes/Admin/class-actions.php` – nonce/capability-protected admin-post handlers.
- `includes/Admin/class-settings-page.php` – admin UI rendering + scoped asset loading.

## Setup

1. Copy this plugin to `wp-content/plugins/cloudflare-cirino`.
2. Activate **Cloudflare Cirino**.
3. Open **Tools → Cloudflare Cirino**.
4. Configure:
    - Cloudflare Zone ID
    - Authentication mode
    - Purge mode
5. Save, then run **Test Connection**.

## Authentication modes

- **API Token (recommended)**
   - Sends `Authorization: Bearer <token>`.
   - Requires zone-level cache purge permission.
- **Legacy Email + Global API Key**
   - Supported for compatibility with older setups.

Secrets are never shown back in plain text in the settings form; blank password fields keep existing stored values.

## Purge behavior details

Automatic hooks cover:

- first publish of public content
- updates to already public content
- transitions between public/non-public states
- permanent delete for public content

Targeted mode includes:

- content permalink
- homepage
- post type archive (when available)
- related public taxonomy archive URLs

Manual targeted purge uses a site-level list (home + public archives).

## Limitations

- No queue/background worker: purges run inline with the triggering request.
- No multisite network settings UI.
- No per-post-type configuration screen.

## Technical rationale

This plugin favors predictable behavior over broad feature surface:

- WordPress HTTP API and option APIs only
- small class boundaries without framework abstractions
- conservative admin UX aligned with wp-admin patterns
- explicit nonce and capability checks for privileged actions

## Changelog

### 1.0.1

- Fixed activity log ordering bug.
- Fixed attachment post type exclusion in content hooks.
- Avoided duplicate delete-related purge by skipping non-public permanent deletes.
- Consolidated targeted purge execution path.
- Improved uninstall cleanup and admin notice handling.
- Refined admin styling to a cleaner native WordPress look.
- Rewrote project documentation for accuracy and scope clarity.
