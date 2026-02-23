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
			'permission_callback' => '__return_true',
		]);

		// 创建订单（前端收银台调用）
		register_rest_route($namespace, '/create-order', [
			'methods' => 'POST',
			'callback' => [$this, 'create_order'],
			'permission_callback' => '__return_true',
		]);

		// 获取支付方式（前端短代码调用）
		register_rest_route($namespace, '/methods', [
			'methods' => 'GET',
			'callback' => [$this, 'get_methods'],
			'permission_callback' => '__return_true',
		]);
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

		global $wpdb;
		$table = $wpdb->prefix . 'ponponpay_payments';
		$wpdb->insert($table, [
			'order_id' => $mch_order_id,
			'amount' => $amount,
			'fiat_currency' => $fiat_currency,
			'status' => 0, // 待支付
			'description' => $description,
			'redirect_url' => $redirect_url,
		]);

		$checkout_url = home_url('/?ponponpay_checkout=' . $mch_order_id);

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

		if (empty($order_id) || empty($network) || empty($currency)) {
			return new WP_REST_Response(['success' => false, 'error' => 'Missing parameters'], 400);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ponponpay_payments';
		$payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %s", $order_id));

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
				$amount = $wc_order->get_total();
				$redirect_url = $wc_order->get_checkout_order_received_url();
			} else {
				return new WP_REST_Response(['success' => false, 'error' => 'Order not found'], 404);
			}
		} else {
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
			$wpdb->update($table, [
				'trade_id' => $trade_id,
				'crypto_currency' => $currency,
				'network' => $network,
				'payment_url' => $payment_url,
			], ['order_id' => $order_id]);
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
		$data = $request->get_json_params();
		if (empty($data)) {
			$data = $request->get_body_params();
		}

		// 验证必要字段
		if (empty($data['order_no']) || empty($data['status'])) {
			$this->log('Callback missing required fields');
			return new WP_REST_Response('Missing required fields', 400);
		}

		// 验证 API Key
		$received_key = $request->get_header('X-API-Key');
		$expected_key = PonponPay_Settings::get_api_key();

		if (empty($received_key) || $received_key !== $expected_key) {
			$this->log('API Key validation failed');
			return new WP_REST_Response('Unauthorized', 401);
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
		global $wpdb;
		$table = $wpdb->prefix . 'ponponpay_payments';

		// 查询支付记录
		$payment = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %s", $order_no)
		);

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
				$wpdb->update($table, [
					'status' => 1,
					'tx_hash' => $tx_hash,
				], ['order_id' => $order_no]);
				$this->log("Payment completed: {$order_no}, TX: {$tx_hash}");

				// 触发 WordPress action 钩子
				do_action('ponponpay_payment_completed', $payment, $data);
				break;

			case 3: // 过期
				$wpdb->update($table, ['status' => 2], ['order_id' => $order_no]);
				do_action('ponponpay_payment_expired', $payment, $data);
				break;

			case 4: // 取消
				$wpdb->update($table, ['status' => 3], ['order_id' => $order_no]);
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
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[PonponPay] ' . $message);
		}
	}
}
