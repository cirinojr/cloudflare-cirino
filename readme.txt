=== Cloudflare Cirino ===
Contributors: cirinojr
Tags: cloudflare, cache, purge, performance
Requires at least: 6.1
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic Cloudflare cache purge for WordPress content lifecycle events with targeted and full-zone strategies.

== Description ==
Cloudflare Cirino keeps your Cloudflare edge cache aligned with WordPress content changes.

When public content changes, the plugin runs the configured purge strategy:

- Targeted mode: purges the content permalink and related URLs such as home, relevant archives, and taxonomy pages.
- Purge everything mode: clears the entire zone cache.

The plugin includes a native WordPress admin screen with:
- Connection status and last result visibility
- API Token-first authentication
- Legacy Email + Global API Key fallback (optional)
- Manual Test Connection and Purge Now controls
- Recent activity panel

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate **Cloudflare Cirino**.
3. Go to **Tools > Cloudflare Cirino**.
4. Configure Zone ID and credentials.
5. Save and run **Test Connection**.

== Configuration ==
1. Enter Zone ID.
2. Choose authentication mode.
3. Choose purge mode.
4. Save settings.

== Frequently Asked Questions ==
= Which authentication method should I use? =
Use API Token mode. Legacy email + global API key is supported only for backward compatibility.

= What permission does the token need? =
At minimum, Zone-level cache purge permission for the selected zone.

= Does this purge on first publish? =
Yes. First publish, public updates, and status transitions affecting public content are handled.

= What does manual targeted purge include? =
It purges home URL and relevant public archive URLs using the currently selected zone.

= Are credentials shown in admin after saving? =
Token and legacy key fields are rendered empty on subsequent loads. Leaving them blank keeps the existing saved values.

== Changelog ==

= 1.0.1 =
* Fixed activity log ordering.
* Fixed attachment filtering in supported post types.
* Skips delete-trigger purge when the deleted post is not public.
* Consolidated targeted purge request logic.
* Added uninstall cleanup for transient admin notice.
* Refined admin UI styling for clearer WordPress-native presentation.

= 1.0.0 =
* Initial public release.
