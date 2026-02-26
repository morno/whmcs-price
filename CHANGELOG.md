# Changelog

All notable changes to this project will be documented in this file.

### Fixed

- **Author name encoding**: Corrected double-encoded UTF-8 characters in the plugin
  header of `whmcs_price.php`. The author name `Sörensson` was stored as mojibake
  (`SÃ¶rensson`) due to a file being re-encoded by an external tool or editor.

- **Misplaced docblock**: The `clean_response()` method in `class-whmcs-api.php` was
  missing its own docblock, and an orphaned docblock originally written for it had
  ended up placed above `get_request_args()` instead. Both methods now have correct,
  properly placed documentation.

- **TLD max length**: Added a `substr()` cap of 24 characters to the TLD value in
  the `[whmcs]` shortcode handler, preventing excessively long strings from being
  passed to the API or generating oversized cache keys.

### Changed

- **Shortcode function renamed**: `whmcs_func()` renamed to
  `whmcs_price_shortcode_handler()` to follow WordPress coding standards requiring
  all global functions to use a unique plugin prefix.

- **Blocks file renamed**: `includes/class-whmcs-blocks.php` renamed to
  `includes/blocks.php`. The file contains only a hooked function — not a class —
  so the `class-` prefix was misleading and against WordPress naming conventions.
  The `require_once` reference in `whmcs_price.php` updated accordingly.

- **License unified**: `includes/settings.php` had `GPL-3.0-or-later` in its file
  header, inconsistent with the rest of the plugin. Updated to `GPLv2 or later`
  with the correct license URI to match the main plugin header and `readme.txt`.

### Added

- **`uninstall.php`**: Added a proper uninstall routine that removes all plugin data
  from the database when the plugin is deleted via the WordPress admin. Previously,
  data was left behind after deletion. The plugin stores the following in `wp_options`:
  - `whmcs_price_option` — plugin settings (WHMCS URL, cache TTL, custom User-Agent)
  - `_transient_whmcs_product_*` — cached product prices (name, description, price)
  - `_transient_whmcs_domain_*` — cached domain prices per TLD/type/period
  - `_transient_whmcs_domain_all` — cached full TLD price list
  - `_transient_lock_whmcs_*` — short-lived stampede-prevention locks (10 s TTL)

## [2.5.0] - 2026-02-26

### Added

- **Custom User-Agent setting**: A new "Custom User-Agent" field has been added to
  the plugin settings page. When filled in, this value overrides the auto-generated
  User-Agent for all HTTP requests to WHMCS. Useful for matching server or firewall
  allow-rules, or for identifying plugin requests in access logs.
  Leave the field blank to use the default auto-generated string.

- **Hybrid User-Agent + explicit timeout**: All HTTP requests to WHMCS now send a
  descriptive User-Agent header and have an explicit 15-second timeout.

### Changed

- **Default User-Agent format**: WordPress version number removed to avoid exposing
  version info. New format: `WordPress (https://yoursite.com) whmcs-price/2.5.0`

## [2.4.7] - 2026-02-25

### Fixed

- **Slow Save with Shortcodes**: The `[whmcs]` shortcode ran via the `the_content`
  filter on every Gutenberg REST save request, triggering live HTTP calls to WHMCS
  for each shortcode on the page. Added the same `REST_REQUEST` / `DOING_AUTOSAVE`
  early return as in `render.php`, returning a lightweight HTML comment instead.
  Frontend page loads are unaffected.

## [2.4.6] - 2026-02-25

### Fixed

- **Slow Gutenberg Save**: When saving a post in the block editor, WordPress fires
  the `the_content` filter via a REST API request, which triggered server-side
  rendering of both WHMCS blocks. This caused live HTTP requests to the WHMCS server
  on every save — even though the result is never shown in the editor. Added an early
  exit in both `render.php` files when `REST_REQUEST` or `DOING_AUTOSAVE` is defined,
  returning a lightweight HTML comment instead. The real data continues to render
  correctly on frontend page loads.

## [2.4.5] - 2026-02-25

### Fixed

- **Gutenberg Editor Crash**: Both Gutenberg blocks (`whmcs-price/product` and
  `whmcs-price/domain`) were missing a `save` function in their `registerBlockType`
  call. Without `save: () => null`, WordPress attempts to serialize the block's
  editor output as static HTML. On page reload, the editor compares the stored HTML
  against what `save()` returns — the mismatch triggers a block validation error that
  causes the editor to hang indefinitely on load. Added `save: () => null` to both
  `index.js` source files and their compiled counterparts in `blocks/build/` to
  correctly declare these as dynamic (server-side rendered) blocks.

## [2.4.4] - 2026-02-24

### Security

- **Query Injection Fix**: Replaced raw string concatenation for WHMCS API URLs with
  `add_query_arg()` in `get_product_data()` and `get_domain_price()`. Previously,
  unsanitized parameters could be used to inject additional query parameters into
  requests sent to the WHMCS feed endpoints.

