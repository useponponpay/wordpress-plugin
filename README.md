🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# PonponPay WordPress Payment Plugin

Accept cryptocurrency payments (USDT, USDC, etc.) on **any WordPress site** via [PonponPay](https://ponponpay.com). Works standalone with shortcodes or integrates with WooCommerce.

Supported networks: **Tron (TRC20)** · **Ethereum (ERC20)** · **BSC (BEP20)** · **Polygon** · **Solana**

---

## Features

- ✅ **Works on any WordPress site** — No WooCommerce required
- ✅ **Shortcode support** — Embed payment buttons on any page or post
- ✅ **WooCommerce integration** — Auto-detected, registers as payment gateway
- ✅ **Independent settings page** — Settings → PonponPay
- ✅ **Payment records** — Built-in payment tracking table
- ✅ **WooCommerce HPOS** compatible

---

## Prerequisites

> **⚠️ Before installing, complete these steps at [ponponpay.com](https://ponponpay.com):**

1. **Register an account** at [ponponpay.com](https://ponponpay.com)
2. **Add wallet addresses** — At least one receiving wallet (e.g. TRC20 USDT)
3. **Enable currencies** — Select supported cryptocurrencies per wallet
4. **Get your API Key** from the API Keys page

---

## Installation

1. Upload the `ponponpay` folder to `/wp-content/plugins/`
2. Activate through **Plugins** menu
3. Go to **Settings → PonponPay** and enter your API Key

```
ponponpay/                       →  wp-content/plugins/ponponpay/
├── ponponpay.php                         # Plugin entry point
├── includes/
│   ├── class-ponponpay-api.php           # PonponPay API client
│   ├── class-ponponpay-settings.php      # Standalone settings page
│   ├── class-ponponpay-shortcode.php     # [ponponpay_button] shortcode
│   ├── class-ponponpay-rest-callback.php # REST API callback handler
│   ├── class-ponponpay-gateway.php       # WooCommerce gateway (optional)
│   └── class-ponponpay-callback.php      # WooCommerce callback (optional)
├── assets/
│   ├── css/ponponpay.css
│   └── js/ponponpay.js
└── templates/
    └── payment-checkout.php              # Standalone checkout page template
```

---

## Usage

### Mode 1: Shortcode (Any WordPress Site)

Embed a payment button on any page or post:

```
[ponponpay_button amount="99.99"]
```

**Full parameters:**

```
[ponponpay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `amount` | ✅ | — | Payment amount |
| `fiat_currency` | ❌ | USD | Fiat currency code |
| `description` | ❌ | — | Payment description |
| `button_text` | ❌ | Pay with Crypto | Button label |
| `redirect_url` | ❌ | — | Redirect URL after payment |

### Mode 2: WooCommerce Payment Gateway

If WooCommerce is installed, PonponPay automatically appears in:

**WooCommerce → Settings → Payments → PonponPay**

No additional configuration needed — it uses the same API Key from Settings → PonponPay.

---

## Payment Flow

### Shortcode Mode
```
Page/Post with [ponponpay_button] → Customer clicks "Pay with Crypto"
→ Selects network & currency → Plugin creates order via API
→ Redirects to PonponPay payment page → Payment completed
→ Callback to /wp-json/ponponpay/v1/callback → Record updated
```

### WooCommerce Mode
```
Checkout → Select "Crypto Payment (PonponPay)" → Order created
→ Redirects to payment page → Payment completed
→ Callback to /wc-api/ponponpay → WC order marked as paid
```

---

## Links

- **PonponPay Console**: [https://ponponpay.com](https://ponponpay.com)
- **Documentation**: [https://ponponpay.com/docs](https://ponponpay.com/docs)
