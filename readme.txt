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
Cloudflare Cirino keeps Cloudflare edge cache aligned with WordPress content changes.

When public content changes, the plugin runs one of two purge strategies:

* Targeted mode purges the post permalink plus related public URLs (home, archive pages, taxonomy terms).
* Purge Everything mode clears the full Cloudflare zone cache.

The plugin provides a native wp-admin screen under Tools with:

* Connection status and last operation signals
* API Token authentication (recommended)
* Legacy Email + Global API Key mode for compatibility
* Manual Test Connection and Purge Now actions
* Recent activity log for quick troubleshooting

Internally, the plugin uses WordPress HTTP API and option APIs only.

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
5. Run **Test Connection**.

== Screenshots ==
1. Settings overview
2. Connection test status
3. Recent activity log
4. Targeted vs Purge Everything mode

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

= Does this plugin skip autosaves and revisions? =
Yes. Autosaves, revisions, and non-public statuses are ignored to avoid unnecessary purge traffic.

== Changelog ==

= 1.0.1 =
* Fixed activity log ordering.
* Improved supported post type filtering.
* Skips purge on non-public permanent deletes.
* Improved settings validation and admin UX clarity.
* Updated documentation for public repository usage.

= 1.0.0 =
* Initial public release.
