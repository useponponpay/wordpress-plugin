<?php
/**
 * PolyPay WooCommerce payment gateway
 *
 * Extends WC_Payment_Gateway to implement the cryptocurrency payment flow
 *
 * @package PolyPay_WooCommerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PolyPay_Gateway extends WC_Payment_Gateway
{
	/** @var PolyPay_API API client instance */
	private $api;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->id = 'polypay';
		$this->icon = POLYPAY_PLUGIN_URL . 'assets/images/polypay-icon.png';
		if (!file_exists(POLYPAY_PLUGIN_DIR . 'assets/images/polypay-icon.png')) {
			$this->icon = '';
		}
		$this->has_fields = false;
		$this->method_title = __('PolyPay - Crypto Payment', 'polypay-crypto-payment-gateway');
		$this->method_description = __('Accept cryptocurrency payments (USDT, USDC, etc.) via PolyPay. Supports Tron, Ethereum, BSC, Polygon, Solana networks.', 'polypay-crypto-payment-gateway');
		$this->supports = ['products'];

		// Load settings
		$this->init_form_fields();
		$this->init_settings();

		// User configuration
		$this->title = $this->get_option('title', __('Crypto Payment (PolyPay)', 'polypay-crypto-payment-gateway'));
		$this->description = $this->get_option('description', __('Pay with USDT, USDC and other cryptocurrencies via PolyPay.', 'polypay-crypto-payment-gateway'));
		$this->enabled = $this->get_option('enabled', 'no');

		// Initialize the API client
		$api_key = $this->get_option('api_key');
		if ($api_key) {
			$this->api = new PolyPay_API($api_key);
		}

		// Save admin settings
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'validate_api_key']);
	}

	/**
	 * Define the admin configuration fields
	 */
	public function init_form_fields()
	{
		$this->form_fields = [
			'enabled' => [
				'title' => __('Enable/Disable', 'polypay-crypto-payment-gateway'),
				'type' => 'checkbox',
				'label' => __('Enable PolyPay Crypto Payment', 'polypay-crypto-payment-gateway'),
				'default' => 'no',
			],
			'title' => [
				'title' => __('Title', 'polypay-crypto-payment-gateway'),
				'type' => 'text',
				'description' => __('Payment method title displayed to customers at checkout.', 'polypay-crypto-payment-gateway'),
				'default' => __('Crypto Payment (PolyPay)', 'polypay-crypto-payment-gateway'),
				'desc_tip' => true,
			],
			'description' => [
				'title' => __('Description', 'polypay-crypto-payment-gateway'),
				'type' => 'textarea',
				'description' => __('Payment method description displayed to customers at checkout.', 'polypay-crypto-payment-gateway'),
				'default' => __('Pay with USDT, USDC and other cryptocurrencies. Supports Tron, Ethereum, BSC, Polygon, Solana networks.', 'polypay-crypto-payment-gateway'),
				'desc_tip' => true,
			],
			'api_key' => [
				'title' => __('API Key', 'polypay-crypto-payment-gateway'),
				'type' => 'text',
				'description' => sprintf(
					/* translators: %s: PolyPay console URL */
					__('Enter your PolyPay API Key. Get it from %s.', 'polypay-crypto-payment-gateway'),
					'<a href="https://polypay.ai" target="_blank">polypay.ai</a>'
				),
				'default' => '',
			],
			'mch_id' => [
				'title' => __('Merchant ID', 'polypay-crypto-payment-gateway'),
				'type' => 'text',
				'description' => __('Merchant ID from the PolyPay console (e.g. MCH17790986189696). Used as a short prefix of the merchant order number; falls back to an API Key derived identifier when empty.', 'polypay-crypto-payment-gateway'),
				'default' => '',
				'desc_tip' => true,
			],
		];
	}

	/**
	 * Validate the API Key (triggered when settings are saved)
	 */
	public function validate_api_key()
	{
		$api_key = $this->get_option('api_key');
		if (empty($api_key)) {
			return;
		}

		$api = new PolyPay_API($api_key);
		$result = $api->activate_plugin();

		if (is_wp_error($result)) {
			WC_Admin_Settings::add_error(
				__('PolyPay API connection failed: ', 'polypay-crypto-payment-gateway') . $result->get_error_message()
			);
			return;
		}

		if (!isset($result['code']) || $result['code'] != 0) {
			$error_msg = $result['message'] ?? __('Unknown error', 'polypay-crypto-payment-gateway');
			WC_Admin_Settings::add_error(
				__('PolyPay plugin activation failed: ', 'polypay-crypto-payment-gateway') . $error_msg
			);
			return;
		}

		WC_Admin_Settings::add_message(__('PolyPay API Key verified successfully!', 'polypay-crypto-payment-gateway'));
	}

	/**
	 * Process the payment
	 *
	 * @param int $order_id WooCommerce order ID
	 * @return array
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);

		if (!$this->api) {
			wc_add_notice(__('PolyPay is not properly configured. Please contact the store administrator.', 'polypay-crypto-payment-gateway'), 'error');
			return ['result' => 'failure'];
		}

		try {
			// Update the order status to pending payment
			$order->update_status('pending', __('Awaiting PolyPay crypto payment.', 'polypay-crypto-payment-gateway'));

			// Build the standalone checkout URL (order number format: B{shortened merchant ID}_{order_id})
			$order_no = polypay_build_wc_order_no($order_id);
			$checkout_url = polypay_build_checkout_url($order_no, $order->get_order_key());

			// Log the action
			$this->log('Redirecting WC order to PolyPay checkout: ' . $order_no);

			// Empty the cart
			WC()->cart->empty_cart();

			// Redirect to the payment method selection page
			return [
				'result' => 'success',
				'redirect' => $checkout_url,
			];

		} catch (Exception $e) {
			$this->log('Payment error: ' . $e->getMessage());
			wc_add_notice($e->getMessage(), 'error');
			return ['result' => 'failure'];
		}
	}

	/**
	 * Check whether the gateway is available
	 *
	 * @return bool
	 */
	public function is_available()
	{
		if (!parent::is_available()) {
			return false;
		}

		// The API Key must be configured
		if (empty($this->get_option('api_key'))) {
			return false;
		}

		return true;
	}

	/**
	 * Admin - display payment information on the order details page
	 *
	 * @param WC_Order $order
	 */
	public function admin_order_data($order)
	{
		$trade_id = $order->get_meta('_polypay_trade_id');
		$network = $order->get_meta('_polypay_network');
		$currency = $order->get_meta('_polypay_currency');

		if ($trade_id) {
			echo '<p><strong>' . esc_html__('PolyPay Trade ID:', 'polypay-crypto-payment-gateway') . '</strong> ' . esc_html($trade_id) . '</p>';
			echo '<p><strong>' . esc_html__('Network:', 'polypay-crypto-payment-gateway') . '</strong> ' . esc_html($network) . '</p>';
			echo '<p><strong>' . esc_html__('Currency:', 'polypay-crypto-payment-gateway') . '</strong> ' . esc_html($currency) . '</p>';
		}
	}

	/**
	 * Write a log entry
	 *
	 * @param string $message Log message
	 * @param string $level   Log level
	 */
	public function log($message, $level = 'info')
	{
		$logger = wc_get_logger();
		$logger->log($level, $message, ['source' => 'polypay']);
	}
}
