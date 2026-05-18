🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# إضافة PolyPay للدفع في WordPress

اقبل مدفوعات العملات الرقمية (USDT وUSDC وغيرها) في أي موقع WordPress عبر [PolyPay](https://polypay.ai). تدعم وضع الشورت كود المستقل والتكامل مع WooCommerce.

الشبكات المدعومة: **Tron (TRC20)** و **Ethereum (ERC20)** و **BSC (BEP20)** و **Polygon** و **Solana**

## الميزات

- تعمل بدون WooCommerce
- دعم الشورت كود `[polypay_button]`
- تكامل تلقائي كبوابة دفع WooCommerce
- صفحة الإعدادات: `Settings -> PolyPay`
- جدول مدمج لسجلات الدفع

## التثبيت

1. ارفع مجلد `polypay` إلى `/wp-content/plugins/`
2. فعّل الإضافة من لوحة تحكم WordPress
3. أدخل API Key من `Settings -> PolyPay`

## الاستخدام

```text
[polypay_button amount="99.99"]
```

مثال كامل:

```text
[polypay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```


## الروابط

- لوحة التحكم: https://polypay.ai
- الوثائق: https://polypay.ai/docs
