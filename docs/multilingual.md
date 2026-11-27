# WPML and Polylang

Since 2.10.0 the plugin works with WPML and Polylang. Neither is required: with
no multilingual plugin everything behaves as before.

## Currency per language

WHMCS sends prices already formatted in a WHMCS currency, for example
`999,00 kr` or `€89.00`. The plugin doesn't reformat them. Instead, it can ask
WHMCS for a different currency depending on the page language.

1. In WHMCS, open **Configuration → System Settings → Currencies** and note the ID
   of each currency. Hover the edit link to see it.
2. In WordPress, open **Settings → WHMCS Price → Connection → Currency per Language**.
   This section appears only when WPML or Polylang is active. Enter a currency ID
   per language, for example Svenska = 1 (SEK), English = 2 (EUR). Leave a
   language empty to use the WHMCS default currency.

The currency is chosen in this order:

1. **Set on the block or shortcode.** Block sidebar → *Currency* → *WHMCS currency
   ID*, or `[whmcs pid="1" bc="1y" currency="2"]`. Always wins, so one page can show
   several currencies side by side.
2. **Mapped to the page language** (the setting above).
3. **The WHMCS default currency.**

The same applies to the REST API (`?currency=2`, omit for automatic) and the
Abilities API (`"currency": 2`). Elementor widgets use the automatic
choice (2 then 3).

Each currency is cached separately, and the webhook clears every mapped currency
for a product or TLD. Prices in the WHMCS default currency use the same cache as
before, so upgrading doesn't empty your cache.

## Translating your own texts

The **fallback price** and the **promo notice text** you type in the settings are
registered with:

- **WPML:** WPML → String Translation, domain *Mornolink for WHMCS*.
- **Polylang:** Languages → Translations, group *Mornolink for WHMCS*.

Translate them there and visitors see the version for their language. All other
plugin texts (table headers, "per month", and so on) come from the normal plugin
translations on translate.wordpress.org.

## Filters

```php
// Choose the currency yourself, e.g. by country instead of language.
add_filter( 'whmcs_price_currency', function ( $currency_id, $lang ) {
	return 'en' === $lang ? 2 : $currency_id;
}, 10, 2 );

// Use another language source (e.g. TranslatePress, a custom cookie).
add_filter( 'whmcs_price_current_language', fn( $lang ) => my_language_code() );
```

## Why the plugin doesn't reformat prices per language

The original plan for this issue was to reformat prices for the active language
(decimal comma vs point, symbol position). That was dropped:

- WHMCS already formats each price exactly as configured for its currency.
  Reformatting means parsing a formatted string back into a number, and that
  guess can go wrong (is `1.200` one thousand two hundred, or 1.2?). A wrong
  price on a pricing page is worse than a differently formatted one.
- Language and currency are different things. An English page for Swedish
  customers should still show SEK. A currency mapping states that intent
  directly. A locale guess doesn't.
- Asking WHMCS for the currency means the price on your site matches what the
  customer later sees in the WHMCS cart.

The trade-off: you need to configure the mapping once, and each extra currency
means its own cached copy of each price (one more WHMCS request per price per
cache period).
