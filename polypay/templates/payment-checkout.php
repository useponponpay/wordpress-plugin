<?php
/**
 * PolyPay standalone payment method selection page template
 *
 * Rendered when /?polypay_checkout=<order_id> is visited.
 *
 * @package PolyPay
 */

if (!defined('ABSPATH')) {
	exit;
}

$polypay_i18n = [
	'invalid_request' => __('Invalid request', 'polypay-crypto-payment-gateway'),
	'order_not_found' => __('Order not found', 'polypay-crypto-payment-gateway'),
	'pay_with_crypto' => __('Pay with Crypto', 'polypay-crypto-payment-gateway'),
	'checkout_title' => __('PolyPay Checkout', 'polypay-crypto-payment-gateway'),
	'selected' => __('Selected', 'polypay-crypto-payment-gateway'),
	'select_payment_method' => __('Select Payment Method', 'polypay-crypto-payment-gateway'),
	'loading_methods' => __('Loading methods...', 'polypay-crypto-payment-gateway'),
	'confirm_pay' => __('Confirm & Pay', 'polypay-crypto-payment-gateway'),
	'cancel_and_return' => __('Cancel and return', 'polypay-crypto-payment-gateway'),
	'processing' => __('Processing...', 'polypay-crypto-payment-gateway'),
	'secured_by' => __('Secured by', 'polypay-crypto-payment-gateway'),
	'no_payment_methods' => __('No payment methods available', 'polypay-crypto-payment-gateway'),
	'no_payment_methods_contact' => __('No payment methods available. Contact administrator.', 'polypay-crypto-payment-gateway'),
	'network_error_loading_methods' => __('Network error while loading methods.', 'polypay-crypto-payment-gateway'),
	'failed_create_order' => __('Failed to create order', 'polypay-crypto-payment-gateway'),
	'network_error_creating_order' => __('Network error while creating order.', 'polypay-crypto-payment-gateway'),
];

$polypay_order_id = sanitize_text_field((string)get_query_var('polypay_checkout'));
$polypay_checkout_token = sanitize_text_field((string)get_query_var('polypay_token'));
if (empty($polypay_order_id)) {
	wp_die(esc_html($polypay_i18n['invalid_request']));
}

$polypay_payment = polypay_get_payment($polypay_order_id);

$polypay_is_wc_order = false;
$polypay_amount = 0;
$polypay_fiat_currency = 'USD';
$polypay_description = '';
$polypay_redirect_url = home_url();
$polypay_expected_token = '';

if (!$polypay_payment) {
	// Check whether this is a WooCommerce order (B{shortened merchant ID}_{order_id}, backward compatible with the legacy WC_ prefix)
	$polypay_wc_order_id = polypay_parse_wc_order_id($polypay_order_id);
	if ($polypay_wc_order_id > 0 && class_exists('WC_Order')) {
		$polypay_wc_order = wc_get_order($polypay_wc_order_id);
		if (!$polypay_wc_order) {
			wp_die(esc_html($polypay_i18n['order_not_found']));
		}

		$polypay_is_wc_order = true;
		$polypay_expected_token = polypay_create_checkout_token($polypay_order_id, $polypay_wc_order->get_order_key());
		$polypay_amount = $polypay_wc_order->get_total();
		$polypay_fiat_currency = $polypay_wc_order->get_currency();
		/* translators: %s: WooCommerce order number */
		$polypay_description = sprintf(__('Order #%s', 'polypay-crypto-payment-gateway'), $polypay_wc_order->get_order_number());
		$polypay_redirect_url = $polypay_wc_order->get_checkout_order_received_url();

		// Check whether payment_url has already been recorded (meaning a backend order was already created)
		$polypay_existing_payment_url = $polypay_wc_order->get_meta('_polypay_payment_url');
		if ($polypay_existing_payment_url) {
			// Stay on the current checkout page; the user confirms payment manually.
		}
	} else {
		wp_die(esc_html($polypay_i18n['order_not_found']));
	}
} else {
	$polypay_expected_token = polypay_create_checkout_token($polypay_order_id);
	if ($polypay_payment->status > 0 && !empty($polypay_payment->payment_url)) {
		// Do not auto-redirect even when payment_url already exists; keep the interaction on the current page.
	}
	$polypay_amount = $polypay_payment->amount;
	$polypay_fiat_currency = $polypay_payment->fiat_currency;
	$polypay_description = $polypay_payment->description;
	$polypay_redirect_url = $polypay_payment->redirect_url ?: home_url();
}

