🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# إضافة PonponPay للدفع في WordPress

اقبل مدفوعات العملات الرقمية (USDT وUSDC وغيرها) في أي موقع WordPress عبر [PonponPay](https://ponponpay.com). تدعم وضع الشورت كود المستقل والتكامل مع WooCommerce.

الشبكات المدعومة: **Tron (TRC20)** و **Ethereum (ERC20)** و **BSC (BEP20)** و **Polygon** و **Solana**

## الميزات

- تعمل بدون WooCommerce
- دعم الشورت كود `[ponponpay_button]`
- تكامل تلقائي كبوابة دفع WooCommerce
- صفحة الإعدادات: `Settings -> PonponPay`
- جدول مدمج لسجلات الدفع

## التثبيت

1. ارفع مجلد `ponponpay` إلى `/wp-content/plugins/`
2. فعّل الإضافة من لوحة تحكم WordPress
3. أدخل API Key من `Settings -> PonponPay`

## الاستخدام

```text
[ponponpay_button amount="99.99"]
```

مثال كامل:

```text
[ponponpay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```


## الروابط

- لوحة التحكم: https://ponponpay.com
- الوثائق: https://ponponpay.com/docs
