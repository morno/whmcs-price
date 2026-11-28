# Billing period switcher

Since 2.10.1 the **Cards** and **Pricing Grid** styles can show a switcher that lets
visitors change billing cycle (for example monthly / annually) without reloading the
page.

## Block

1. Select the WHMCS Product Price block and choose the Cards or Pricing Grid style.
2. Under **Display Style**, turn on **Billing period switcher**.
3. Tick the cycles to offer (at least two).

The **Billing Cycle** under Product Settings is the cycle shown first. If it is not one
of the offered cycles, the first offered cycle is shown first.

## Elementor

The WHMCS Product Price widget has the same settings: choose Cards or Pricing Grid,
turn on **Billing period switcher** under Display Style and pick the cycles. The widget
renders through the block, so the result is identical
(see [page-builders.md](page-builders.md)).

## Shortcode

```text
[whmcs pid="1,2,3" bc="1y" show="name,price,setupfee" style="cards" toggle="1m,1y"]
```

| Attribute | Value |
|---|---|
| `toggle` | Comma-separated cycles: `1m`, `3m`, `6m`, `1y`, `2y`, `3y`. `toggle="1"` means `1m,1y`. |
| `bc` | Cycle shown first. |
| `style` | `cards` or `grid`. The table style ignores `toggle`. |

The order you type the cycles in does not matter: the switcher always lists the shortest
cycle first.

## How it works

- The server renders the price and setup fee for **every** offered cycle, each in a
  `<span data-whmcs-cycle="1m">`. They come from the normal price cache, so switching
  never calls WHMCS or the REST API. The first page view after the cache expires fetches
  one price per product and cycle from WHMCS, like any other price.
- The wrapper has `data-whmcs-selected="1y"`, and the stylesheet hides the spans of every
  other cycle. The hidden prices are also hidden from screen readers.
- A small [Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/)
  script module (`assets/js/cycle-toggle.js`, store `whmcs-price/cycle-toggle`) shows the
  switcher and updates `data-whmcs-selected` when a visitor picks a cycle. It is only
  loaded on pages that use the switcher.

## Accessibility

- The switcher is a group of native radio buttons with the legend "Billing period", so
  Tab moves into it and the arrow keys change cycle.
- After a change, a polite live region announces the new cycle, for example
  "Showing monthly prices."
- Keyboard focus is visible, the selected option stays visible in Windows high contrast
  mode, and transitions are disabled with `prefers-reduced-motion`.

## Without JavaScript or on older WordPress

- Without JavaScript the switcher stays hidden and the first cycle is shown, exactly as
  without the switcher.
- Script modules need WordPress 6.5. On 6.4 the switcher is not shown and the block
  renders the configured cycle as before.

## Styling

| Selector | Element |
|---|---|
| `.whmcs-cycle-toggle` | The switcher (pill-shaped container). |
| `.whmcs-cycle-toggle__label` | One option. |
| `.whmcs-cycle-toggle__input:checked + .whmcs-cycle-toggle__label` | The selected option. |
