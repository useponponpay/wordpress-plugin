=== PolyPay - Crypto Payment Gateway ===
Contributors: polypay
Tags: crypto payment, cryptocurrency, payment gateway, woocommerce, usdt
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept cryptocurrency payments on WordPress with PolyPay. Use shortcode payment buttons on any site or enable it as a WooCommerce gateway.

== Description ==

PolyPay lets you accept cryptocurrency payments on WordPress sites using PolyPay merchant APIs.

The plugin works in two modes:

* Standalone WordPress mode with the `[polypay_button]` shortcode
* WooCommerce payment gateway mode when WooCommerce is installed

Features:

* Accept crypto payments on any WordPress site
* Use shortcode buttons without requiring WooCommerce
* Auto-register as a WooCommerce payment gateway when WooCommerce is active
* Manage API credentials from `Settings -> PolyPay`
* Store payment records in a dedicated WordPress table
* Compatible with WooCommerce HPOS

Supported networks include Tron, Ethereum, BSC, Polygon, and Solana.

== Installation ==

1. Upload the `polypay` folder to `/wp-content/plugins/`, or install the plugin from the WordPress admin area.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Go to `Settings -> PolyPay`.
4. Enter your PolyPay API Key and save the settings.
5. If you use WooCommerce, enable `PolyPay` under `WooCommerce -> Settings -> Payments`.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. You can use the `[polypay_button]` shortcode on any WordPress page or post.

= How do I create a payment button? =

Use the basic shortcode:

`[polypay_button amount="99.99"]`

Full example:

`[polypay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]`

= Where do I get the API Key? =

Log in to your PolyPay merchant account, create an API Key, and paste it into `Settings -> PolyPay`.

= What happens when WooCommerce is installed? =

The plugin automatically registers a PolyPay payment method in WooCommerce. It uses the same API Key configured in the WordPress settings page.

= Which data is sent to PolyPay? =

When you create or manage a payment, the plugin sends order-related data required for payment processing, such as amount, fiat currency, merchant order ID, redirect URL, callback URL, and selected payment method details.

== Screenshots ==

1. PolyPay settings page in WordPress admin
2. Shortcode payment button on a WordPress page
3. PolyPay payment option during WooCommerce checkout
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

This plugin connects to the PolyPay API, an external cryptocurrency payment gateway service provided by PolyPay. The service is required to validate merchant API access, fetch available cryptocurrency payment methods, create payment orders, query payment order details, host the customer cryptocurrency checkout flow, and send payment status callbacks back to the WordPress site.

The plugin sends data to PolyPay only when the store administrator or customer uses a PolyPay payment feature:

* When an administrator saves or verifies the API Key, the plugin sends the API Key and plugin type to validate the merchant connection.
* When a checkout page or payment button loads available payment methods, the plugin sends the API Key to request the merchant's enabled payment methods.
* When a customer creates a payment order, the plugin sends the API Key, payment amount, fiat currency, merchant order ID, selected cryptocurrency, selected blockchain network, callback URL, and redirect URL.
* When an order detail lookup is needed, the plugin sends the API Key and payment identifiers such as trade ID or merchant order ID.
* When PolyPay sends a payment callback to the site, the callback may include payment status, merchant order ID, trade ID, transaction hash, cryptocurrency, blockchain network, and paid amount.

Data sent to PolyPay may include:

* API Key in the request header for authentication
* Payment amount and fiat currency
* Merchant order ID
* Selected cryptocurrency and blockchain network
* Redirect URL after payment
* Callback URL for payment notifications
* Payment identifiers such as trade ID, order number, and transaction hash

Service endpoints used by the plugin include:

* `https://api.polypay.ai/api/v1/pay/sdk/plugin/activate`
* `https://api.polypay.ai/api/v1/pay/sdk/payment-methods`
* `https://api.polypay.ai/api/v1/pay/sdk/order/add`
* `https://api.polypay.ai/api/v1/pay/sdk/order/detail`

Service homepage:

* `https://polypay.ai`

Service documentation:

* `https://polypay.ai/docs`

Terms of Service:

* `https://polypay.ai/terms`

Privacy Policy:

* `https://polypay.ai/privacy`
