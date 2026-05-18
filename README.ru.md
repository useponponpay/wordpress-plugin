🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# Плагин оплаты PolyPay для WordPress

Принимайте криптоплатежи (USDT, USDC и др.) на любом сайте WordPress через [PolyPay](https://polypay.ai). Поддерживаются режим шорткода и интеграция с WooCommerce.

Поддерживаемые сети: **Tron (TRC20)**, **Ethereum (ERC20)**, **BSC (BEP20)**, **Polygon**, **Solana**

## Возможности

- Работает без WooCommerce
- Поддержка шорткода `[polypay_button]`
- Автоматическая интеграция как шлюз WooCommerce
- Страница настроек: `Settings -> PolyPay`
- Встроенная таблица платежей

## Установка

1. Загрузите `polypay` в `/wp-content/plugins/`
2. Активируйте плагин в админке WordPress
3. Укажите API Key в `Settings -> PolyPay`

## Использование

```text
[polypay_button amount="99.99"]
```

Полный пример:

```text
[polypay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```


## Ссылки

- Консоль: https://polypay.ai
- Документация: https://polypay.ai/docs
