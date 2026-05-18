🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# Plugin de pago PolyPay para WordPress

Acepta pagos en criptomonedas (USDT, USDC, etc.) en cualquier sitio WordPress con [PolyPay](https://polypay.ai). Soporta modo shortcode independiente y también integración con WooCommerce.

Redes compatibles: **Tron (TRC20)**, **Ethereum (ERC20)**, **BSC (BEP20)**, **Polygon**, **Solana**

## Funciones

- Funciona sin WooCommerce
- Soporte de shortcode `[polypay_button]`
- Integración automática como gateway de WooCommerce
- Página de configuración: `Settings -> PolyPay`
- Registro interno de pagos

## Instalación

1. Sube `polypay` a `/wp-content/plugins/`
2. Actívalo desde el panel de plugins
3. Configura tu API Key en `Settings -> PolyPay`

## Uso

```text
[polypay_button amount="99.99"]
```

Ejemplo completo:

```text
[polypay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```


## Enlaces

- Consola: https://polypay.ai
- Documentación: https://polypay.ai/docs
