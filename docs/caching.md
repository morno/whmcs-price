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

## Cache warming (since 2.10.0)

Turn on **Settings → WHMCS Price → Performance → Cache Warming** to refresh
prices in the background *before* they expire. Visitors then always get a
fresh cached price, and WHMCS sees a steady trickle of requests instead of
bursts after every expiry.

How it works:

- The plugin keeps a list of the prices visitors have actually viewed. Only
  those are warmed, so a site that shows three products makes three requests,
  not hundreds.
- A WP-Cron job runs every 5 minutes, 15 minutes or hourly (your choice) and
  refreshes entries that expire before the next run, plus one minute of margin.
- Entries missing from the cache, for example after **Clear cache**, are
  fetched again by the next run.
- A price that no visitor has caused to be fetched for 7 days drops off the list.
  Warm-ups themselves don't count as use, so prices you remove from your pages
  stop being fetched. A price that is still on a page is put back on the list the
  next time it is served after expiring. With stale-while-revalidate the visitor
  doesn't notice that.
- At most 50 entries are refreshed per run. When more than 10 are due, requests
  are spaced one second apart.
- If WHMCS stops answering, the run stops after three failures in a row (or as
  soon as an outage alert has been sent) and continues on the next run.

Run it by hand with WP-CLI:

```bash
wp whmcs-price cache warm             # refresh every tracked price now
wp whmcs-price cache warm --due-only  # only what the scheduled job would refresh
wp whmcs-price cache status           # shows interval, next run and tracked entries
```

### Filters

| Filter | Default | Purpose |
|---|---|---|
| `whmcs_price_warm_threshold` | interval + 60 s | Refresh entries expiring within this many seconds. |
| `whmcs_price_warm_retention` | 7 days | Keep warming an entry this long after a visitor last caused a fetch. |
| `whmcs_price_warm_max_per_run` | 50 | Maximum refreshes per run; the rest wait for the next run. |
| `whmcs_price_warm_delay` | 1 s | Pause between requests when more than 10 are due. |
| `whmcs_price_warm_registry_size` | 500 | Maximum number of tracked prices; least recently used are dropped. |

## Multisite

On a Multisite network every site has its own WHMCS settings and its own cache:

- Cached prices are transients and object-cache entries, which WordPress already
  keeps per site (each site's own options table, and blog-prefixed cache groups).
  Site A can never read site B's cached prices, even if both show product 5.
- Clear cache, the cache version, the stale counter, the warm-up registry and the
  warm-up schedule are per site too. Purging one site doesn't touch the others.
- Since 2.10.0, code that switches sites within one request (`switch_to_blog()`,
  e.g. network dashboards or aggregation plugins) also gets correct prices. The
  plugin drops its in-memory request cache on every site switch, and usage and
  stale statistics are written to the site that produced them.

## Purging

**Clear cache** (admin bar, dashboard widget, settings page, `wp whmcs-price cache clear`
or `POST /wp-json/whmcs-price/v1/purge-cache`) bumps the cache version. That
discards both fresh and stale values, so the next visitor triggers a live fetch.