- **Input Allowlists**: Added strict allowlist validation in `WHMCS_Price_API` for all
  parameters passed to WHMCS feed URLs. `$attribute` is restricted to `name`,
  `description`, `price`; `$billing_cycle` to the six known WHMCS cycle names;
  `$type` to `register`, `renew`, `transfer`; and `$reg_period` to integers 1–10.
  Requests with invalid values now return `'NA'` immediately without hitting WHMCS.

- **TLD Sanitization**: The `tld` shortcode attribute is now sanitized with
  `sanitize_text_field()` and stripped of all characters outside `[a-zA-Z0-9-]`
  before being passed to `WHMCS_Price_API::get_domain_price()`. Previously the raw
  unsanitized value was forwarded directly.

- **Shortcode Allowlists**: The `bc` (billing cycle), `show` (columns), and `type`
  (transaction type) shortcode attributes are now validated against allowlists before
  being used. Invalid `bc` or empty `show` after filtering causes the shortcode to
  return an empty string. Invalid `type` falls back to `register`.

- **SSRF Hardening**: Extended the SSRF protection in `get_url()` with a blocklist
  for known cloud metadata endpoints (`169.254.169.254`, `100.100.100.200`,
  `metadata.google.internal`, etc.) that can bypass IP-range checks via DNS.
  Additionally, the WHMCS URL is now required to use HTTPS; HTTP URLs are rejected
  entirely, preventing credential or data leakage over unencrypted connections.

### Fixed

- **Multisite Compatibility**: Removed the early `return` that caused the plugin
  settings page to be completely unavailable on WordPress Multisite installations.
  The settings page now loads correctly for all admin contexts, including network
  sites.

### Added

- **Directory Index Files**: Added `index.php` (silence is golden) to seven
  subdirectories that were missing them (`languages/`, `includes/elementor/`,
  `includes/elementor/widgets/`, `blocks/`, `blocks/whmcs-price-product/`,
  `blocks/whmcs-price-domain/`, `blocks/build/`), preventing directory listing
  on servers without `Options -Indexes`.

- **HTTPS Warning in Admin**: The settings page now displays a visible warning
  if the configured WHMCS URL does not use HTTPS, informing the admin that HTTP
  URLs are blocked by the plugin.

### Changed

- **Code Comment**: Added an explanatory comment to the `wp_kses_post()` call in
  the `[whmcs]` shortcode fallback (all-TLD output), documenting that the use of
  `wp_kses_post` rather than `esc_html` is intentional and by design.

## [2.4.3] - 2026-02-22

### Changed
- **Plugin renamed**: Changed display name from "WHMCS Price" to "Mornolink for WHMCS" to comply with WordPress.org trademark guidelines.
- **readme.txt**: Added `== Source Code ==` section with link to GitHub repository as required by WordPress.org.

### Removed
- **Persian documentation**: Removed link to Persian documentation from `readme.txt` as the hosting site (blog.iranwebsv.net) is no longer available.

## [2.4.2] - 2026-02-19

### Fixed
- **Escape Fix**: Applied `absint()` to integer output in `cache_ttl_callback()` in
  `settings.php` to satisfy WordPress escaping standards (PHPCS `WordPress.Security.EscapeOutput`).

## [2.4.1] - 2026-02-19

### Security
- **SSRF Protection**: Added validation in `get_url()` to block private IPv4/IPv6 ranges,
  reserved IP ranges, and localhost from being used as the WHMCS URL. Prevents
  server-side request forgery if an unauthorized party gains access to plugin settings.
- **XSS Fix**: Wrapped `get_all_domain_prices()` output in `wp_kses_post()` in the
  `[whmcs]` shortcode fallback. Previously unescaped HTML from the WHMCS feed could
  allow injected markup if the remote source was compromised.

### Changed
- **Cache Key Hardening**: Replaced interpolated cache keys with `md5()`-hashed keys
  in `get_product_data()` and `get_domain_price()` to prevent key collisions and
  oversized transient entries from arbitrary input values.
- **Configurable Cache TTL**: Admins can now set cache duration from the plugin settings
  page. Available options: 1, 2, 3, 6, 12, and 24 hours. Falls back to 1 hour if not
  configured. Added `get_cache_expiry()` method to `WHMCS_Price_API` to read the
  setting dynamically.

### Added
- **Cache Stampede Protection**: Added `acquire_lock()` method in `WHMCS_Price_API`
  using a short-lived transient lock (10 seconds). Prevents multiple simultaneous
  requests from hitting WHMCS when the cache is cold or has just been cleared.
  Applied to both `get_product_data()` and `get_domain_price()`. Locks are
  automatically released on success, HTTP error, or request failure.

## [2.4.0] - 2026-02-18

