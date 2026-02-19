# WHMCS Price Plugin Structure

## Directory Layout

```
whmcs-price/
├── blocks/                          # Gutenberg Blocks
│   ├── build/                       # Compiled JS & CSS (webpack output)
│   │   ├── whmcs-price-product.js
│   │   ├── whmcs-price-product.css
│   │   ├── whmcs-price-domain.js
│   │   └── whmcs-price-domain.css
│   ├── whmcs-price-product/         # Product Price Block
│   │   ├── block.json              # Block metadata
│   │   ├── index.js                # Entry point (registration)
│   │   ├── edit.js                 # Editor component
│   │   ├── render.php              # Server-side render
│   │   └── styles.css              # Frontend styles
│   └── whmcs-price-domain/          # Domain Price Block
│       ├── block.json
│       ├── index.js
│       ├── edit.js
│       ├── render.php
│       └── styles.css
│
├── includes/                        # Backend & Integrations
│   ├── class-whmcs-api.php         # Core API class (WHMCS communication)
│   ├── class-whmcs-blocks.php      # Block registration
│   ├── settings.php                # Admin settings page
│   │
│   ├── shortcodes/                  # Shortcode integration
│   │   └── shortcode.php           # [whmcs] shortcode handler
│   │
│   └── elementor/                   # Elementor integration
│       ├── class-whmcs-elementor.php  # Widget registration & CSS enqueue
│       └── widgets/
│           ├── product-price-widget.php
│           └── domain-price-widget.php
│
├── languages/                       # Translation files
│   └── whmcs-price.pot
│
├── whmcs_price.php                  # Main plugin file
├── package.json                     # NPM dependencies
├── webpack.config.js                # Webpack build config
├── CHANGELOG.md
├── README.md
└── readme.txt                       # WordPress.org readme

```

## Philosophy

### `blocks/`
- **Purpose**: Gutenberg block source files and compiled assets
- **Contains**: React components, block.json metadata, server-side render templates, CSS
- **Build**: Compiled by webpack (`npm run build`)

### `includes/`
- **Purpose**: Backend logic and frontend integrations
- **Contains**:
  - **Core**: API communication, settings, block registration
  - **Integrations**: Shortcodes, Elementor widgets

### Shared Logic
All frontend integrations (blocks, Elementor, shortcodes) reuse:
- `WHMCS_Price_API` class for data fetching
- Compiled CSS from `blocks/build/` for consistent styling
- No code duplication between integrations

## Build Process

```bash
npm install          # Install dependencies
npm run build        # Compile blocks → blocks/build/
npm run start        # Watch mode for development
```

## Integration Points

1. **Gutenberg**: `includes/class-whmcs-blocks.php` registers blocks from `blocks/`
2. **Shortcodes**: `includes/shortcodes/shortcode.php` handles `[whmcs]`
3. **Elementor**: `includes/elementor/class-whmcs-elementor.php` registers widgets (only if Elementor active)

All three share the same backend API and CSS.
