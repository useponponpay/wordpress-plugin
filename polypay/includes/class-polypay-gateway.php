<?php
/**
 * PolyPay WooCommerce 支付网关
 *
 * 继承 WC_Payment_Gateway，实现加密货币支付流程
 *
 * @package PolyPay_WooCommerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PolyPay_Gateway extends WC_Payment_Gateway
{
	/** @var PolyPay_API API 客户端实例 */
	private $api;

	/**
	 * 构造函数
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

		// 加载设置
		$this->init_form_fields();
		$this->init_settings();

		// 用户配置
		$this->title = $this->get_option('title', __('Crypto Payment (PolyPay)', 'polypay-crypto-payment-gateway'));
		$this->description = $this->get_option('description', __('Pay with USDT, USDC and other cryptocurrencies via PolyPay.', 'polypay-crypto-payment-gateway'));
		$this->enabled = $this->get_option('enabled', 'no');

		// 初始化 API 客户端
		$api_key = $this->get_option('api_key');
		if ($api_key) {
			$this->api = new PolyPay_API($api_key);
		}

		// 保存管理后台设置
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'validate_api_key']);
	}

	/**
	 * 定义管理后台配置字段
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
		];
	}

	/**
	 * 验证 API Key（保存设置时触发）
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
	 * 处理支付
	 *
	 * @param int $order_id WooCommerce 订单 ID
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
			// 更新订单状态为等待支付
			$order->update_status('pending', __('Awaiting PolyPay crypto payment.', 'polypay-crypto-payment-gateway'));

			// 组合独立的收银台 URL
			$checkout_url = polypay_build_checkout_url('WC_' . $order_id, $order->get_order_key());

			// 日志记录
			$this->log('Redirecting WC order to PolyPay checkout: WC_' . $order_id);

			// 清空购物车
			WC()->cart->empty_cart();

			// 跳转到支付选择页面
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
	 * 检查网关是否可用
	 *
	 * @return bool
	 */
	public function is_available()
	{
		if (!parent::is_available()) {
			return false;
		}

		// API Key 必须配置
		if (empty($this->get_option('api_key'))) {
			return false;
		}

		return true;
	}

	/**
	 * 管理后台 - 订单详情页显示支付信息
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
	 * 记录日志
	 *
	 * @param string $message 日志消息
	 * @param string $level   日志级别
	 */
	public function log($message, $level = 'info')
	{
		$logger = wc_get_logger();
		$logger->log($level, $message, ['source' => 'polypay']);
	}
}
