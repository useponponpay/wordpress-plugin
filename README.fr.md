🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# Plugin de paiement PolyPay pour WordPress

Acceptez les paiements crypto (USDT, USDC, etc.) sur n'importe quel site WordPress via [PolyPay](https://polypay.ai). Prend en charge le mode shortcode autonome et l'intégration WooCommerce.

Réseaux pris en charge : **Tron (TRC20)**, **Ethereum (ERC20)**, **BSC (BEP20)**, **Polygon**, **Solana**

## Fonctionnalités

- Fonctionne sans WooCommerce
- Shortcode `[polypay_button]`
- Intégration automatique en passerelle WooCommerce
- Page de réglages : `Settings -> PolyPay`
- Historique des paiements intégré

## Installation

1. Téléversez `polypay` dans `/wp-content/plugins/`
2. Activez le plugin dans l'admin WordPress
3. Configurez la clé API dans `Settings -> PolyPay`

## Utilisation

```text
[polypay_button amount="99.99"]
```

Exemple complet :

```text
[polypay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```


## Liens

- Console : https://polypay.ai
- Documentation : https://polypay.ai/docs
