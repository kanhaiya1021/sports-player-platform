# Sports Player Platform implementation audit

Updated 26 November 2026. The review covered the implemented foundation, authentication, dashboard, and payment flows against the professional build plan, plus the existing public REST routes and profile moderation.

## Fixed

| Area | Finding and resolution |
| --- | --- |
| Activation and upgrades | The bootstrap required a Composer loader even though the plugin has no runtime Composer dependencies. It now runs directly on PHP 8.2. WordPress schema migrations can now update existing tables. Activation creates the 11 planned pages, and an admin visit provisions missing pages on existing installations. Pages found at the planned paths retain their existing content. |
| Authentication | WordPress password authentication now enforces SPP email verification. Registration, login, and reset use the original unslashed password. Dashboard redirects and links resolve WordPress page permalinks. |
| Player data and moderation | Public REST results include basic fields from published, non-password-protected player posts. Team reads require login and writes require administrator capability. Moderation requires publish permission and permission to edit the specific pending player post. Rejected posts remain editable through the owner's dashboard. |
| Dashboard and shortcodes | Coaches and parents can save published players from the directory and manage the shortlist in their dashboard. The articles shortcode accepts WordPress attributes, and the events shortcode reads the URL saved in plugin settings. |
| Membership and billing | Free and expired memberships are denied paid access. Checkout requires an enabled and configured gateway. Stripe verifies webhook signatures, grants or renews access from paid subscription invoices, and pauses on failed payments. PayPal uses recurring subscriptions with webhook verification. Gateway cancellation runs before local cancellation. Provider event IDs prevent duplicate payment history entries. |
| Messaging and pricing | Conversation reads are scoped to their participants and the dashboard enforces paid access for eligible roles. Pricing lists the available inbox access. Compose, message limits, and other unfinished paid benefits have been removed from the purchase promises. |

## Payment configuration and verification

- **Stripe** uses the WordPress HTTP API with hosted Checkout. Register the site's `/wp-json/spp/v1/stripe-webhook` endpoint for `invoice.paid`, `invoice.payment_failed`, and `customer.subscription.deleted`. Use its signing secret and an API key from the same Stripe environment. [Stripe subscription webhooks](https://docs.stripe.com/billing/subscriptions/webhooks)
- **PayPal** requires the REST app client ID and secret, the webhook ID, and matching active recurring billing plans for USD 29 monthly and USD 249 annually. Configure a single regular billing cycle per plan with no trial or setup fee. Register `/wp-json/spp/v1/paypal-webhook` for `PAYMENT.SALE.COMPLETED`, `BILLING.SUBSCRIPTION.PAYMENT.FAILED`, `BILLING.SUBSCRIPTION.SUSPENDED`, `BILLING.SUBSCRIPTION.CANCELLED`, and `BILLING.SUBSCRIPTION.EXPIRED`. [PayPal subscription events](https://developer.paypal.com/docs/subscriptions/reference/webhooks/) · [PayPal webhook verification](https://developer.paypal.com/api/rest/webhooks/rest/)
- Review any membership records created by the previous implementation against provider records. Legacy PayPal checkout used one-time orders. Stripe renewals and cancellation can look up legacy subscription IDs stored in user meta.
- Database and application integration checks ran against an isolated WordPress database with PHP 8.2. Checks covered activation, page preservation, schema upgrade, verification and passwords, shortlist and content shortcodes, membership expiry, REST access, profile moderation, messaging isolation, and mocked gateway activation, renewal, retries, and cancellation. External gateway sandbox sessions and email delivery remain unverified.

## Remaining build-plan work

- Complete messaging compose and reply, quotas, notifications, recruiting list management and export, and paid contact access.
- Complete the expanded public player and team presentation, profile-view reporting, admin reporting, transactional email infrastructure, and broader SEO work.
- Validate real Stripe Test Mode and PayPal Sandbox checkout end to end before enabling a live gateway. Verify payment delivery, renewals, and cancellation with each provider.