### Added - Elementor Integration
- **Elementor Product Price Widget**: Display WHMCS product pricing in Elementor with visual builder
  - Product IDs input (comma-separated)
  - Billing cycle dropdown selector
  - Display columns multi-select (name, description, price)
  - 3 display styles: Table, Cards, Pricing Grid
  - Live preview in Elementor editor
  
- **Elementor Domain Price Widget**: Display WHMCS domain pricing in Elementor
  - TLD input (leave empty for all TLDs)
  - Registration period selector (1-10 years)
  - "Show all transaction types" toggle
  - Transaction type selector (register, renew, transfer)
  - 3 display styles: Table, Badge, Inline
  - Live preview in Elementor editor

- **Custom Elementor Category**: "WHMCS Price" category in Elementor widget panel
- **Shared Styling**: Elementor widgets reuse block CSS for consistency

### Changed
- **File Structure**: Reorganized for better maintainability
  - Renamed `includes/short_code/` → `includes/shortcodes/`
  - Renamed `short_code.php` → `shortcode.php`
  - Added `includes/elementor/` for Elementor integration
  - Added `STRUCTURE.md` documentation

## [2.3.0] - 2026-02-16

### Added
- **Gutenberg Block: WHMCS Product Price** — Native block editor support for displaying real-time product pricing from WHMCS. Configured via the block sidebar (InspectorControls) with controls for Product ID(s), Billing Cycle, and display columns (Name, Description, Price).
- **Gutenberg Block: WHMCS Domain Price** — Native block editor support for displaying real-time domain pricing from WHMCS. Configured via the block sidebar with controls for TLD, Transaction Type (register, renew, transfer), and Registration Period (1–10 years).
- Both blocks use **server-side rendering** (`render.php`) and reuse the existing `WHMCS_Price_API` class — no logic duplication, full transient caching inherited automatically.
- `block.json` metadata files for both blocks following WordPress block API v3 standards.
- `class-whmcs-blocks.php` for block registration via `register_block_type()`.
- Editor preview shown in the block canvas when a Product ID or TLD has been configured.
- `Placeholder` component shown in the editor when the block has not yet been configured.

### Changed
- Updated `WHMCS_PRICE_VERSION` constant to `2.3.0`.
- Fixed author name encoding in plugin header (`Sörensson` was incorrectly stored as mojibake).
- Block registration uses `WHMCS_PRICE_DIR` constant consistently with the rest of the plugin.
- Changed Tags in readme.txt to the supported Tags of 5.

## [2.2.2] - 2026-02-13

### Fixed
- Fixed a typo in the domain price shortcode output that could trigger PHP notices by referencing an undefined variable.
- Updated Swedish language files in the `languages/` directory.

### Changed
- **WordPress Directory Compliance:** Updated "Tested up to" value to 6.7 to match the current stable WordPress release.
- **Readme Optimization:** Refactored `readme.txt` headers and spacing to ensure compatibility with the WordPress.org plugin parser.
- **License Clarification:** Added explicit GPLv2 license declaration to `README.md` as requested by the plugin review team.
- **Version Alignment:** Synchronized versioning (2.2.2) across all core files, `readme.txt`, and `README.md`.

### Removed
- Removed the `/assets/` directory from the plugin distribution (to be managed via SVN assets as per WordPress.org guidelines).

## [2.2.1] - 2026-02-10
### Added
- Implemented redirect-safe cache clearing from the Admin Bar (prevents double execution on page refresh).
- Added success notices when clearing cache via the Admin Bar.

### Fixed
- Resolved browser console warnings by fixing duplicate HTML `id` and `name` attributes in the settings page.
- Fixed minor PHPDoc alignment to better follow WordPress Coding Standards.

### Changed
- Updated plugin versioning to 2.2.1 across all core files.
- Improved shortcode usage examples for better clarity.

## [2.2.0] - 2026-02-10
### Added
- Complete **PHPDoc documentation** in English for all classes and functions following WordPress Coding Standards.
- New `WHMCS_Price_API` service class to centralize data fetching and caching logic.
- Support for translation via text-domain `whmcs-price` and `/languages` folder.
- **Official Swedish (sv_SE) translation** and `.pot` template for localization.
- Security nonces for cache clearing actions in both Admin Bar and Settings page.
- Visual feedback (admin notices) when cache is successfully cleared.

### Changed
- **Adopted Semantic Versioning (x.y.z)** to allow for easier minor updates and patches.
- Refactored shortcode logic for better performance and readability.
- Modernized the Settings UI with improved instructions and code examples.
- Updated minimum PHP requirement to 7.4 (recommended 8.0+) for better stability.
- Standardized file structure following WordPress Plugin Guidelines.

### Fixed
- Fixed issues with JS-feed cleaning where raw Javascript strings were sometimes displayed.
- Resolved potential conflicts by prefixing all functions and classes correctly.

## [2.1]
- Initial refactor of the original plugin.
- Switched from direct SQL/Legacy API to WHMCS JS Feeds (HTTP).
- Added WordPress Transients API for caching.