# Mornolink for WHMCS 🚀

![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D%206.4-blue.svg)
![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-8892bf.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-orange.svg)
![Version](https://img.shields.io/badge/version-2.8.0-brightgreen.svg)
![Maintained](https://img.shields.io/badge/maintained-yes-brightgreen.svg)
[![WordPress.org](https://img.shields.io/badge/WordPress.org-whmcs--price-blue.svg)](https://wordpress.org/plugins/whmcs-price/)

A modernized, secure, and lightweight WordPress plugin to display real-time pricing for products and domains from your WHMCS instance.

---

## 📖 Documentation

Full documentation is available in the [GitHub Wiki](https://github.com/morno/whmcs-price/wiki), including:

- [Getting Started](https://github.com/morno/whmcs-price/wiki/Getting-Started)
- [Installation & Configuration](https://github.com/morno/whmcs-price/wiki/Installation)
- [Product Pricing — Shortcode, Block & Elementor](https://github.com/morno/whmcs-price/wiki/Product-Pricing)
- [Domain Pricing — Shortcode, Block & Elementor](https://github.com/morno/whmcs-price/wiki/Domain-Pricing)
- [Setup Fee](https://github.com/morno/whmcs-price/wiki/Setup-Fee)
- [Per‑Period Price Breakdown](https://github.com/morno/whmcs-price/wiki/Per-Period-Breakdown)
- [Caching](https://github.com/morno/whmcs-price/wiki/Caching)
- [REST API](https://github.com/morno/whmcs-price/wiki/REST-API)
- [Security](https://github.com/morno/whmcs-price/wiki/Security)
- [Localization](https://github.com/morno/whmcs-price/wiki/Localization)
- [For Developers](https://github.com/morno/whmcs-price/wiki/For-Developers)

---

## ✨ Features

### Gutenberg (Block Editor)

Native WordPress block support — no shortcodes needed. Add the **WHMCS Product Price** or **WHMCS Domain Price** block directly from the block inserter and configure everything from the sidebar.

- Product block: set Product ID(s), billing cycle, display columns (name, description, price, setup fee), and per-period breakdown
- Domain block: set TLD, transaction type (register, renew, transfer), and registration period
- Both blocks use server-side rendering and inherit full transient caching automatically
- Three display styles: **Table**, **Cards**, and **Grid**
- **Pattern Overrides** (WordPress 7.0): use blocks inside synced patterns with per-instance product or domain overrides via the Block Bindings API

### Elementor

Dedicated Elementor widgets available under the **WHMCS Price** category in the widget panel.

- **Product Price Widget:** Visual controls for Product ID(s), billing cycle, display columns (including setup fee), per-period breakdown, and display style
- **Domain Price Widget:** Visual controls for TLD, registration period, transaction type, and display style
- Live preview in the Elementor editor

### Shortcodes

Fully supported for use in the classic editor, theme files, and any page builder. See the [wiki](https://github.com/morno/whmcs-price/wiki) for full reference.

### REST API

Read-only JSON endpoints for headless WordPress and JavaScript price loaders — served from the same cache as blocks and shortcodes. See the [REST API wiki page](https://github.com/morno/whmcs-price/wiki/REST-API) for full documentation.

### WP-CLI

Manage the cache and fetch prices from the command line. See the [For Developers wiki page](https://github.com/morno/whmcs-price/wiki/For-Developers) for available commands.

---

## 🛡 Security

- SSRF protection with DNS rebinding checks
- Strict output escaping on all WHMCS data
- Input allowlists on all shortcode, block, and REST API parameters
- HTTPS enforced for all WHMCS connections
- Cache stampede protection via lock transients

See the [Security wiki page](https://github.com/morno/whmcs-price/wiki/Security) for full details.

---

## 🛠 Installation

1. Download the latest ZIP from the [Releases page](https://github.com/morno/whmcs-price/releases) or install directly from [WordPress.org](https://wordpress.org/plugins/whmcs-price/)
2. Activate the plugin in the WordPress admin
3. Go to **Settings → WHMCS Price Settings** and enter your WHMCS URL (HTTPS required)
4. Use a shortcode, block, or Elementor widget to display pricing

See the [Installation page](https://github.com/morno/whmcs-price/wiki/Installation) for full details.

---

## 👨‍💻 Maintainer

**Tobias Sörensson (Morno)** — [github.com/morno](https://github.com/morno)

Originally developed by MohammadReza Kamali. Now maintained and actively developed as the official plugin on WordPress.org.

---

## 📜 License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
