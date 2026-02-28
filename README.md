# Mornolink for WHMCS 🚀

![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D%206.9-blue.svg)
![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-8892bf.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-orange.svg)
![Version](https://img.shields.io/badge/version-2.5.5-brightgreen.svg)
![Maintained](https://img.shields.io/badge/maintained-yes-brightgreen.svg)

A modernized, secure, and lightweight WordPress plugin to display real-time pricing for products and domains from your WHMCS instance.

---

## 🌟 Why this version?

This is an updated and refactored version of the WHMCS Price plugin, maintained by **Tobias Sörensson (Morno)**. 

* **PHP 8.1+ Compatible:** Full support for modern hosting environments.
* **Safe Data Fetching:** Uses WHMCS data feeds (HTTP) via wp_remote_get instead of direct SQL connections, ensuring compatibility with all hosting providers.
* **Admin Bar Integration:** Clear your price cache instantly with one click from the WordPress top menu.

---

### Gutenberg (Block Editor)
Native WordPress block support — no shortcodes needed. Add the **WHMCS Product Price** or **WHMCS Domain Price** block directly from the block inserter and configure everything from the sidebar.

- Product block: set Product ID(s), billing cycle, and which columns to display (name, description, price)
- Domain block: set TLD, transaction type (register, renew, transfer), and registration period
- Both blocks use server-side rendering and inherit full transient caching automatically
- Supports three display styles: **Table**, **Cards**, and **Grid**

### Elementor
Dedicated Elementor widgets available under the **WHMCS Price** category in the widget panel.

- **Product Price Widget:** Visual controls for Product ID(s), billing cycle, and display style (Table, Cards, Pricing Grid)
- **Domain Price Widget:** Visual controls for TLD, registration period, transaction type, and display style (Table, Badge, Inline)
- Live preview in the Elementor editor

---

### Shortcodes
Still fully supported for use in classic editor, theme files, and page builders without native widgets. See the [Usage & Shortcodes](#-usage--shortcodes) section below.

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
    * *Note: HTTPS is required — HTTP URLs are blocked for security reasons.

---

## 📖 Usage & Shortcodes

### 📦 Product Pricing

Fetch name, description, or price for any product ID.

```
[whmcs pid="1" show="name,description,price" bc="1y"]
```

| Attribute | Options | Description |
|-----------|---------|-------------|
| `pid` | Integer | The Product ID from WHMCS. Supports comma-separated values for multiple products. |
| `show` | `name`, `description`, `price` | Comma-separated list of what to display. |
| `bc` | `1m`, `3m`, `6m`, `1y`, `2y`, `3y` | Billing Cycle (Monthly to Triennially). |

### 🌐 Domain Pricing

Display fees for registration, renewal, or transfers.

```
[whmcs tld="com" type="register" reg="1y"]
```

- `tld`: The extension (e.g., `net`, `se`, `com`). Leave empty as `[whmcs tld]` to fetch all available TLDs.
- `type`: `register`, `renew`, or `transfer`.
- `reg`: Period from `1y` up to `10y`.

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
* **The Solution:** I have taken over development in this repository. I have also reached out to the community to [officially become the maintainer](https://wordpress.org/support/topic/update-the-plugin-32/#post-17684480) on the WordPress.org repository.

---

## 👨‍💻 Developers
* **Maintained by:** [Tobias Sörensson (Morno)](https://github.com/morno)
* **Original Dev:** MohammadReza Kamali

---

## License
This plugin is licensed under the GPLv2 or later.








