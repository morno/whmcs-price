===WHMCS Price===
Contributors: morno, kamalireal
Tags: whmcs, price, show, billing, automation, hosting, dynamic, extract, product, domain
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Dynamic way for extracting product & domain price from WHMCS.

== Description ==
A high-performance WordPress integration for WHMCS. Display real-time prices for your hosting products and domains anywhere on your site using simple shortcodes.

* **Secure Data Handling:** Uses official WHMCS data feeds.
* **Performance Optimized:** Built-in caching using the WordPress Transients API.
* **Developer Friendly:** Fully localized and translation-ready.

== 100% FREE And Open Source ! ==

**WHMCS Price**
Dynamic way for extracting product & domain price from WHMCS for use on the pages of your website!

Plugin features:
* Extract product price.
* Extract domain price.
* Use this plugin to Show price in posts and pages.
* Use this plugin to Show price in theme.

== Persian Document ==
*  برای مشاهده راهنمای فارسی از این پلاگین [اینجا کلیک کنید](https://blog.iranwebsv.net/whmcs_price)

== Product Pricing ==
This is the shortcode to extract product name, description, or price:

`[whmcs pid="1" show="name,description,price" bc="1m"]`

1. **pid**: Change the value with your Product ID from WHMCS.
2. **show**: Choose what to display (name, description, price). You can use one or all, comma-separated.
3. **bc**: Change the value with your Product Billing Cycle:
    * Monthly (1 Month) : `bc="1m"`
    * Quarterly (3 Month) : `bc="3m"`
    * Semiannually (6 Month) : `bc="6m"`
    * Annually (1 Year) : `bc="1y"`
    * Biennially (2 Year) : `bc="2y"`
    * Triennially (3 Year) : `bc="3y"`

== Domain Pricing ==
This is the shortcode to extract domain registration, renewal, or transfer prices:

`[whmcs tld="com" type="register" reg="1y"]`

1. **tld**: Change the value with your Domain TLD (com, org, net, etc.).
2. **type**: Choose between `register`, `renew`, or `transfer`.
3. **reg**: Change the value with the Registration Period:
    * Annually (1 Year) : `reg="1y"`
    * Biennially (2 Year) : `reg="2y"`
    * Triennially (3 Year) : `reg="3y"`
    * ... (up to 10 years: `reg="10y"`)
4. If left like this `[whmcs tld]` it will call without any Domain TLD and will take all the TLDs that are in WHMCS.

== Installation ==
1. Upload `whmcs_price` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > WHMCS Price Options and save WHMCS URL.

== Changelog ==
= 2.2.2 =
* Fixed: A typo in the domain price shortcode output that could trigger PHP notices.
* Updated: Swedish language files in the languages/ directory.
* Changed: Updated "Tested up to" value to 6.7 to comply with WordPress.org guidelines.
* Changed: Refactored readme.txt headers for better compatibility with the WordPress.org parser.
* Changed: Added explicit GPLv2 license declaration to README.md.
* Removed: The /assets/ directory from the plugin distribution (moved to SVN assets).

= 2.2.1 =
* Added: Redirect-safe cache clearing from the Admin Bar (prevents double execution).
* Added: Success notices when clearing cache via the Admin Bar.
* Fixed: Duplicate HTML id and name attributes in the settings page.
* Fixed: Minor PHPDoc alignment for WordPress Coding Standards.

= 2.2.0 =
* Added: Complete PHPDoc documentation for all classes and functions.
* Added: New WHMCS_Price_API service class for centralized logic.
* Added: Official Swedish (sv_SE) translation and .pot template.
* Added: Security nonces for cache clearing actions.
* Added: Visual feedback (admin notices) when cache is cleared.
* Changed: Adopted Semantic Versioning (x.y.z).
* Changed: Modernized Settings UI with improved instructions.

= 2.1 =
* Initial refactor of the original plugin.
* Switched from direct SQL/Legacy API to WHMCS HTTP feeds.
* Added WordPress Transients API for caching.

= 1.3 =
* Fix Bug
= 1.2 =
* Fix Bug
= 1.1 =
* Fix Interference Bug
= 1.0 =
* First Version

== Screenshots ==
1. /assets/screenshot-1.jpg
2. /assets/screenshot-2.jpg
3. /assets/screenshot-2.jpg
4. /assets/screenshot-3.jpg