# Changelog

All notable changes to this project will be documented in this file.

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