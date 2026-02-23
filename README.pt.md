🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# Plugin de pagamento PonponPay para WordPress

Aceite pagamentos em cripto (USDT, USDC, etc.) em qualquer site WordPress com [PonponPay](https://ponponpay.com). Suporta modo shortcode independente e integração com WooCommerce.

Redes suportadas: **Tron (TRC20)**, **Ethereum (ERC20)**, **BSC (BEP20)**, **Polygon**, **Solana**

## Recursos

- Funciona sem WooCommerce
- Suporte ao shortcode `[ponponpay_button]`
- Integração automática como gateway do WooCommerce
- Página de configurações: `Settings -> PonponPay`
- Registro interno de pagamentos

## Instalação

1. Envie `ponponpay` para `/wp-content/plugins/`
2. Ative no painel do WordPress
3. Configure a API Key em `Settings -> PonponPay`

## Uso

```text
[ponponpay_button amount="99.99"]
```

Exemplo completo:

```text
[ponponpay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```


## Links

- Console: https://ponponpay.com
- Documentação: https://ponponpay.com/docs
