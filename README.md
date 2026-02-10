# WHMCS Price Integration 🚀

![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D%205.0-blue.svg)
![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-8892bf.svg)
![License](https://img.shields.io/badge/license-GPL--3.0-orange.svg)
![Maintained](https://img.shields.io/badge/maintained-yes-brightgreen.svg)

A modernized, secure, and lightweight WordPress plugin to display real-time pricing for products and domains from your WHMCS instance.

## 🌟 Why this version?

This is an updated and refactored version of the WHMCS Price plugin, maintained by **Tobias Sörensson (Morno)**. 

* **PHP 8.x Compatible:** Full support for modern hosting environments.
* **Safe Data Fetching:** Uses WHMCS data feeds (HTTP) via wp_remote_get instead of direct SQL connections, ensuring compatibility with all hosting providers.
* **Admin Bar Integration:** Clear your price cache instantly with one click from the WordPress top menu.

## 🌍 Localization
This plugin is translation-ready. If you want to contribute a translation:
1. Use the `languages/whmcs-price.pot` file as a template.
2. Create your `.po` and `.mo` files (e.g., `whmcs-price-sv_SE.po`).
3. Submit a Pull Request!

---

## 🛠 Installation

1.  Download the repository as a ZIP or clone it into `/wp-content/plugins/`.
2.  Activate the plugin in the WordPress Admin.
3.  Go to **Settings > WHMCS Price Settings** (or via the **Tools** menu).
4.  Enter your WHMCS URL (e.g., `https://yourbilling.com`).
    * *Note: Do not include a trailing slash at the end.*

---

## 📖 Usage & Shortcodes

### 📦 Product Pricing
Fetch name, description, or price for any product ID.

`[whmcs pid="1" show="name,description,price" bc="1y"]`

| Attribute | Options | Description |
| :--- | :--- | :--- |
| `pid` | *Integer* | The Product ID from WHMCS. |
| `show` | `name`, `description`, `price` | Comma-separated list of what to display. |
| `bc` | `1m`, `3m`, `6m`, `1y`, `2y`, `3y` | Billing Cycle (Monthly to Triennially). |

### 🌐 Domain Pricing
Display fees for registration, renewal, or transfers.

`[whmcs tld="com" type="register" reg="1y"]`

* **`tld`**: The extension (e.g., `net`, `se`, `com`). If left as `[whmcs tld]`, it fetches all available TLDs.
* **`type`**: `register`, `renew`, or `transfer`.
* **`reg`**: Period from `1y` up to `10y`.

---

## ⚡ Caching
To ensure maximum performance, the plugin uses **WordPress Transients** to cache pricing data. 

**How to refresh prices:**
- Go to the plugin settings and click **"Clear Cache"**.
- Use the **"Clear WHMCS Cache"** button in the WordPress Admin Bar (visible to admins).
- *Remember to clear your page cache (WP Rocket, Autoptimize, etc.) if the new prices don't show up immediately.*

---

## 📜 Project History & Status

This fork was created to resolve critical issues in the original plugin which had become incompatible with modern web environments.

* **Original Plugin:** [WHMCS Price on WordPress.org](https://wordpress.org/plugins/whmcs-price/)
* **The Problem:** Users reported the [plugin was no longer working](https://wordpress.org/support/topic/this-plugin-has-not-been-working-recently/) with recent PHP/WP updates.
* **The Solution:** I have taken over development in this repository. I have also reached out to the community to [officially become the maintainer](https://wordpress.org/support/topic/update-the-plugin-11/) on the WordPress.org repository.

---

## 👨‍💻 Developers
* **Maintained by:** [Tobias Sörensson (Morno)](https://github.com/morno)
* **Original Dev:** MohammadReza Kamali

---

## License
This project is licensed under the GPLv3 License.
