# XPayr Gateway for Zen Cart

Non-custodial crypto checkout module for Zen Cart stores.

## Features
- Hosted checkout via XPayr (`/pay/ps_xxx`)
- Creates payment session from Zen Cart order total
- Redirects customer to XPayr checkout after order placement
- Webhook callback with `X-XPayr-Signature` verification
- Order status mapping for `completed / failed / expired / pending`
- Stores session mapping in custom DB table `xpayr_transactions`

## Package structure
Upload the content of `upload/` into Zen Cart root:

- `includes/modules/payment/xpayr.php`
- `includes/languages/english/modules/payment/xpayr.php`
- `ipn_xpayr.php`
- `images/xpayr.png`

## Installation
1. Copy `upload/*` to your Zen Cart installation root.
2. In Admin Panel, go to `Modules > Payment`.
3. Find **XPayr Gateway** and click **Install**.
4. Configure required settings:
   - `API Base URL` (example: `https://xpayr.com/api/v1` or your current API domain)
   - `Secret API Key` (`sk_test_...` or `sk_live_...`)
   - `Network` (example: `bsc-testnet`)
   - `Currency` (example: `USDC`)
   - `Webhook Secret` (from XPayr panel webhook settings)
5. Save.

## Webhook
Set callback URL in XPayr panel:

`https://<your-store-domain>/ipn_xpayr.php`

Supported events:
- `payment.completed`
- `payment.confirmed`
- `payment.failed`
- `payment.expired`
- `payment.pending`
- `payment.processing`

## Uninstallation
1. In Admin Panel, go to `Modules > Payment`.
2. Select **XPayr Gateway** and click **Remove**.
   This removes all `MODULE_PAYMENT_XPAYR_*` configuration keys from the database.
3. Delete the following files from your Zen Cart installation:
   - `includes/modules/payment/xpayr.php`
   - `includes/languages/english/modules/payment/xpayr.php`
   - `includes/languages/english/modules/payment/lang.xpayr.php`
   - `ipn_xpayr.php`
   - `images/xpayr.png`
4. Optionally, drop the transactions table:
   ```sql
   DROP TABLE IF EXISTS xpayr_transactions;
   ```

## Notes
- This integration is non-custodial: funds are routed by on-chain payment flow, not held by plugin.
- If checkout or webhook fails, enable **Debug Log** and check `logs/xpayr-zencart.log`.
