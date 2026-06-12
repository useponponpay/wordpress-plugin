<?php
/**
 * PolyPay callback handler
 *
 * Handles payment callback notifications from the PolyPay backend and updates WooCommerce order statuses
 *
 * @package PolyPay_WooCommerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PolyPay_Callback
{
	/**
	 * Callback authentication time window (seconds)
	 */
	const SIGNATURE_WINDOW_SECONDS = 300;

	/**
	 * Constructor - register the WooCommerce API callback
	 */
	public function __construct()
	{
		// Register the callback endpoint: /wc-api/polypay
		add_action('woocommerce_api_polypay', [$this, 'handle_callback']);
	}

	/**
	 * Handle the callback request
	 */
	public function handle_callback()
	{
		$logger = wc_get_logger();

		try {
			// Get the callback data
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- The raw request body is required for PolyPay signature verification.
			$input = file_get_contents('php://input');
			$logger->info(
				'Callback request received. Body length: ' . strlen((string)$input) . ', body hash: ' . hash('sha256', (string)$input),
				['source' => 'polypay']
			);

			$data = json_decode($input, true);

			if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The PolyPay server-side callback cannot carry a WordPress nonce; the request origin is verified below via signature headers.
				$data = map_deep(wp_unslash($_POST), 'sanitize_text_field');
			}

			// Sanitize the json_decode result field by field
			if (is_array($data)) {
				$data = map_deep($data, 'sanitize_text_field');
			}

			// Validate required fields
			if (empty($data['order_no']) || empty($data['status'])) {
				$logger->error('Callback missing required fields: order_no or status', ['source' => 'polypay']);
				wp_send_json_error('Missing required fields', 400);
				return;
			}

			$gateway = $this->get_gateway();

			if (!$gateway) {
				$logger->error('Gateway not configured', ['source' => 'polypay']);
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
				$logger->error('Signature validation failed: ' . $auth_result->get_error_message(), ['source' => 'polypay']);
				status_header((int)($auth_result->get_error_data('status') ?: 401));
				echo esc_html($auth_result->get_error_message());
				exit;
			}

			// Extract the WooCommerce order ID from the order number
			// Order number format: B{shortened merchant ID}_{order_id}, backward compatible with the legacy WC_ prefix format
			$order_id = polypay_parse_wc_order_id($data['order_no']);
			if ($order_id <= 0) {
				$logger->error('Invalid order number format: ' . $data['order_no'], ['source' => 'polypay']);
				wp_send_json_error('Invalid order number format', 400);
				return;
			}

			$order = wc_get_order($order_id);

			if (!$order) {
				$logger->error('Order not found: ' . $order_id, ['source' => 'polypay']);
				wp_send_json_error('Order not found', 404);
				return;
			}

			// Order already paid; skip processing
			if ($order->is_paid()) {
				$logger->info('Order already paid: ' . $order_id, ['source' => 'polypay']);
				echo 'OK';
				exit;
			}

			// Handle the different payment statuses
			// 1 - pending payment, 2 - payment successful, 3 - expired, 4 - payment cancelled, 5 - manual deposit
			$status = absint($data['status']);

			$logger->info("Processing callback for order #{$order_id}, status: {$status}", ['source' => 'polypay']);

			switch ($status) {
				case 2:  // Payment successful
				case 5:  // Manual deposit
					$this->handle_payment_success($order, $data);
					break;

				case 1:  // Pending payment
					$logger->info('Payment pending for order: ' . $order_id, ['source' => 'polypay']);
					break;

				case 3:  // Expired
					$order->update_status('cancelled', __('PolyPay: Payment expired.', 'polypay-crypto-payment-gateway'));
					$logger->info('Payment expired for order: ' . $order_id, ['source' => 'polypay']);
					break;

				case 4:  // Payment cancelled
					$order->update_status('cancelled', __('PolyPay: Payment cancelled.', 'polypay-crypto-payment-gateway'));
					$logger->info('Payment cancelled for order: ' . $order_id, ['source' => 'polypay']);
					break;

				default:
					$logger->warning('Unknown payment status: ' . $status . ' for order: ' . $order_id, ['source' => 'polypay']);
					wp_send_json_error('Unknown payment status', 400);
					return;
			}

			echo 'OK';
			exit;

		} catch (Exception $e) {
			$logger->error('Callback error: ' . $e->getMessage(), ['source' => 'polypay']);
			status_header(500);
			echo 'Internal error';
			exit;
		}
	}

	/**
	 * Handle a successful payment
	 *
	 * @param WC_Order $order WooCommerce order
	 * @param array    $data  Callback data
	 */
	private function handle_payment_success($order, $data)
	{
		$logger = wc_get_logger();

		// Get transaction information
		$transaction_id = $data['transaction_id'] ?? $data['tx_hash'] ?? $data['order_no'];

		// Mark the order as paid
		$order->payment_complete($transaction_id);

		// Add an order note
		$note = sprintf(
			/* translators: 1: transaction ID, 2: currency, 3: network */
			__('PolyPay payment completed. Transaction: %1$s, Currency: %2$s, Network: %3$s', 'polypay-crypto-payment-gateway'),
			$transaction_id,
			$data['currency'] ?? 'N/A',
			$data['network'] ?? 'N/A'
		);
		$order->add_order_note($note);

		// Save transaction details
		$order->update_meta_data('_polypay_tx_hash', $data['tx_hash'] ?? '');
		$order->update_meta_data('_polypay_payment_amount', $data['amount'] ?? 0);
		$order->save();

		$logger->info('Payment completed for order #' . $order->get_id() . ', TX: ' . $transaction_id, ['source' => 'polypay']);
	}

	/**
	 * Get the gateway instance
	 *
	 * @return PolyPay_Gateway|null
	 */
	private function get_gateway()
	{
		$gateways = WC()->payment_gateways()->payment_gateways();
		return $gateways['polypay'] ?? null;
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
