# Accessibility

Since 2.10.0 all display styles (shortcode, Gutenberg blocks and Elementor
widgets) are built to meet WCAG 2.1 AA.

## What the plugin does

| Area | Behaviour |
|---|---|
| Tables | Column headers use `<th scope="col">`. The product name (or billing cycle / TLD) is the row header, `<th scope="row">`, so screen readers announce "Plan 1, Price, 99 kr" instead of a bare number. |
| Cards | Each card is an `<article>` labelled by its title (`aria-labelledby`). |
| Pricing grid | Each product is a `role="group"` named after the product. |
| Domain badges | A labelled list (".se domain prices") of register / renew / transfer items. |
| Per-period prices | Abbreviations like "/yr" and "/mo" are hidden from screen readers, which hear "959 kr per year, equivalent to 79.92 kr per month". |
| Colour contrast | All text meets AA (4.5:1) on light and dark themes. Tables, cards and grids have their own white background and dark text. Inline and badge-heading text follows the theme's text colour. |
| Motion | Hover animations are switched off for visitors who prefer reduced motion. |
| Keyboard | Links in plugin output (e.g. promo notices) get a visible focus outline. |

Checked with axe-core (WCAG 2.0/2.1 A and AA plus best practices) in Chrome on
light and dark backgrounds, and with Lighthouse: accessibility score 100 on every
display style. The markup is covered by `tests/Unit/MarkupAccessibilityTest.php`.
That test can write every style as a standalone page for your own checks:

```bash
mkdir -p /tmp/a11y && WHMCS_PRICE_A11Y_DUMP=/tmp/a11y vendor/bin/phpunit --filter MarkupAccessibilityTest
```

## Card heading level

Card titles are `<h3>` by default, which fits a pricing section under an `<h2>`.
If your page outline is different, change it so headings don't skip levels:

```php
add_filter( 'whmcs_price_card_heading_level', static fn() => 2 ); // 2–6
```

## Custom colours

Text colours chosen in the block sidebar (or in `theme.json`) are applied to the
whole display, including table cells and card text. If you pick a light text
colour, also pick a matching background, because tables and cards keep their
white background.

## Screen-reader text

Hidden text uses WordPress's standard `.screen-reader-text` class plus the
plugin's own `.whmcs-price-sr`. The plugin's stylesheet defines the latter, so it
stays hidden even if your theme doesn't style `.screen-reader-text`.
