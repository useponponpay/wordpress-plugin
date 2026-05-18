🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# PolyPay WordPress Zahlungs-Plugin

Mit [PolyPay](https://polypay.ai) können Sie auf jeder WordPress-Seite Krypto-Zahlungen (USDT, USDC usw.) akzeptieren. Unterstützt sowohl Shortcode- als auch WooCommerce-Gateway-Modus.

Unterstützte Netzwerke: **Tron (TRC20)**, **Ethereum (ERC20)**, **BSC (BEP20)**, **Polygon**, **Solana**

## Funktionen

- Funktioniert ohne WooCommerce
- Shortcode `[polypay_button]`
- Automatische WooCommerce-Gateway-Integration
- Einstellungsseite: `Settings -> PolyPay`
- Integrierte Zahlungshistorie

## Installation

1. `polypay` nach `/wp-content/plugins/` hochladen
2. Im WordPress-Admin aktivieren
3. API Key unter `Settings -> PolyPay` eintragen

## Verwendung

```text
[polypay_button amount="99.99"]
```

Vollständiges Beispiel:

```text
[polypay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```


## Links

- Konsole: https://polypay.ai
- Dokumentation: https://polypay.ai/docs
