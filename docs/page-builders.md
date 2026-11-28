# Page builder integrations

Every page builder integration must look and behave **exactly** like the Gutenberg
blocks. Same settings, same defaults, same HTML, same features.

## The rule: render through the block

A builder widget does not render prices itself. It only:

1. Registers one control per block attribute in `blocks/*/block.json`, with the same
   labels, options and defaults as the block's sidebar.
2. Maps its settings to block attributes in a public `to_block_attributes( array $settings )`
   method.
3. Renders with `whmcs_price_render_block( 'product' | 'domain', $attributes )`.

`whmcs_price_render_block()` (in `includes/builders.php`) runs the real block through
`render_block()`. The block's `render.php` is the only renderer, so a new block feature
(such as the billing period switcher) reaches every builder automatically. The builder
only needs a control for any new attribute.

The block's stylesheet and the switcher's script module are enqueued by `render_block()`
under the block's own handles, so they are never loaded twice.

## Shared option lists

`whmcs_price_builder_options()` returns the option lists the block editor uses:
billing cycles, product fields, display styles, per-period options, transaction types and
registration periods. Use them for select controls instead of writing new lists.
`whmcs_price_builder_currency()` cleans a currency ID control value.

## The parity test

`tests/Unit/BuilderParityTest.php` checks every widget listed in `builder_widgets()`:

- every block attribute (except the inserter-only `preview`) is mapped, and nothing else;
- a freshly added widget gives the same attributes as a freshly inserted block;
- mapped values have the type block.json declares;
- `render()` calls the block with exactly those attributes.

Adding an attribute to block.json without a control in every builder makes the test fail.
When adding a new builder, add its widgets to `builder_widgets()`.

## Editor previews

Builder editors render widgets without running the switcher's script module. The
product stylesheet shows the switcher in the block editor and in the Elementor editor
(`.elementor-editor-active`), so the author sees it. Add the editor's body or wrapper
class to that rule when adding a builder.

## Elementor

`includes/elementor/widgets/product-price-widget.php` and `domain-price-widget.php`
follow the rule above. Since 2.10.1 they also have the Currency setting and the billing
period switcher, and render byte for byte the same HTML as the blocks.
