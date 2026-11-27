# Block patterns

Since 2.10.0 the block inserter has a **WHMCS Pricing** pattern category with
ready-made layouts. Open the inserter (+), choose **Patterns**, then **WHMCS Pricing**.

| Pattern | Layout |
|---|---|
| Pricing comparison (3 products) | Heading and intro text, three plans as cards with the price also shown per month. |
| Featured product | One highlighted plan in a narrow column with an **Order now** button. |
| Domain prices (6 TLDs) | Registration price badges for six extensions in two rows. |
| Domain price table (1 TLD) | Register, renew and transfer prices for one extension. |
| Hosting plan with domains | One plan card with three domain prices below it. |
| Monthly vs yearly price | The same product billed monthly and yearly side by side. |

All patterns use the plugin's own Product Price and Domain Price blocks plus
standard WordPress layout blocks (headings, columns, buttons). After inserting one:

1. **Product blocks** have no product ID yet. The editor shows "Please enter a
   Product ID", and nothing is shown on the site until you add one in the block
   sidebar. The comparison pattern takes several IDs separated by commas
   (`1,2,3`).
2. **Domain blocks** use example extensions (.com, .net, .org, .io, .eu, .se).
   Change them to the ones you sell.
3. **Delete the ✏️ note** at the top of the pattern. It is a normal paragraph and
   would show on your site if left in.
4. In **Featured product**, point the button at your WHMCS order page
   (for example `https://billing.example.com/cart.php?a=add&pid=5`).

Every pattern can be styled like any other content: the Product and Domain blocks
support colour, spacing and wide/full alignment.
