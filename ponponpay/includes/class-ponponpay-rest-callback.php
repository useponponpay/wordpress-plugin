<?php
/**
 * PonponPay REST API 回调
 *
 * 提供独立的 REST API 端点，不依赖 WooCommerce：
 * - POST /wp-json/ponponpay/v1/callback     — 接收 PonponPay 支付回调
 * - POST /wp-json/ponponpay/v1/create-order — 前端创建支付订单
 * - GET  /wp-json/ponponpay/v1/methods      — 获取可用支付方式
 *
 * @package PonponPay
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PonponPay_REST_Callback
{
	/**
	 * 回调鉴权时间窗口（秒）
	 */
	const SIGNATURE_WINDOW_SECONDS = 300;

	/**
	 * 构造函数
	 */
	public function __construct()
	{
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	/**
	 * 注册 REST 路由
	 */
	public function register_routes()
	{
		$namespace = 'ponponpay/v1';

		// 回调端点（PonponPay 后端调用）
		register_rest_route($namespace, '/callback', [
			'methods' => 'POST',
			'callback' => [$this, 'handle_callback'],
			'permission_callback' => '__return_true',
		]);

		// 初始化订单（前端短代码调用）
		register_rest_route($namespace, '/init-payment', [
			'methods' => 'POST',
			'callback' => [$this, 'init_payment'],
			'permission_callback' => [$this, 'verify_frontend_request'],
		]);

		// 创建订单（前端收银台调用）
		register_rest_route($namespace, '/create-order', [
			'methods' => 'POST',
			'callback' => [$this, 'create_order'],
			'permission_callback' => [$this, 'verify_frontend_request'],
		]);

		// 获取支付方式（前端短代码调用）
		register_rest_route($namespace, '/methods', [
			'methods' => 'GET',
			'callback' => [$this, 'get_methods'],
			'permission_callback' => [$this, 'verify_frontend_request'],
		]);
	}

	/**
	 * 校验前端发起的 REST 请求。
	 *
	 * @param WP_REST_Request $request
	 * @return true|WP_Error
	 */
	public function verify_frontend_request($request)
	{
		$nonce = $request->get_header('X-WP-Nonce');
		if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
			return new WP_Error('ponponpay_forbidden', 'Invalid request nonce', ['status' => 403]);
		}

		return true;
	}

	/**
	 * 获取支付方式
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_methods($request)
	{
		$api_key = PonponPay_Settings::get_api_key();
		if (empty($api_key)) {
			return new WP_REST_Response(['success' => false, 'error' => 'Not configured'], 500);
		}

		$api = new PonponPay_API($api_key);
		$result = $api->get_payment_methods();

		if (is_wp_error($result)) {
			return new WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], 500);
		}

		$methods = $result['data']['methods'] ?? [];
		return new WP_REST_Response(['success' => true, 'methods' => $methods]);
	}

	/**
	 * 初始化支付订单（仅生成本地记录，供短代码等使用）
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function init_payment($request)
	{
		$api_key = PonponPay_Settings::get_api_key();
		if (empty($api_key)) {
			return new WP_REST_Response(['success' => false, 'error' => 'Not configured'], 500);
		}

		$amount = floatval($request->get_param('amount'));
		$fiat_currency = sanitize_text_field($request->get_param('fiat_currency') ?: 'USD');
		$description = sanitize_text_field($request->get_param('description') ?: '');
		$redirect_url = esc_url_raw($request->get_param('redirect_url') ?: '');

		if ($amount <= 0) {
			return new WP_REST_Response(['success' => false, 'error' => 'Invalid amount'], 400);
		}

		// 生成商户订单号
		$mch_order_id = 'WP_' . time() . '_' . substr(md5($api_key . '_' . $amount . '_' . wp_generate_uuid4()), 0, 8);

		ponponpay_insert_payment([
			'order_id' => $mch_order_id,
			'amount' => $amount,
			'fiat_currency' => $fiat_currency,
			'status' => 0, // 待支付
			'description' => $description,
			'redirect_url' => $redirect_url,
		]);

		$checkout_url = ponponpay_build_checkout_url($mch_order_id);

		return new WP_REST_Response([
			'success' => true,
			'order_id' => $mch_order_id,
			'checkout_url' => $checkout_url,
		]);
	}

	/**
	 * 创建支付订单（调用 PonponPay API 下单）
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function create_order($request)
	{
		$api_key = PonponPay_Settings::get_api_key();
		if (empty($api_key)) {
			return new WP_REST_Response(['success' => false, 'error' => 'Not configured'], 500);
		}

		$order_id = sanitize_text_field($request->get_param('order_id'));
		$network = sanitize_text_field($request->get_param('network'));
		$currency = sanitize_text_field($request->get_param('currency'));
		$checkout_token = sanitize_text_field($request->get_param('token'));

		if (empty($order_id) || empty($network) || empty($currency) || empty($checkout_token)) {
			return new WP_REST_Response(['success' => false, 'error' => 'Missing parameters'], 400);
		}

		$payment = ponponpay_get_payment($order_id);

		$amount = 0;
		$redirect_url = '';

		if (!$payment) {
			// 可能是 WooCommerce 订单
			if (strpos($order_id, 'WC_') === 0 && class_exists('WC_Order')) {
				$wc_order_id = str_replace('WC_', '', $order_id);
				$wc_order = wc_get_order($wc_order_id);
				if (!$wc_order) {
					return new WP_REST_Response(['success' => false, 'error' => 'Order not found'], 404);
				}

				$expected_token = ponponpay_create_checkout_token($order_id, $wc_order->get_order_key());
				if (!hash_equals($expected_token, $checkout_token)) {
					return new WP_REST_Response(['success' => false, 'error' => 'Invalid checkout token'], 403);
				}

				$amount = $wc_order->get_total();
				$redirect_url = $wc_order->get_checkout_order_received_url();
			} else {
				return new WP_REST_Response(['success' => false, 'error' => 'Order not found'], 404);
			}
		} else {
			$expected_token = ponponpay_create_checkout_token($order_id);
			if (!hash_equals($expected_token, $checkout_token)) {
				return new WP_REST_Response(['success' => false, 'error' => 'Invalid checkout token'], 403);
			}

			$amount = $payment->amount;
			$redirect_url = $payment->redirect_url;
		}

		$notify_url = rest_url('ponponpay/v1/callback');

		$api = new PonponPay_API($api_key);
		$result = $api->create_order([
			'mch_order_id' => $order_id,
			'currency' => $currency,
			'network' => $network,
			'amount' => floatval($amount),
			'notify_url' => $notify_url,
			'redirect_url' => $redirect_url,
		]);

		if (is_wp_error($result)) {
			return new WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], 500);
		}

		if (!isset($result['code']) || $result['code'] != 0) {
			return new WP_REST_Response(['success' => false, 'error' => $result['message'] ?? 'Failed'], 500);
		}

		$trade_id = $result['data']['trade_id'] ?? '';
		$payment_url = $result['data']['payment_url'] ?? '';

		if ($payment) {
			ponponpay_update_payment($order_id, [
				'trade_id' => $trade_id,
				'crypto_currency' => $currency,
				'network' => $network,
				'payment_url' => $payment_url,
			]);
		} else if (isset($wc_order)) {
			$wc_order->add_meta_data('_ponponpay_trade_id', $trade_id, true);
			$wc_order->add_meta_data('_ponponpay_payment_url', $payment_url, true);
			$wc_order->add_meta_data('_ponponpay_network', $network, true);
			$wc_order->add_meta_data('_ponponpay_currency', $currency, true);
			$wc_order->save();
		}

		$this->log("Order API created: {$order_id}, Trade ID: {$trade_id}");

		return new WP_REST_Response([
			'success' => true,
			'payment_url' => $payment_url,
			'trade_id' => $trade_id,
		]);
	}

	/**
	 * 处理 PonponPay 支付回调
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_callback($request)
	{
		$raw_body = $request->get_body();
		$data = $request->get_json_params();
		if (empty($data)) {
			$data = $request->get_body_params();
		}

		// 对 JSON 解码后的数据进行字段级消毒
		if (is_array($data)) {
			$data = array_map(function ($value) {
				return is_string($value) ? sanitize_text_field($value) : $value;
			}, $data);
		}

		// 验证必要字段
		if (empty($data['order_no']) || empty($data['status'])) {
			$this->log('Callback missing required fields');
			return new WP_REST_Response('Missing required fields', 400);
		}

		$auth_result = $this->validate_signature_headers(
			PonponPay_Settings::get_api_key(),
			$request->get_header('X-Key-Prefix'),
			$request->get_header('X-Timestamp'),
			$request->get_header('X-Nonce'),
			$request->get_header('X-Signature'),
			$raw_body
		);
		if (is_wp_error($auth_result)) {
			$this->log('Signature validation failed: ' . $auth_result->get_error_message());
			return new WP_REST_Response($auth_result->get_error_message(), (int)($auth_result->get_error_data('status') ?: 401));
		}

		$order_no = sanitize_text_field($data['order_no']);
		$status = intval($data['status']);

		$this->log("Callback received: {$order_no}, status: {$status}");

		// 判断订单来源
		if (strpos($order_no, 'WP_') === 0) {
			// 独立支付记录
			return $this->process_standalone_callback($order_no, $status, $data);
		}

		if (strpos($order_no, 'WC_') === 0 && class_exists('WooCommerce')) {
			// WooCommerce 订单 — 由 WooCommerce 回调处理器处理
			$this->log("WC order, delegating to WC callback");
			return new WP_REST_Response('OK');
		}

		$this->log("Unknown order prefix: {$order_no}");
		return new WP_REST_Response('Unknown order format', 400);
	}

	/**
	 * 处理独立支付回调
	 *
	 * @param string $order_no 订单号
	 * @param int    $status   状态
	 * @param array  $data     回调数据
	 * @return WP_REST_Response
	 */
	private function process_standalone_callback($order_no, $status, $data)
	{
		$payment = ponponpay_get_payment($order_no);

		if (!$payment) {
			$this->log("Payment not found: {$order_no}");
			return new WP_REST_Response('Payment not found', 404);
		}

		// 已完成的不再处理
		if ($payment->status == 1) {
			$this->log("Payment already completed: {$order_no}");
			return new WP_REST_Response('OK');
		}

		$tx_hash = $data['tx_hash'] ?? $data['transaction_id'] ?? '';

		switch ($status) {
			case 2: // 支付成功
			case 5: // 人工充值
				ponponpay_update_payment($order_no, [
					'status' => 1,
					'tx_hash' => $tx_hash,
				]);
				$this->log("Payment completed: {$order_no}, TX: {$tx_hash}");

				// 触发 WordPress action 钩子
				do_action('ponponpay_payment_completed', $payment, $data);
				break;

			case 3: // 过期
				ponponpay_update_payment($order_no, ['status' => 2]);
				do_action('ponponpay_payment_expired', $payment, $data);
				break;

			case 4: // 取消
				ponponpay_update_payment($order_no, ['status' => 3]);
				do_action('ponponpay_payment_cancelled', $payment, $data);
				break;
		}

		return new WP_REST_Response('OK');
	}

	/**
	 * 记录日志
	 *
	 * @param string $message
	 */
	private function log($message)
	{
		if (function_exists('wc_get_logger')) {
			wc_get_logger()->info($message, ['source' => 'ponponpay']);
			return;
		}

		if (function_exists('wp_debug_log')) {
			wp_debug_log('[PonponPay] ' . $message);
		}
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
