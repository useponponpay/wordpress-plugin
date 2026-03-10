=== PonponPay - Crypto Payment Gateway ===
Contributors: ponponpay
Tags: crypto payment, cryptocurrency, payment gateway, woocommerce, usdt
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept cryptocurrency payments on WordPress with PonponPay. Use shortcode payment buttons on any site or enable it as a WooCommerce gateway.

== Description ==

PonponPay lets you accept cryptocurrency payments on WordPress sites using PonponPay merchant APIs.

The plugin works in two modes:

* Standalone WordPress mode with the `[ponponpay_button]` shortcode
* WooCommerce payment gateway mode when WooCommerce is installed

Features:

* Accept crypto payments on any WordPress site
* Use shortcode buttons without requiring WooCommerce
* Auto-register as a WooCommerce payment gateway when WooCommerce is active
* Manage API credentials from `Settings -> PonponPay`
* Store payment records in a dedicated WordPress table
* Compatible with WooCommerce HPOS

Supported networks include Tron, Ethereum, BSC, Polygon, and Solana.

== Installation ==

1. Upload the `ponponpay` folder to `/wp-content/plugins/`, or install the plugin from the WordPress admin area.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Go to `Settings -> PonponPay`.
4. Enter your PonponPay API Key and save the settings.
5. If you use WooCommerce, enable `PonponPay` under `WooCommerce -> Settings -> Payments`.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. You can use the `[ponponpay_button]` shortcode on any WordPress page or post.

= How do I create a payment button? =

Use the basic shortcode:

`[ponponpay_button amount="99.99"]`

Full example:

`[ponponpay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]`

= Where do I get the API Key? =

Log in to your PonponPay merchant account, create an API Key, and paste it into `Settings -> PonponPay`.

= What happens when WooCommerce is installed? =

The plugin automatically registers a PonponPay payment method in WooCommerce. It uses the same API Key configured in the WordPress settings page.

= Which data is sent to PonponPay? =

When you create or manage a payment, the plugin sends order-related data required for payment processing, such as amount, fiat currency, merchant order ID, redirect URL, callback URL, and selected payment method details.

== Screenshots ==

1. PonponPay settings page in WordPress admin
2. Shortcode payment button on a WordPress page
3. PonponPay payment option during WooCommerce checkout
4. Hosted crypto checkout flow

== Changelog ==

= 1.0.0 =

* Initial public release
* Added shortcode-based payment buttons
* Added WooCommerce payment gateway integration
* Added REST callback handling and payment record storage

== Upgrade Notice ==

= 1.0.0 =

Initial release.

== External Services ==

This plugin connects to the PonponPay service to create payment orders, fetch available payment methods, validate API access, and receive payment status updates.

It sends data to PonponPay only when needed for payment processing, including:

* API Key in the request header for authentication
* Payment amount and fiat currency
* Merchant order ID
* Redirect URL after payment
* Callback URL for payment notifications
* Payment identifiers such as trade ID or order number

Service endpoints used by the plugin include:

* `https://api.ponponpay.com/api/v1/pay/sdk/plugin/activate`
* `https://api.ponponpay.com/api/v1/pay/sdk/payment-methods`
* `https://api.ponponpay.com/api/v1/pay/sdk/order/add`
* `https://api.ponponpay.com/api/v1/pay/sdk/order/detail`

Service homepage:

* `https://ponponpay.com`

Service documentation:

* `https://ponponpay.com/docs`
