<?php
/**
 * PolyPay REST API callback
 *
 * Provides standalone REST API endpoints, without depending on WooCommerce:
 * - POST /wp-json/polypay/v1/callback     — Receive PolyPay payment callbacks
 * - POST /wp-json/polypay/v1/create-order — Create payment orders from the front end
 * - GET  /wp-json/polypay/v1/methods      — Get available payment methods
 *
 * @package PolyPay
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PolyPay_REST_Callback
{
	/**
	 * Callback authentication time window (seconds)
	 */
	const SIGNATURE_WINDOW_SECONDS = 300;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	/**
	 * Register REST routes
	 */
	public function register_routes()
	{
		$namespace = 'polypay/v1';

		// Callback endpoint (called by the PolyPay backend)
		register_rest_route($namespace, '/callback', [
			'methods' => 'POST',
			'callback' => [$this, 'handle_callback'],
			'permission_callback' => '__return_true',
		]);

		// Initialize an order (called by the front-end shortcode)
		register_rest_route($namespace, '/init-payment', [
			'methods' => 'POST',
			'callback' => [$this, 'init_payment'],
			'permission_callback' => [$this, 'verify_frontend_request'],
		]);

		// Create an order (called by the front-end checkout)
		register_rest_route($namespace, '/create-order', [
			'methods' => 'POST',
			'callback' => [$this, 'create_order'],
			'permission_callback' => [$this, 'verify_frontend_request'],
		]);

		// Get payment methods (called by the front-end shortcode)
		register_rest_route($namespace, '/methods', [
			'methods' => 'GET',
			'callback' => [$this, 'get_methods'],
			'permission_callback' => [$this, 'verify_frontend_request'],
		]);
	}

	/**
	 * Verify a REST request initiated from the front end.
	 *
	 * @param WP_REST_Request $request
	 * @return true|WP_Error
	 */
	public function verify_frontend_request($request)
	{
		$nonce = $request->get_header('X-WP-Nonce');
		if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
			return new WP_Error('polypay_forbidden', 'Invalid request nonce', ['status' => 403]);
		}

		return true;
	}

	/**
	 * Get payment methods
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_methods($request)
	{
		$api_key = PolyPay_Settings::get_api_key();
		if (empty($api_key)) {
			return new WP_REST_Response(['success' => false, 'error' => 'Not configured'], 500);
		}

		$api = new PolyPay_API($api_key);
		$result = $api->get_payment_methods();

		if (is_wp_error($result)) {
			return new WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], 500);
		}

		$methods = $result['data']['methods'] ?? [];
		return new WP_REST_Response(['success' => true, 'methods' => $methods]);
	}

	/**
	 * Initialize a payment order (only creates a local record, for use by shortcodes etc.)
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function init_payment($request)
	{
		$api_key = PolyPay_Settings::get_api_key();
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

		// Generate the merchant order number (B{shortened merchant ID}_{timestamp}_{random string}; standalone payment orders are identified via the local payment table)
		$mch_order_id = 'B' . polypay_merchant_short_id() . '_' . time() . '_' . substr(md5($api_key . '_' . $amount . '_' . wp_generate_uuid4()), 0, 8);

		polypay_insert_payment([
			'order_id' => $mch_order_id,
			'amount' => $amount,
			'fiat_currency' => $fiat_currency,
			'status' => 0, // Pending payment
			'description' => $description,
			'redirect_url' => $redirect_url,
		]);

		$checkout_url = polypay_build_checkout_url($mch_order_id);

		return new WP_REST_Response([
			'success' => true,
			'order_id' => $mch_order_id,
			'checkout_url' => $checkout_url,
		]);
	}

	/**
	 * Create a payment order (places the order via the PolyPay API)
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function create_order($request)
	{
		$api_key = PolyPay_Settings::get_api_key();
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

		$payment = polypay_get_payment($order_id);

		$amount = 0;
		$redirect_url = '';

		if (!$payment) {
			// May be a WooCommerce order (B{shortened merchant ID}_{order_id}, backward compatible with the legacy WC_ prefix)
			$wc_order_id = polypay_parse_wc_order_id($order_id);
			if ($wc_order_id > 0 && class_exists('WC_Order')) {
				$wc_order = wc_get_order($wc_order_id);
				if (!$wc_order) {
					return new WP_REST_Response(['success' => false, 'error' => 'Order not found'], 404);
				}

				$expected_token = polypay_create_checkout_token($order_id, $wc_order->get_order_key());
				if (!hash_equals($expected_token, $checkout_token)) {
					return new WP_REST_Response(['success' => false, 'error' => 'Invalid checkout token'], 403);
				}

				$amount = $wc_order->get_total();
				$redirect_url = $wc_order->get_checkout_order_received_url();
			} else {
				return new WP_REST_Response(['success' => false, 'error' => 'Order not found'], 404);
			}
		} else {
			$expected_token = polypay_create_checkout_token($order_id);
			if (!hash_equals($expected_token, $checkout_token)) {
				return new WP_REST_Response(['success' => false, 'error' => 'Invalid checkout token'], 403);
			}

			$amount = $payment->amount;
			$redirect_url = $payment->redirect_url;
		}

		$notify_url = rest_url('polypay/v1/callback');

		$api = new PolyPay_API($api_key);
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
			polypay_update_payment($order_id, [
				'trade_id' => $trade_id,
				'crypto_currency' => $currency,
				'network' => $network,
				'payment_url' => $payment_url,
			]);
		} else if (isset($wc_order)) {
			$wc_order->add_meta_data('_polypay_trade_id', $trade_id, true);
			$wc_order->add_meta_data('_polypay_payment_url', $payment_url, true);
			$wc_order->add_meta_data('_polypay_network', $network, true);
			$wc_order->add_meta_data('_polypay_currency', $currency, true);
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
	 * Handle the PolyPay payment callback
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

		// Sanitize the JSON-decoded data field by field
		if (is_array($data)) {
			$data = map_deep($data, 'sanitize_text_field');
		}

		// Validate required fields
		if (empty($data['order_no']) || empty($data['status'])) {
			$this->log('Callback missing required fields');
			return new WP_REST_Response('Missing required fields', 400);
		}

		$auth_result = $this->validate_signature_headers(
			PolyPay_Settings::get_api_key(),
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

		// Determine the order source: standalone payment orders are recorded in the local payment table (backward compatible with the legacy WP_ prefix)
		if (strpos($order_no, 'WP_') === 0 || polypay_get_payment($order_no)) {
			return $this->process_standalone_callback($order_no, $status, $data);
		}

		if (polypay_parse_wc_order_id($order_no) > 0 && class_exists('WooCommerce')) {
			// WooCommerce order — handled by the WooCommerce callback handler
			$this->log("WC order, delegating to WC callback");
			return new WP_REST_Response('OK');
		}

		$this->log("Unknown order prefix: {$order_no}");
		return new WP_REST_Response('Unknown order format', 400);
	}

	/**
	 * Handle a standalone payment callback
	 *
	 * @param string $order_no Order number
	 * @param int    $status   Status
	 * @param array  $data     Callback data
	 * @return WP_REST_Response
	 */
	private function process_standalone_callback($order_no, $status, $data)
	{
		$payment = polypay_get_payment($order_no);

		if (!$payment) {
			$this->log("Payment not found: {$order_no}");
			return new WP_REST_Response('Payment not found', 404);
		}

		// Already completed payments are not processed again
		if ($payment->status == 1) {
			$this->log("Payment already completed: {$order_no}");
			return new WP_REST_Response('OK');
		}

		$tx_hash = $data['tx_hash'] ?? $data['transaction_id'] ?? '';

		switch ($status) {
			case 2: // Payment successful
			case 5: // Manual deposit
				polypay_update_payment($order_no, [
					'status' => 1,
					'tx_hash' => $tx_hash,
				]);
				$this->log("Payment completed: {$order_no}, TX: {$tx_hash}");

				// Fire the WordPress action hook
				do_action('polypay_payment_completed', $payment, $data);
				break;

			case 3: // Expired
				polypay_update_payment($order_no, ['status' => 2]);
				do_action('polypay_payment_expired', $payment, $data);
				break;

			case 4: // Cancelled
				polypay_update_payment($order_no, ['status' => 3]);
				do_action('polypay_payment_cancelled', $payment, $data);
				break;
		}

		return new WP_REST_Response('OK');
	}

	/**
	 * Write a log entry
	 *
	 * @param string $message
	 */
	private function log($message)
	{
		if (function_exists('wc_get_logger')) {
			wc_get_logger()->info($message, ['source' => 'polypay']);
			return;
		}

		if (function_exists('wp_debug_log')) {
			wp_debug_log('[PolyPay] ' . $message);
		}
	}

	/**
	 * Validate signature headers and prevent replay attacks.
	 *
	 * @param string $api_key API Key
	 * @param string $prefix Header: X-Key-Prefix
	 * @param string $timestamp Header: X-Timestamp
	 * @param string $nonce Header: X-Nonce
	 * @param string $signature Header: X-Signature
	 * @param string $raw_body Raw request body
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
			return new WP_Error('polypay_auth_error', 'Gateway API key not configured', ['status' => 500]);
		}

		if ($prefix === '' || $timestamp === '' || $nonce === '' || $signature === '') {
			return new WP_Error('polypay_auth_error', 'Missing signature headers', ['status' => 401]);
		}

		if (!ctype_digit($timestamp)) {
			return new WP_Error('polypay_auth_error', 'Invalid timestamp', ['status' => 401]);
		}

		$now = time();
		$ts = (int)$timestamp;
		if (abs($now - $ts) > self::SIGNATURE_WINDOW_SECONDS) {
			return new WP_Error('polypay_auth_error', 'Timestamp expired', ['status' => 401]);
		}

		$expected_prefix = substr($api_key, 0, 12);
		if ($prefix !== $expected_prefix) {
			return new WP_Error('polypay_auth_error', 'Invalid key prefix', ['status' => 401]);
		}

		if (!$this->consume_nonce($timestamp, $nonce)) {
			return new WP_Error('polypay_auth_error', 'Nonce already used', ['status' => 409]);
		}

		$key_hash = hash('sha256', $api_key);
		$payload = $timestamp . "\n" . $nonce . "\n" . $raw_body;
		$expected_signature = hash_hmac('sha256', $payload, $key_hash);

		if (!hash_equals($expected_signature, $signature)) {
			return new WP_Error('polypay_auth_error', 'Invalid signature', ['status' => 401]);
		}

		return true;
	}

	/**
	 * Consume the nonce to prevent replay attacks.
	 *
	 * @param string $timestamp Timestamp
	 * @param string $nonce Random string
	 * @return bool
	 */
	private function consume_nonce($timestamp, $nonce)
	{
		if (!preg_match('/^[A-Za-z0-9]{16,128}$/', $nonce)) {
			return false;
		}

		$key = 'polypay_nonce_' . hash('sha256', $timestamp . '|' . $nonce);
		if (get_transient($key)) {
			return false;
		}

		set_transient($key, 1, 10 * MINUTE_IN_SECONDS);
		return true;
	}
}
