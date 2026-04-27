<?php
/**
 * PonponPay 独立支付选择页面模板
 *
 * 当访问 /?ponponpay_checkout=<order_id> 时渲染此页面。
 *
 * @package PonponPay
 */

if (!defined('ABSPATH')) {
	exit;
}

$ponponpay_i18n = [
	'invalid_request' => __('Invalid request', 'ponponpay-crypto-payment-gateway'),
	'order_not_found' => __('Order not found', 'ponponpay-crypto-payment-gateway'),
	'pay_with_crypto' => __('Pay with Crypto', 'ponponpay-crypto-payment-gateway'),
	'checkout_title' => __('PonponPay Checkout', 'ponponpay-crypto-payment-gateway'),
	'selected' => __('Selected', 'ponponpay-crypto-payment-gateway'),
	'select_payment_method' => __('Select Payment Method', 'ponponpay-crypto-payment-gateway'),
	'loading_methods' => __('Loading methods...', 'ponponpay-crypto-payment-gateway'),
	'confirm_pay' => __('Confirm & Pay', 'ponponpay-crypto-payment-gateway'),
	'cancel_and_return' => __('Cancel and return', 'ponponpay-crypto-payment-gateway'),
	'processing' => __('Processing...', 'ponponpay-crypto-payment-gateway'),
	'secured_by' => __('Secured by', 'ponponpay-crypto-payment-gateway'),
	'no_payment_methods' => __('No payment methods available', 'ponponpay-crypto-payment-gateway'),
	'no_payment_methods_contact' => __('No payment methods available. Contact administrator.', 'ponponpay-crypto-payment-gateway'),
	'network_error_loading_methods' => __('Network error while loading methods.', 'ponponpay-crypto-payment-gateway'),
	'failed_create_order' => __('Failed to create order', 'ponponpay-crypto-payment-gateway'),
	'network_error_creating_order' => __('Network error while creating order.', 'ponponpay-crypto-payment-gateway'),
];

$ponponpay_order_id = sanitize_text_field((string)get_query_var('ponponpay_checkout'));
$ponponpay_checkout_token = sanitize_text_field((string)get_query_var('ponponpay_token'));
if (empty($ponponpay_order_id)) {
	wp_die(esc_html($ponponpay_i18n['invalid_request']));
}

$ponponpay_payment = ponponpay_get_payment($ponponpay_order_id);

$ponponpay_is_wc_order = false;
$ponponpay_amount = 0;
$ponponpay_fiat_currency = 'USD';
$ponponpay_description = '';
$ponponpay_redirect_url = home_url();
$ponponpay_expected_token = '';

if (!$ponponpay_payment) {
	// 检查是否为 WooCommerce 订单
	if (strpos($ponponpay_order_id, 'WC_') === 0 && class_exists('WC_Order')) {
		$ponponpay_wc_order_id = str_replace('WC_', '', $ponponpay_order_id);
		$ponponpay_wc_order = wc_get_order($ponponpay_wc_order_id);
		if (!$ponponpay_wc_order) {
			wp_die(esc_html($ponponpay_i18n['order_not_found']));
		}

		$ponponpay_is_wc_order = true;
		$ponponpay_expected_token = ponponpay_create_checkout_token($ponponpay_order_id, $ponponpay_wc_order->get_order_key());
		$ponponpay_amount = $ponponpay_wc_order->get_total();
		$ponponpay_fiat_currency = $ponponpay_wc_order->get_currency();
		/* translators: %s: WooCommerce order number */
		$ponponpay_description = sprintf(__('Order #%s', 'ponponpay-crypto-payment-gateway'), $ponponpay_wc_order->get_order_number());
		$ponponpay_redirect_url = $ponponpay_wc_order->get_checkout_order_received_url();

		// 检查是否已经标记了 payment_url（说明已经创建过后台订单）
		$ponponpay_existing_payment_url = $ponponpay_wc_order->get_meta('_ponponpay_payment_url');
		if ($ponponpay_existing_payment_url) {
			// 保持在当前收银台页面，由用户手动点击确认支付。
		}
	} else {
		wp_die(esc_html($ponponpay_i18n['order_not_found']));
	}
} else {
	$ponponpay_expected_token = ponponpay_create_checkout_token($ponponpay_order_id);
	if ($ponponpay_payment->status > 0 && !empty($ponponpay_payment->payment_url)) {
		// 已有 payment_url 时也不自动跳转，保持当前页面交互。
	}
	$ponponpay_amount = $ponponpay_payment->amount;
	$ponponpay_fiat_currency = $ponponpay_payment->fiat_currency;
	$ponponpay_description = $ponponpay_payment->description;
	$ponponpay_redirect_url = $ponponpay_payment->redirect_url ?: home_url();
}

