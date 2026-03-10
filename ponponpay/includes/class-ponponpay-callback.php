<?php
/**
 * PonponPay 回调处理器
 *
 * 处理来自 PonponPay 后端的支付回调通知，更新 WooCommerce 订单状态
 *
 * @package PonponPay_WooCommerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PonponPay_Callback
{
	/**
	 * 回调鉴权时间窗口（秒）
	 */
	const SIGNATURE_WINDOW_SECONDS = 300;

	/**
	 * 构造函数 - 注册 WooCommerce API 回调
	 */
	public function __construct()
	{
		// 注册回调端点: /wc-api/ponponpay
		add_action('woocommerce_api_ponponpay', [$this, 'handle_callback']);
	}

	/**
	 * 处理回调请求
	 */
	public function handle_callback()
	{
		$logger = wc_get_logger();

		try {
			// 获取回调数据
			$input = file_get_contents('php://input');
			$logger->info('Callback received: ' . $input, ['source' => 'ponponpay']);

			$data = json_decode($input, true);

			if (!$data) {
				$data = map_deep(wp_unslash($_POST), 'sanitize_text_field');
			}

			// 验证必要字段
			if (empty($data['order_no']) || empty($data['status'])) {
				$logger->error('Callback missing required fields: order_no or status', ['source' => 'ponponpay']);
				wp_send_json_error('Missing required fields', 400);
				return;
			}

			$gateway = $this->get_gateway();

			if (!$gateway) {
				$logger->error('Gateway not configured', ['source' => 'ponponpay']);
				wp_send_json_error('Gateway not configured', 500);
				return;
			}

			$expected_api_key = $gateway->get_option('api_key');
			$auth_result = $this->validate_signature_headers(
				$expected_api_key,
				isset($_SERVER['HTTP_X_KEY_PREFIX']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_KEY_PREFIX'])) : '',
				isset($_SERVER['HTTP_X_TIMESTAMP']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_TIMESTAMP'])) : '',
				isset($_SERVER['HTTP_X_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_NONCE'])) : '',
				isset($_SERVER['HTTP_X_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_SIGNATURE'])) : '',
				$input
			);
			if (is_wp_error($auth_result)) {
				$logger->error('Signature validation failed: ' . $auth_result->get_error_message(), ['source' => 'ponponpay']);
				status_header((int)($auth_result->get_error_data('status') ?: 401));
				echo esc_html($auth_result->get_error_message());
				exit;
			}

			// 从订单号中提取 WooCommerce 订单 ID
			// 订单号格式: WC_{order_id}_{hash}
			if (!preg_match('/^WC_(\d+)_[a-zA-Z0-9]+$/', $data['order_no'], $matches)) {
				$logger->error('Invalid order number format: ' . $data['order_no'], ['source' => 'ponponpay']);
				wp_send_json_error('Invalid order number format', 400);
				return;
			}

			$order_id = (int) $matches[1];
			$order = wc_get_order($order_id);

			if (!$order) {
				$logger->error('Order not found: ' . $order_id, ['source' => 'ponponpay']);
				wp_send_json_error('Order not found', 404);
				return;
			}

			// 订单已支付，跳过处理
			if ($order->is_paid()) {
				$logger->info('Order already paid: ' . $order_id, ['source' => 'ponponpay']);
				echo 'OK';
				exit;
			}

			// 处理不同的支付状态
			// 1-等待支付，2-支付成功，3-已过期，4-取消支付，5-人工充值
			$status = $data['status'];

			$logger->info("Processing callback for order #{$order_id}, status: {$status}", ['source' => 'ponponpay']);

			switch ($status) {
				case 2:  // 支付成功
				case 5:  // 人工充值
					$this->handle_payment_success($order, $data);
					break;

				case 1:  // 等待支付
					$logger->info('Payment pending for order: ' . $order_id, ['source' => 'ponponpay']);
					break;

				case 3:  // 已过期
					$order->update_status('cancelled', __('PonponPay: Payment expired.', 'ponponpay-crypto-payment-gateway'));
					$logger->info('Payment expired for order: ' . $order_id, ['source' => 'ponponpay']);
					break;

				case 4:  // 取消支付
					$order->update_status('cancelled', __('PonponPay: Payment cancelled.', 'ponponpay-crypto-payment-gateway'));
					$logger->info('Payment cancelled for order: ' . $order_id, ['source' => 'ponponpay']);
					break;

				default:
					$logger->warning('Unknown payment status: ' . $status . ' for order: ' . $order_id, ['source' => 'ponponpay']);
					wp_send_json_error('Unknown payment status', 400);
					return;
			}

			echo 'OK';
			exit;

		} catch (Exception $e) {
			$logger->error('Callback error: ' . $e->getMessage(), ['source' => 'ponponpay']);
			status_header(500);
			echo 'Internal error';
			exit;
		}
	}

	/**
	 * 处理支付成功
	 *
	 * @param WC_Order $order WooCommerce 订单
	 * @param array    $data  回调数据
	 */
	private function handle_payment_success($order, $data)
	{
		$logger = wc_get_logger();

		// 获取交易信息
		$transaction_id = $data['transaction_id'] ?? $data['tx_hash'] ?? $data['order_no'];

		// 标记订单为已支付
		$order->payment_complete($transaction_id);

		// 添加订单备注
		$note = sprintf(
			/* translators: 1: transaction ID, 2: currency, 3: network */
			__('PonponPay payment completed. Transaction: %1$s, Currency: %2$s, Network: %3$s', 'ponponpay-crypto-payment-gateway'),
			$transaction_id,
			$data['currency'] ?? 'N/A',
			$data['network'] ?? 'N/A'
		);
		$order->add_order_note($note);

		// 保存交易详情
		$order->update_meta_data('_ponponpay_tx_hash', $data['tx_hash'] ?? '');
		$order->update_meta_data('_ponponpay_payment_amount', $data['amount'] ?? 0);
		$order->save();

		$logger->info('Payment completed for order #' . $order->get_id() . ', TX: ' . $transaction_id, ['source' => 'ponponpay']);
	}

	/**
	 * 获取网关实例
	 *
	 * @return PonponPay_Gateway|null
	 */
	private function get_gateway()
	{
		$gateways = WC()->payment_gateways()->payment_gateways();
		return $gateways['ponponpay'] ?? null;
	}

	/**
	 * 验证签名头与防重放。
	 *
	 * @param string $api_key API Key
	 * @param string $prefix Header: X-Key-Prefix
	 * @param string $timestamp Header: X-Timestamp
	 * @param string $nonce Header: X-Nonce
	 * @param string $signature Header: X-Signature
	 * @param string $raw_body 原始请求体
	 * @return true|WP_Error
	 */
	private function validate_signature_headers($api_key, $prefix, $timestamp, $nonce, $signature, $raw_body)
	{
		$api_key = trim((string)$api_key);
		$prefix = trim((string)$prefix);
		$timestamp = trim((string)$timestamp);
		$nonce = trim((string)$nonce);
		$signature = strtolower(trim((string)$signature));

		if ($api_key === '') {
			return new WP_Error('ponponpay_auth_error', 'Gateway API key not configured', ['status' => 500]);
		}

		if ($prefix === '' || $timestamp === '' || $nonce === '' || $signature === '') {
			return new WP_Error('ponponpay_auth_error', 'Missing signature headers', ['status' => 401]);
		}

		if (!ctype_digit($timestamp)) {
			return new WP_Error('ponponpay_auth_error', 'Invalid timestamp', ['status' => 401]);
		}

		$now = time();
		$ts = (int)$timestamp;
		if (abs($now - $ts) > self::SIGNATURE_WINDOW_SECONDS) {
			return new WP_Error('ponponpay_auth_error', 'Timestamp expired', ['status' => 401]);
		}

		$expected_prefix = substr($api_key, 0, 12);
		if ($prefix !== $expected_prefix) {
			return new WP_Error('ponponpay_auth_error', 'Invalid key prefix', ['status' => 401]);
		}

		if (!$this->consume_nonce($timestamp, $nonce)) {
			return new WP_Error('ponponpay_auth_error', 'Nonce already used', ['status' => 409]);
		}

		$key_hash = hash('sha256', $api_key);
		$payload = $timestamp . "\n" . $nonce . "\n" . $raw_body;
		$expected_signature = hash_hmac('sha256', $payload, $key_hash);

		if (!hash_equals($expected_signature, $signature)) {
			return new WP_Error('ponponpay_auth_error', 'Invalid signature', ['status' => 401]);
		}

		return true;
	}

	/**
	 * 消费 nonce，防重放。
	 *
	 * @param string $timestamp 时间戳
	 * @param string $nonce 随机串
	 * @return bool
	 */
	private function consume_nonce($timestamp, $nonce)
	{
		if (!preg_match('/^[A-Za-z0-9]{16,128}$/', $nonce)) {
			return false;
		}

		$key = 'ponponpay_nonce_' . hash('sha256', $timestamp . '|' . $nonce);
		if (get_transient($key)) {
			return false;
		}

		set_transient($key, 1, 10 * MINUTE_IN_SECONDS);
		return true;
	}
}
