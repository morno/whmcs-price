# Mornolink for WHMCS 🚀

![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D%206.9-blue.svg)
![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-8892bf.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-orange.svg)
![Version](https://img.shields.io/badge/version-2.6.0-brightgreen.svg)
![Maintained](https://img.shields.io/badge/maintained-yes-brightgreen.svg)

A modernized, secure, and lightweight WordPress plugin to display real-time pricing for products and domains from your WHMCS instance.

---

## 📖 Documentation

Full documentation is available in the [GitHub Wiki](https://github.com/morno/whmcs-price/wiki), including:

- [Installation & Configuration](https://github.com/morno/whmcs-price/wiki/Installation)
- [Product Pricing — Shortcode, Block & Elementor](https://github.com/morno/whmcs-price/wiki/Product-Pricing)
- [Domain Pricing — Shortcode, Block & Elementor](https://github.com/morno/whmcs-price/wiki/Domain-Pricing)
- [Setup Fee](https://github.com/morno/whmcs-price/wiki/Setup-Fee)
- [Per‑Period Price Breakdown](https://github.com/morno/whmcs-price/wiki/Per-Period-Breakdown)
- [Caching](https://github.com/morno/whmcs-price/wiki/Caching)
- [Localization](https://github.com/morno/whmcs-price/wiki/Localization)

---

## 🌟 Why this version?

This is an updated and refactored version of the WHMCS Price plugin, maintained by **Tobias Sörensson (Morno)**.

- **PHP 8.1+ compatible:** Full support for modern hosting environments.
- **Safe data fetching:** Uses WHMCS data feeds via `wp_remote_get` instead of direct SQL connections, ensuring compatibility with all hosting providers.
- **Admin Bar integration:** Clear your price cache instantly with one click from the WordPress top menu.
- **Setup fee support:** Display one-time setup fees as a separate column alongside recurring prices.
- **Per-period breakdown:** Automatically show e.g. `999 Kr/yr (83 Kr/mo)` for any billing cycle.
- **Hardened security:** SSRF protection, strict output escaping, DNS rebinding checks, and allowlist validation throughout.

---

## ✨ Features

### Gutenberg (Block Editor)

Native WordPress block support — no shortcodes needed. Add the **WHMCS Product Price** or **WHMCS Domain Price** block directly from the block inserter and configure everything from the sidebar.

- Product block: set Product ID(s), billing cycle, display columns (name, description, price, setup fee), and per-period breakdown
- Domain block: set TLD, transaction type (register, renew, transfer), and registration period
- Both blocks use server-side rendering and inherit full transient caching automatically
- Supports three display styles: **Table**, **Cards**, and **Grid**

### Elementor

Dedicated Elementor widgets available under the **WHMCS Price** category in the widget panel.

- **Product Price Widget:** Visual controls for Product ID(s), billing cycle, display columns (including setup fee), per-period breakdown, and display style (Table, Cards, Pricing Grid)
- **Domain Price Widget:** Visual controls for TLD, registration period, transaction type, and display style (Table, Badge, Inline)
- Live preview in the Elementor editor

### Shortcodes

Still fully supported for use in the classic editor, theme files, and page builders without native widgets. See the [wiki](https://github.com/morno/whmcs-price/wiki) for full reference.

---

## 🛠 Installation

1.  Download the repository as a ZIP or clone it into `/wp-content/plugins/`.
2.  Activate the plugin in the WordPress Admin.
3. Go to **Settings → WHMCS Price Settings** and enter your WHMCS URL (HTTPS required).
4. Use a shortcode, block, or Elementor widget to display pricing.

See the [Installation page](https://github.com/morno/whmcs-price/wiki/Installation) in the wiki for full details.

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

