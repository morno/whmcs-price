# Abilities API

Since 2.10.0, Mornolink for WHMCS registers two read-only abilities with the
[WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/)
(WordPress 6.9 and later). AI agents, MCP clients and other plugins can use them
to look up live WHMCS prices without scraping shortcodes or calling WHMCS directly.

Both abilities use the same cache as the shortcodes, blocks and REST endpoints,
so calling them does not add any extra load on WHMCS when the prices are already cached.

On WordPress versions without the Abilities API nothing is registered and nothing changes.

## Category

| Slug | Label |
|---|---|
| `whmcs-price` | WHMCS Pricing |

## `whmcs-price/get-product-price`

Name, description, recurring price and setup fee for one or more WHMCS products.

**Input**

| Field | Type | Required | Notes |
|---|---|---|---|
| `product_ids` | integer[] | yes | 1–20 WHMCS product IDs (`pid`). Duplicates are removed. |
| `billing_cycle` | string | no | `monthly` (default), `quarterly`, `semiannually`, `annually`, `biennially`, `triennially` |

**Output**

```json
{
  "billing_cycle": "annually",
  "products": [
    {
      "product_id": 1,
      "available": true,
      "name": "Web Hosting",
      "description": "10 GB SSD",
      "price": "999 kr",
      "setup_fee": "100 kr"
    }
  ]
}
```

Fields WHMCS cannot provide are `null`. `available` is `false` when the price
could not be fetched (WHMCS unreachable, unknown product, billing cycle not offered).
Prices are formatted strings exactly as WHMCS shows them, including currency.
HTML is stripped.

## `whmcs-price/get-domain-price`

Register, renew and transfer prices for a TLD.

**Input**

| Field | Type | Required | Notes |
|---|---|---|---|
| `tld` | string | yes | With or without leading dot, e.g. `se`, `.com`. Second-level TLDs such as `co.uk` are not supported yet (same as the shortcode). |
| `reg_period` | integer | no | Years, 1–10. Default 1. |
| `types` | string[] | no | Any of `register`, `renew`, `transfer`. Default: all three. |

**Output**

```json
{
  "tld": "se",
  "reg_period": 1,
  "available": true,
  "prices": {
    "register": "129 kr",
    "renew": "149 kr",
    "transfer": null
  }
}
```

## Permissions and rate limiting

Prices are public data, so both abilities are available to everyone, which is the same
policy as `GET /wp-json/whmcs-price/v1/product/{pid}` and `/domain/{tld}`.

When an ability is executed over REST, the plugin's REST read rate limit
(Settings → Advanced → Rate limiting) applies, sharing the same `product` and
`domain` buckets as the REST endpoints. In-process calls from PHP are not limited.

## Trying it out

```bash
# List the plugin's abilities
curl -s https://example.com/wp-json/wp-abilities/v1/abilities | jq '.[] | select(.category=="whmcs-price") | .name'
```

To execute an ability over REST, use the core run endpoint for the ability
(`/wp-abilities/v1/abilities/<name>/run`). See the
[Abilities API REST documentation](https://developer.wordpress.org/apis/abilities-api/)
for the HTTP method and input format your WordPress version expects. Both
abilities are annotated as read-only.

```php
// From PHP (another plugin, WP-CLI eval)
$ability = wp_get_ability( 'whmcs-price/get-product-price' );
$result  = $ability ? $ability->execute( array( 'product_ids' => array( 1, 2 ), 'billing_cycle' => 'annually' ) ) : null;
```

Depending on your WordPress version and site configuration, the core Abilities
REST endpoints may require an authenticated user even though these abilities
themselves are public.
