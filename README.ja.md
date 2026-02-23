🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# PonponPay WordPress 決済プラグイン

[PonponPay](https://ponponpay.com) を使って、任意の WordPress サイトで暗号資産決済（USDT、USDC など）を受け付けできます。ショートコード単体利用と WooCommerce 連携の両方に対応します。

対応ネットワーク: **Tron (TRC20)**、**Ethereum (ERC20)**、**BSC (BEP20)**、**Polygon**、**Solana**

## 主な機能

- WooCommerce なしでも利用可能
- ショートコード `[ponponpay_button]` 対応
- WooCommerce 決済ゲートウェイ自動連携
- 設定画面: `Settings -> PonponPay`
- 決済履歴テーブル内蔵

## インストール

1. `ponponpay` を `/wp-content/plugins/` にアップロード
2. WordPress 管理画面で有効化
3. `Settings -> PonponPay` で API Key を設定

## 使い方

```text
[ponponpay_button amount="99.99"]
```

完全な例:

```text
[ponponpay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```


## リンク

- コンソール: https://ponponpay.com
- ドキュメント: https://ponponpay.com/docs
