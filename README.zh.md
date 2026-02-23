🌐 [English](README.md) | [中文](README.zh.md) | [日本語](README.ja.md) | [한국어](README.ko.md) | [Deutsch](README.de.md) | [Español](README.es.md) | [Français](README.fr.md) | [Português](README.pt.md) | [Русский](README.ru.md) | [العربية](README.ar.md)

# PonponPay WordPress 支付插件

通过 [PonponPay](https://ponponpay.com) 在**任意 WordPress 站点**接收加密货币支付（USDT、USDC 等）。支持独立短代码模式，也支持 WooCommerce 网关模式。

支持网络：**Tron (TRC20)**、**Ethereum (ERC20)**、**BSC (BEP20)**、**Polygon**、**Solana**

---

## 功能

- ✅ 支持任意 WordPress 站点（不依赖 WooCommerce）
- ✅ 支持短代码 `[ponponpay_button]`
- ✅ 自动集成 WooCommerce 支付网关
- ✅ 独立设置页：`Settings -> PonponPay`
- ✅ 内置支付记录表
- ✅ 收银台展示已选支付方式（`Network / Currency`）
- ✅ 支持 WooCommerce HPOS

---

## 前置准备

> **⚠️ 安装前请先在 [ponponpay.com](https://ponponpay.com) 完成：**

1. 注册账号
2. 添加收款钱包（至少一个，例如 TRC20 USDT）
3. 在钱包中启用对应币种
4. 获取 API Key

---

## 安装

1. 将 `ponponpay` 目录上传到 `/wp-content/plugins/`
2. 在 WordPress 后台插件页启用插件
3. 打开 `Settings -> PonponPay`，填写 API Key

目录结构：

```text
ponponpay/                       -> wp-content/plugins/ponponpay/
├── ponponpay.php                         # 插件入口
├── includes/
│   ├── class-ponponpay-api.php           # API 客户端
│   ├── class-ponponpay-settings.php      # 独立设置页
│   ├── class-ponponpay-shortcode.php     # [ponponpay_button] 短代码
│   ├── class-ponponpay-rest-callback.php # REST 回调处理
│   ├── class-ponponpay-gateway.php       # WooCommerce 网关（可选）
│   └── class-ponponpay-callback.php      # WooCommerce 回调（可选）
├── assets/
│   ├── css/ponponpay.css
│   └── js/ponponpay.js
└── templates/
    └── payment-checkout.php              # 独立收银台模板
```

---

## 使用方式

### 模式 1：短代码（任意 WordPress 站点）

在页面或文章中插入：

```text
[ponponpay_button amount="99.99"]
```

完整参数示例：

```text
[ponponpay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]
```

参数说明：

| 参数 | 必填 | 默认值 | 说明 |
|------|------|--------|------|
| `amount` | ✅ | — | 支付金额 |
| `fiat_currency` | ❌ | USD | 法币代码 |
| `description` | ❌ | — | 订单描述 |
| `button_text` | ❌ | Pay with Crypto | 按钮文案 |
| `redirect_url` | ❌ | — | 支付后回跳地址 |

### 模式 2：WooCommerce 支付网关

安装 WooCommerce 后，PonponPay 会自动出现在：

**WooCommerce -> Settings -> Payments -> PonponPay**

无需额外配置，复用 `Settings -> PonponPay` 的 API Key。

---

## 链接

- 控制台：https://ponponpay.com
- 文档：https://ponponpay.com/docs
