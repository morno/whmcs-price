# Changelog

All notable changes to this project will be documented in this file.

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