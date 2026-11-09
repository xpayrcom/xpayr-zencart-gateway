<div align="center">
  <a href="https://xpayr.com">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://xpayr.com/public/images/xpayr-logo-white.png" />
      <source media="(prefers-color-scheme: light)" srcset="https://xpayr.com/public/images/xpayr-logo-black.png" />
      <img src="https://xpayr.com/public/images/xpayr-logo-white.png" alt="XPayr Official Logo" width="220" />
    </picture>
  </a>
  <p>
    <a href="https://xpayr.com"><img src="https://xpayr.com/partner-badge.php?type=powered_by&theme=dark" alt="Powered by XPayr" height="28" /></a>
    <a href="https://mcp.xpayr.com"><img src="https://img.shields.io/badge/MCP_Portal-mcp.xpayr.com-029bfd?style=for-the-badge" alt="XPayr MCP Portal" height="28" /></a>
    <a href="https://docs.xpayr.com"><img src="https://img.shields.io/badge/Docs-docs.xpayr.com-10b981?style=for-the-badge" alt="XPayr Docs" height="28" /></a>
    <a href="https://www.npmjs.com/package/@xpayr/mcp-server"><img src="https://img.shields.io/npm/v/@xpayr/mcp-server?style=for-the-badge&color=cb3837&label=MCP%20NPM" alt="NPM @xpayr/mcp-server" height="28" /></a>
  </p>
  <p>
    <a href="https://xpayr.com"><b>Official Website</b></a> •
    <a href="https://mcp.xpayr.com"><b>AI &amp; MCP Portal (21 Tools)</b></a> •
    <a href="https://docs.xpayr.com"><b>Developer Docs</b></a> •
    <a href="https://xpayr.com/developers"><b>Developer Hub</b></a> •
    <a href="https://xpayr.com/tools/crypto-payment-fee-calculator"><b>0.5% Fee Calculator</b></a>
  </p>
</div>

---

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