if (empty($ponponpay_checkout_token) || empty($ponponpay_expected_token) || !hash_equals($ponponpay_expected_token, $ponponpay_checkout_token)) {
	wp_die(esc_html($ponponpay_i18n['invalid_request']));
}

// 提取当前 REST API 基础路径和 nonce 给前端 JS 调用
$ponponpay_rest_url = esc_url_raw(rest_url('ponponpay/v1/'));
$ponponpay_nonce = wp_create_nonce('wp_rest');

// 注册并入队收银台专用样式和脚本
wp_enqueue_style(
	'ponponpay-checkout-style',
	PONPONPAY_PLUGIN_URL . 'assets/css/ponponpay-checkout.css',
	[],
	PONPONPAY_VERSION
);

wp_enqueue_script(
	'ponponpay-checkout-script',
	PONPONPAY_PLUGIN_URL . 'assets/js/ponponpay-checkout.js',
	[],
	PONPONPAY_VERSION,
	true
);

wp_localize_script('ponponpay-checkout-script', 'ponponpayCheckout', [
	'restUrl' => $ponponpay_rest_url,
	'nonce' => $ponponpay_nonce,
	'orderId' => $ponponpay_order_id,
	'token' => $ponponpay_checkout_token,
	'i18n' => $ponponpay_i18n,
]);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>
			<?php echo esc_html($ponponpay_i18n['pay_with_crypto']); ?> -
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
					<?php echo esc_html($ponponpay_i18n['checkout_title']); ?>
			</div>
			<div class="amount">
				<?php echo esc_html(number_format(floatval($ponponpay_amount), 2)); ?>
				<span class="currency">
					<?php echo esc_html($ponponpay_fiat_currency); ?>
				</span>
			</div>
			<?php if ($ponponpay_description): ?>
				<div class="desc">
					<?php echo esc_html($ponponpay_description); ?>
				</div>
			<?php endif; ?>
			<div class="selected-method" id="selectedMethodDisplay">
				<span class="selected-method-label"><?php echo esc_html($ponponpay_i18n['selected']); ?>:</span>
				<span id="selectedMethodText">--</span>
			</div>
		</div>

		<div class="content">
			<div class="error-msg" id="errorMsg"></div>

			<div class="select-group">
				<div class="section-title"><?php echo esc_html($ponponpay_i18n['select_payment_method']); ?></div>
				<div id="methodsContainer" class="methods-container">
					<div style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 14px;">
						<?php echo esc_html($ponponpay_i18n['loading_methods']); ?>
					</div>
				</div>
			</div>

			<button type="button" class="btn-submit" id="submitBtn" disabled>
				<span>
						<?php echo esc_html($ponponpay_i18n['confirm_pay']); ?>
				</span>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round">
					<line x1="5" y1="12" x2="19" y2="12"></line>
					<polyline points="12 5 19 12 12 19"></polyline>
				</svg>
			</button>

			<a href="<?php echo esc_url($ponponpay_redirect_url); ?>" class="btn-cancel">
				<?php echo esc_html($ponponpay_i18n['cancel_and_return']); ?>
			</a>
		</div>

		<div class="loading-overlay" id="loadingOverlay">
			<div class="spinner"></div>
			<div style="font-weight: 500; color: var(--primary);"><?php echo esc_html($ponponpay_i18n['processing']); ?></div>
		</div>

		<div class="footer">
			<?php echo esc_html($ponponpay_i18n['secured_by']); ?> <a href="https://ponponpay.com" target="_blank">PonponPay</a>
		</div>
	</div>

	<?php wp_footer(); ?>

</body>

</html>
