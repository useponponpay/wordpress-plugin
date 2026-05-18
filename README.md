🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# PolyPay WordPress Payment Plugin

Accept cryptocurrency payments (USDT, USDC, etc.) on **any WordPress site** via [PolyPay](https://polypay.ai). Works standalone with shortcodes or integrates with WooCommerce.

Supported networks: **Tron (TRC20)** · **Ethereum (ERC20)** · **BSC (BEP20)** · **Polygon** · **Solana**

---

## Features

- ✅ **Works on any WordPress site** — No WooCommerce required
- ✅ **Shortcode support** — Embed payment buttons on any page or post
- ✅ **WooCommerce integration** — Auto-detected, registers as payment gateway
- ✅ **Independent settings page** — Settings → PolyPay
- ✅ **Payment records** — Built-in payment tracking table
- ✅ **WooCommerce HPOS** compatible

---

## Prerequisites

> **⚠️ Before installing, complete these steps at [polypay.ai](https://polypay.ai):**

1. **Register an account** at [polypay.ai](https://polypay.ai)
2. **Add wallet addresses** — At least one receiving wallet (e.g. TRC20 USDT)
3. **Enable currencies** — Select supported cryptocurrencies per wallet
4. **Get your API Key** from the API Keys page

---

## Installation

1. Upload the `polypay` folder to `/wp-content/plugins/`
2. Activate through **Plugins** menu
3. Go to **Settings → PolyPay** and enter your API Key

```
polypay/                       →  wp-content/plugins/polypay/
├── polypay.php                         # Plugin entry point
├── includes/
│   ├── class-polypay-api.php           # PolyPay API client
│   ├── class-polypay-settings.php      # Standalone settings page
│   ├── class-polypay-shortcode.php     # [polypay_button] shortcode
│   ├── class-polypay-rest-callback.php # REST API callback handler
│   ├── class-polypay-gateway.php       # WooCommerce gateway (optional)
│   └── class-polypay-callback.php      # WooCommerce callback (optional)
├── assets/
│   ├── css/polypay.css
│   └── js/polypay.js
└── templates/
    └── payment-checkout.php              # Standalone checkout page template
```

---

## Usage

### Mode 1: Shortcode (Any WordPress Site)

Embed a payment button on any page or post:

```
[polypay_button amount="99.99"]
```

**Full parameters:**

```
[polypay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `amount` | ✅ | — | Payment amount |
| `fiat_currency` | ❌ | USD | Fiat currency code |
| `description` | ❌ | — | Payment description |
| `button_text` | ❌ | Pay with Crypto | Button label |
| `redirect_url` | ❌ | — | Redirect URL after payment |

### Mode 2: WooCommerce Payment Gateway

If WooCommerce is installed, PolyPay automatically appears in:

**WooCommerce → Settings → Payments → PolyPay**

No additional configuration needed — it uses the same API Key from Settings → PolyPay.

---

## Payment Flow

### Shortcode Mode
```
Page/Post with [polypay_button] → Customer clicks "Pay with Crypto"
→ Selects network & currency → Plugin creates order via API
→ Redirects to PolyPay payment page → Payment completed
→ Callback to /wp-json/polypay/v1/callback → Record updated
```

### WooCommerce Mode
```
Checkout → Select "Crypto Payment (PolyPay)" → Order created
→ Redirects to payment page → Payment completed
→ Callback to /wc-api/polypay → WC order marked as paid
```

---

## Links

- **PolyPay Console**: [https://polypay.ai](https://polypay.ai)
- **Documentation**: [https://polypay.ai/docs](https://polypay.ai/docs)
