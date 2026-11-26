# Instant price updates from WHMCS (webhook)

Since 2.10.0, WHMCS can tell your WordPress site the moment a price changes,
so visitors see new prices right away instead of after the cache expires.

## Setup (2 minutes)

1. **WordPress:** go to **Settings → WHMCS Price → Advanced** and set a
   **Cache Purge Token** (at least 16 characters). Copy it.
2. **WHMCS:** download [`whmcs-hook/mornolink_whmcs_price.php`](whmcs-hook/mornolink_whmcs_price.php)
   and copy it to `<your WHMCS>/includes/hooks/`.
3. Open the file and replace the example line with your site URL and the token:

   ```php
   'https://www.example.com' => 'paste-your-cache-purge-token-here',
   ```

   If several WordPress sites show prices from the same WHMCS, add one line per site.

That's it. WHMCS loads files in `includes/hooks/` automatically.

## What triggers an update

| WHMCS event | Hook | What is cleared on WordPress |
|---|---|---|
| A product is saved (including its pricing) | `ProductEdit` | Name, description, price and setup fee of that product, for all billing cycles |
| A product is deleted | `ProductDelete` | Same as above |
| Domain pricing for a TLD is saved | `TopLevelDomainPricingUpdate` | Register, renew and transfer prices for that TLD (all periods), and the all-domains table |
| Daily WHMCS cron | `DailyCronJob` | Everything (full purge) |

`TopLevelDomainPricingUpdate` only fires when pricing is saved by hand in the
WHMCS admin area. Prices changed by a registrar price sync or a currency rate
update don't fire it, so the daily full purge catches those. Remove the
`DailyCronJob` block from the hook file if you don't want it.

Prices that visitors view are re-fetched in the background right after they are
cleared, so the next visitor gets the new price straight from the cache.

## Checking that it works

- Save a product in WHMCS, then reload a WordPress page showing its price.
- Failures are written to the **WHMCS Activity Log** ("Mornolink for WHMCS: could
  not clear cached prices …"). They never block saving in WHMCS. The hook gives up
  after 5 seconds.
- Test from a shell:

  ```bash
  curl -X POST https://www.example.com/wp-json/whmcs-price/v1/webhook/invalidate \
    -H 'Content-Type: application/json' \
    -H 'X-WHMCS-Price-Token: YOUR_TOKEN' \
    -d '{"scope":"product:5"}'
  # {"invalidated":true,"scope":"product:5","entries":4,"message":"Cache invalidated."}
  ```

  `entries` is the number of cached values that were removed. 0 means nothing for
  that product was cached yet, which is fine.

## Endpoint reference

`POST /wp-json/whmcs-price/v1/webhook/invalidate`

| | |
|---|---|
| Auth | Header `X-WHMCS-Price-Token`, the same Cache Purge Token as `/purge-cache` |
| Body | `{"scope": "<targets>"}`, JSON or form-encoded |
| Targets | `product:<pid>`, `domain:<tld>` (with or without dot), `all`. Comma-separate several, up to 50: `product:5,product:6,domain:se` |
| Default | `all` when `scope` is missing or empty |

| Status | Meaning |
|---|---|
| 200 | Done. Targeted: `{"invalidated":true,"scope":"product:5","entries":4}`. Full: `{"invalidated":true,"scope":"all","cache_version":7}` |
| 400 | Invalid scope |
| 401 | Missing or wrong token |
| 403 | No Cache Purge Token configured |
| 429 | Rate limited. Includes a `Retry-After` header |

**Rate limits.** A full purge (`all`) shares its cooldown with `/purge-cache`
(Settings → Advanced → Rate limiting). Targeted invalidations are cheap and WHMCS
may send many in a row during bulk edits, so they use a separate limit of 120
requests per minute per client instead (filter `whmcs_price_webhook_rate_limit`, 0
disables it). Failed token attempts are limited exactly like `/purge-cache`.
