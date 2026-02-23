🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# Plugin de pago PonponPay para WordPress

Acepta pagos en criptomonedas (USDT, USDC, etc.) en cualquier sitio WordPress con [PonponPay](https://ponponpay.com). Soporta modo shortcode independiente y también integración con WooCommerce.

Redes compatibles: **Tron (TRC20)**, **Ethereum (ERC20)**, **BSC (BEP20)**, **Polygon**, **Solana**

## Funciones

- Funciona sin WooCommerce
- Soporte de shortcode `[ponponpay_button]`
- Integración automática como gateway de WooCommerce
- Página de configuración: `Settings -> PonponPay`
- Registro interno de pagos

## Instalación

1. Sube `ponponpay` a `/wp-content/plugins/`
2. Actívalo desde el panel de plugins
3. Configura tu API Key en `Settings -> PonponPay`

## Uso

```text
[ponponpay_button amount="99.99"]
```

Ejemplo completo:

```text
[ponponpay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```


## Enlaces

- Consola: https://ponponpay.com
- Documentación: https://ponponpay.com/docs