if (empty($polypay_checkout_token) || empty($polypay_expected_token) || !hash_equals($polypay_expected_token, $polypay_checkout_token)) {
	wp_die(esc_html($polypay_i18n['invalid_request']));
}

// Extract the current REST API base path and nonce for front-end JS calls
$polypay_rest_url = esc_url_raw(rest_url('polypay/v1/'));
$polypay_nonce = wp_create_nonce('wp_rest');

// Register and enqueue checkout-specific styles and scripts
wp_enqueue_style(
	'polypay-checkout-style',
	POLYPAY_PLUGIN_URL . 'assets/css/polypay-checkout.css',
	[],
	POLYPAY_VERSION
);

wp_enqueue_script(
	'polypay-checkout-script',
	POLYPAY_PLUGIN_URL . 'assets/js/polypay-checkout.js',
	[],
	POLYPAY_VERSION,
	true
);

wp_localize_script('polypay-checkout-script', 'polypayCheckout', [
	'restUrl' => $polypay_rest_url,
	'nonce' => $polypay_nonce,
	'orderId' => $polypay_order_id,
	'token' => $polypay_checkout_token,
	'i18n' => $polypay_i18n,
]);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>
			<?php echo esc_html($polypay_i18n['pay_with_crypto']); ?> -
		<?php bloginfo('name'); ?>
	</title>
	<?php wp_head(); ?>
</head>

<body>

	<div class="checkout-container" id="app">
		<div class="header">
			<div class="brand">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
				</svg>
					<?php echo esc_html($polypay_i18n['checkout_title']); ?>
			</div>
			<div class="amount">
				<?php echo esc_html(number_format(floatval($polypay_amount), 2)); ?>
				<span class="currency">
					<?php echo esc_html($polypay_fiat_currency); ?>
				</span>
			</div>
			<?php if ($polypay_description): ?>
				<div class="desc">
					<?php echo esc_html($polypay_description); ?>
				</div>
			<?php endif; ?>
			<div class="selected-method" id="selectedMethodDisplay">
				<span class="selected-method-label"><?php echo esc_html($polypay_i18n['selected']); ?>:</span>
				<span id="selectedMethodText">--</span>
			</div>
		</div>

		<div class="content">
			<div class="error-msg" id="errorMsg"></div>

			<div class="select-group">
				<div class="section-title"><?php echo esc_html($polypay_i18n['select_payment_method']); ?></div>
				<div id="methodsContainer" class="methods-container">
					<div style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 14px;">
						<?php echo esc_html($polypay_i18n['loading_methods']); ?>
					</div>
				</div>
			</div>

			<button type="button" class="btn-submit" id="submitBtn" disabled>
				<span>
						<?php echo esc_html($polypay_i18n['confirm_pay']); ?>
				</span>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round">
					<line x1="5" y1="12" x2="19" y2="12"></line>
					<polyline points="12 5 19 12 12 19"></polyline>
				</svg>
			</button>

			<a href="<?php echo esc_url($polypay_redirect_url); ?>" class="btn-cancel">
				<?php echo esc_html($polypay_i18n['cancel_and_return']); ?>
			</a>
		</div>

		<div class="loading-overlay" id="loadingOverlay">
			<div class="spinner"></div>
			<div style="font-weight: 500; color: var(--primary);"><?php echo esc_html($polypay_i18n['processing']); ?></div>
		</div>

		<div class="footer">
			<?php echo esc_html($polypay_i18n['secured_by']); ?> <a href="https://polypay.ai" target="_blank">PolyPay</a>
		</div>
	</div>

	<?php wp_footer(); ?>

</body>

</html>
