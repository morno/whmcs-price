# Caching

Every WHMCS feed response (product name, description and price, domain prices,
the all-domains table and the product pricing feed used for setup fees and
billing cycles) is cached in the object cache and as a transient. The cache
duration is set under **Settings → WHMCS Price → Performance**.

## Stale-while-revalidate (since 2.10.0)

When a cached price passes its cache duration, it is not thrown away right away.
It stays stored for a **stale window**, by default one more cache duration:

| Age of the cached value | What a visitor gets |
|---|---|
| Younger than the cache duration | The cached value. |
| Older, but within the stale window | The cached (stale) value **immediately**, and a background refresh is scheduled through WP-Cron. |
| Older than the stale window, or nothing cached | A live fetch from WHMCS, as before. |

This means:

- Visitors don't wait for WHMCS when a cache entry expires.
- Visitors don't see "NA" while another request is already fetching the same price.
- If WHMCS is unreachable when the background refresh runs, the last known price
  keeps being shown until the stale window ends.

The background refresh runs the same code as a normal fetch, so it uses the same
fetch lock, outage e-mail and filters.

### Tuning the stale window

```php
// Allow prices up to 6 hours old while a refresh is pending.
add_filter( 'whmcs_price_max_stale_age', static fn( $max_age, $ttl ) => 6 * HOUR_IN_SECONDS, 10, 2 );

// Disable stale serving entirely (always fetch live after expiry).
add_filter( 'whmcs_price_max_stale_age', static fn( $max_age, $ttl ) => $ttl, 10, 2 );
```

The value is the maximum total age of a served price, counted from when it was
fetched. It is never shorter than the cache duration.

### WP-Cron

Background refreshes are one-off WP-Cron events (`whmcs_price_refresh_entry`).
If you run WP-Cron from a system cron (`DISABLE_WP_CRON`), refreshes happen on
the next system cron run. If that runs less often than the stale window lasts,
some visitors will get a live fetch after the window ends. That is the same
behaviour as before 2.10.0.

### Monitoring

The dashboard widget and `wp whmcs-price cache status` show how many stale
responses have been served and when the last one was. A steadily growing number
with a recent timestamp is normal. If the number grows while prices never
change, check that WP-Cron is running.

## Purging

**Clear cache** (admin bar, dashboard widget, settings page, `wp whmcs-price cache clear`
or `POST /wp-json/whmcs-price/v1/purge-cache`) bumps the cache version. That
discards both fresh and stale values, so the next visitor triggers a live fetch.
