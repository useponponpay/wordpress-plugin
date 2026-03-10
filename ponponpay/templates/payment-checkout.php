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

// 查询订单
global $wpdb;
$ponponpay_table = $wpdb->prefix . 'ponponpay_payments';
$ponponpay_payment = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . esc_sql($ponponpay_table) . ' WHERE order_id = %s',
		$ponponpay_order_id
	)
);

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
	<style>
		:root {
			--primary: #4f46e5;
			--primary-hover: #4338ca;
			--bg: #f3f4f6;
			--card-bg: rgba(255, 255, 255, 0.9);
			--text-main: #111827;
			--text-muted: #6b7280;
			--border: #e5e7eb;
			--ring: rgba(79, 70, 229, 0.2);
		}

		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			background: linear-gradient(135deg, #f6f8fb 0%, #e5e7eb 100%);
			color: var(--text-main);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}

		.checkout-container {
			width: 100%;
			max-width: 440px;
			background: var(--card-bg);
			backdrop-filter: blur(12px);
			-webkit-backdrop-filter: blur(12px);
			border-radius: 24px;
			box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06), 0 1px 3px rgba(0, 0, 0, 0.05);
			overflow: hidden;
			border: 1px solid rgba(255, 255, 255, 0.5);
			animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
		}

		@keyframes slideUp {
			from {
				opacity: 0;
				transform: translateY(20px);
			}

			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.header {
			padding: 32px 32px 24px;
			text-align: center;
			border-bottom: 1px solid var(--border);
		}

		.brand {
			font-size: 14px;
			font-weight: 600;
			color: var(--text-muted);
			text-transform: uppercase;
			letter-spacing: 1px;
			margin-bottom: 12px;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 6px;
		}

		.amount {
			font-size: 42px;
			font-weight: 800;
			color: var(--text-main);
			line-height: 1.1;
			letter-spacing: -1px;
		}

		.currency {
			font-size: 20px;
			color: var(--text-muted);
			font-weight: 500;
		}

		.desc {
			margin-top: 8px;
			font-size: 15px;
			color: var(--text-muted);
		}

		.selected-method {
			margin-top: 12px;
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 12px;
			border-radius: 9999px;
			background: #eef2ff;
			color: #4338ca;
			font-size: 13px;
			font-weight: 600;
			line-height: 1;
		}

		.selected-method-label {
			color: #6366f1;
			font-weight: 500;
		}

		.content {
			padding: 32px;
		}

		.section-title {
			font-size: 14px;
			font-weight: 600;
			color: var(--text-main);
			margin-bottom: 16px;
		}

		.select-group {
			margin-bottom: 24px;
			position: relative;
		}

		.methods-container {
			display: flex;
			flex-direction: column;
			gap: 12px;
		}

		.network-group {
			border: 1px solid var(--border);
			border-radius: 16px;
			background: #fff;
			overflow: hidden;
			transition: all 0.2s ease;
		}
		
		.network-group:hover {
			border-color: #d1d5db;
		}

		.network-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 16px;
			cursor: pointer;
			user-select: none;
			background: #fff;
		}

		.network-title {
			display: flex;
			align-items: center;
			gap: 12px;
			font-size: 16px;
			font-weight: 600;
			color: var(--text-main);
		}

		.network-logo {
			width: 28px;
			height: 28px;
			border-radius: 50%;
			object-fit: contain;
			background: #f3f4f6;
			padding: 4px;
		}

		.chevron {
			color: var(--text-muted);
			transition: transform 0.3s ease;
		}

		.network-group.is-open .chevron {
			transform: rotate(180deg);
		}

		.network-body {
			display: none;
			padding: 0 16px 16px;
			border-top: 1px solid var(--border);
			background: #fafafa;
		}

		.network-group.is-open .network-body {
			display: block;
			animation: slideDown 0.3s ease;
		}

		@keyframes slideDown {
			from { opacity: 0; transform: translateY(-10px); }
			to { opacity: 1; transform: translateY(0); }
		}

		.currency-grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 12px;
			margin-top: 16px;
		}

		.method-card {
			border: 2px solid var(--border);
			border-radius: 12px;
			padding: 14px 12px;
			cursor: pointer;
			transition: all 0.2s ease;
			background: #fff;
			display: flex;
			align-items: center;
			gap: 10px;
			position: relative;
			overflow: hidden;
		}

		.method-card:hover {
			border-color: var(--primary);
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(0,0,0,0.05);
		}

		.method-card.selected {
			border-color: var(--primary);
			background: rgba(79, 70, 229, 0.04);
			box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
		}

		/* Selected indicator */
		.method-card::after {
			content: '';
			position: absolute;
			top: 0;
			right: 0;
			width: 0;
			height: 0;
			border-style: solid;
			border-width: 0 24px 24px 0;
			border-color: transparent var(--primary) transparent transparent;
			opacity: 0;
			transition: opacity 0.2s;
		}

		.method-card.selected::after {
			opacity: 1;
		}

		.method-card .check-icon {
			position: absolute;
			top: 2px;
			right: 2px;
			color: #fff;
			width: 10px;
			height: 10px;
			opacity: 0;
			z-index: 1;
			transition: opacity 0.2s;
		}

		.method-card.selected .check-icon {
			opacity: 1;
		}

		.currency-logo {
			width: 24px;
			height: 24px;
			object-fit: contain;
		}

		.method-currency {
			font-size: 15px;
			font-weight: 600;
			color: var(--text-main);
			line-height: 1;
		}

		.btn-submit {
			width: 100%;
			padding: 16px;
			background: var(--primary);
			color: #fff;
			border: none;
			border-radius: 12px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s ease;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
		}

		.btn-submit:hover:not(:disabled) {
			background: var(--primary-hover);
			transform: translateY(-1px);
			box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
		}

		.btn-submit:disabled {
			background: #9ca3af;
			cursor: not-allowed;
			box-shadow: none;
			transform: none;
		}

		.btn-cancel {
			display: block;
			text-align: center;
			margin-top: 20px;
			color: var(--text-muted);
			text-decoration: none;
			font-size: 14px;
			font-weight: 500;
			transition: color 0.2s ease;
		}

		.btn-cancel:hover {
			color: var(--text-main);
		}

		.loading-overlay {
			position: absolute;
			inset: 0;
			background: rgba(255, 255, 255, 0.8);
			backdrop-filter: blur(4px);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			border-radius: 24px;
			z-index: 10;
		}

		.spinner {
			width: 40px;
			height: 40px;
			border: 3px solid rgba(79, 70, 229, 0.1);
			border-left-color: var(--primary);
			border-radius: 50%;
			animation: spin 1s linear infinite;
			margin-bottom: 16px;
		}

		@keyframes spin {
			to {
				transform: rotate(360deg);
			}
		}

		.error-msg {
			padding: 12px 16px;
			background: #fef2f2;
			color: #b91c1c;
			border-radius: 8px;
			font-size: 14px;
			margin-bottom: 24px;
			display: none;
			border: 1px solid #fecaca;
		}

		.footer {
			text-align: center;
			padding-bottom: 24px;
			font-size: 13px;
			color: #9ca3af;
		}

		.footer a {
			color: #9ca3af;
			text-decoration: none;
		}

		.footer a:hover {
			text-decoration: underline;
		}
	</style>
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

	<script>
		function buildIconUrl(label, background) {
			const safeLabel = String(label || '?').slice(0, 4).toUpperCase();
			const svg = `
				<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28">
					<rect width="28" height="28" rx="14" fill="${background}"/>
					<text x="14" y="17" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" font-weight="700" fill="#ffffff">${safeLabel}</text>
				</svg>
			`;
			return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
		}

		function createIcon(className, label, background) {
			const img = document.createElement('img');
			img.className = className;
			img.alt = label;
			img.src = buildIconUrl(label, background);
			return img;
		}

		function createChevronIcon() {
			const svgNS = 'http://www.w3.org/2000/svg';
			const svg = document.createElementNS(svgNS, 'svg');
			svg.setAttribute('class', 'chevron');
			svg.setAttribute('width', '20');
			svg.setAttribute('height', '20');
			svg.setAttribute('viewBox', '0 0 24 24');
			svg.setAttribute('fill', 'none');
			svg.setAttribute('stroke', 'currentColor');
			svg.setAttribute('stroke-width', '2');
			svg.setAttribute('stroke-linecap', 'round');
			svg.setAttribute('stroke-linejoin', 'round');
			const polyline = document.createElementNS(svgNS, 'polyline');
			polyline.setAttribute('points', '6 9 12 15 18 9');
			svg.appendChild(polyline);
			return svg;
		}

		function createCheckIcon() {
			const svgNS = 'http://www.w3.org/2000/svg';
			const svg = document.createElementNS(svgNS, 'svg');
			svg.setAttribute('class', 'check-icon');
			svg.setAttribute('viewBox', '0 0 24 24');
			svg.setAttribute('fill', 'none');
			svg.setAttribute('stroke', 'currentColor');
			svg.setAttribute('stroke-width', '4');
			svg.setAttribute('stroke-linecap', 'round');
			svg.setAttribute('stroke-linejoin', 'round');
			const polyline = document.createElementNS(svgNS, 'polyline');
			polyline.setAttribute('points', '20 6 9 17 4 12');
			svg.appendChild(polyline);
			return svg;
		}

		document.addEventListener('DOMContentLoaded', () => {
			const container = document.getElementById('methodsContainer');
			const submitBtn = document.getElementById('submitBtn');
			const errorMsg = document.getElementById('errorMsg');
			const loadingOverlay = document.getElementById('loadingOverlay');
			const selectedMethodText = document.getElementById('selectedMethodText');

			const restUrl = <?php echo wp_json_encode($ponponpay_rest_url); ?>;
			const nonce = <?php echo wp_json_encode($ponponpay_nonce); ?>;
			const orderId = <?php echo wp_json_encode($ponponpay_order_id); ?>;
			const checkoutToken = <?php echo wp_json_encode($ponponpay_checkout_token); ?>;
			const i18n = <?php echo wp_json_encode($ponponpay_i18n); ?>;

			let selectedValue = null;

			function showError(msg) {
				errorMsg.textContent = msg;
				errorMsg.style.display = 'block';
				loadingOverlay.style.display = 'none';
			}

			// 1. Fetch available methods
			fetch(restUrl + 'methods', {
				method: 'GET',
				headers: { 'X-WP-Nonce': nonce }
			})
				.then(res => res.json())
				.then(data => {
					loadingOverlay.style.display = 'none';

					if (!data.success || !data.methods || data.methods.length === 0) {
						container.innerHTML = `<div style="text-align: center; color: #b91c1c; font-size: 14px; padding: 12px; background: #fef2f2; border-radius: 8px;">${i18n.no_payment_methods}</div>`;
						return showError(data.error || i18n.no_payment_methods_contact);
					}

					container.innerHTML = '';
					
					// Build Network Accordions
					data.methods.forEach((method, index) => {
						const network = method.network || '';
						const currencies = method.currencies || [];
						if(currencies.length === 0) return;

						const group = document.createElement('div');
						group.className = 'network-group';
						// Open the first network group by default
						if (index === 0) {
							group.classList.add('is-open');
						}

						// Construct Header
						const header = document.createElement('div');
						header.className = 'network-header';
						const title = document.createElement('div');
						title.className = 'network-title';
						title.appendChild(createIcon('network-logo', network, '#6366f1'));
						const titleText = document.createTextNode(network);
						title.appendChild(titleText);
						header.appendChild(title);
						header.appendChild(createChevronIcon());
						
						// Toggle accordion state
						header.addEventListener('click', () => {
							const isOpen = group.classList.contains('is-open');
							// Close all others
							document.querySelectorAll('.network-group').forEach(g => g.classList.remove('is-open'));
							// Toggle current
							if (!isOpen) {
								group.classList.add('is-open');
							}
						});

						// Construct Body & Cards
						const body = document.createElement('div');
						body.className = 'network-body';
						
						const grid = document.createElement('div');
						grid.className = 'currency-grid';

						currencies.forEach(curr => {
							const card = document.createElement('div');
							card.className = 'method-card';
							card.dataset.value = `${network}|${curr}`;
							card.appendChild(createIcon('currency-logo', curr, '#4f46e5'));
							const currencyText = document.createElement('div');
							currencyText.className = 'method-currency';
							currencyText.textContent = curr;
							card.appendChild(currencyText);
							card.appendChild(createCheckIcon());
							
							card.addEventListener('click', (e) => {
								e.stopPropagation(); // Prevent accordion toggle if clicking card
								document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
								card.classList.add('selected');
								selectedValue = card.dataset.value;
								selectedMethodText.textContent = `${network} / ${curr}`;
								submitBtn.disabled = false;
							});

							grid.appendChild(card);
						});

						body.appendChild(grid);
						group.appendChild(header);
						group.appendChild(body);
						container.appendChild(group);
					});
				})
				.catch(err => {
					showError(i18n.network_error_loading_methods);
				});

			// 2. Submit and create order
			submitBtn.addEventListener('click', () => {
				if (!selectedValue) return;

				const [network, currency] = selectedValue.split('|');

				loadingOverlay.style.display = 'flex';
				errorMsg.style.display = 'none';

				fetch(restUrl + 'create-order', {
					method: 'POST',
					headers: {
						'X-WP-Nonce': nonce,
						'Content-Type': 'application/json'
					},
						body: JSON.stringify({
							order_id: orderId,
							token: checkoutToken,
							network: network,
							currency: currency
						})
				})
					.then(res => res.json())
					.then(data => {
						if (data.success && data.payment_url) {
							const payWindow = window.open(data.payment_url, '_blank', 'noopener,noreferrer');
							loadingOverlay.style.display = 'none';
						} else {
							showError(data.error || i18n.failed_create_order);
						}
					})
					.catch(err => {
						showError(i18n.network_error_creating_order);
					});
			});
		});
	</script>

</body>

</html>
