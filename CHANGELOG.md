# Changelog

All notable changes to this project are documented in this file.

## [1.1.0] - 2026-05-19

### Added
- Optional Cloudflare Cache Rules setup for WordPress full-page edge caching via the Rulesets API.
- Managed rule application for the `http_request_cache_settings` phase using plugin-owned rule descriptions.
- Cache rules hostname and preset settings, runtime status tracking, and admin activity logging.

### Changed
- Expanded the admin screen and documentation to cover opt-in cache rule management and required token permissions.

## [1.0.1] - 2026-04-02

### Changed
- Refined admin documentation and repository presentation for public usage.
- Improved validation handling for legacy email input.
- Updated WordPress readme content for behavior clarity.

### Fixed
- Activity log ordering and supported post type filtering improvements from the stabilization pass.
- Reduced purge noise by skipping non-public permanent deletes.

## [1.0.0] - 2026-03-31

### Added
- Initial public release.
- Cloudflare API integration with token and legacy authentication modes.
- Targeted and full-zone purge strategies.
- Native WordPress admin settings page with manual test and purge actions.
- Runtime status and recent activity logging.
