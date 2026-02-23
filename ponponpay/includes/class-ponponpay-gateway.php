<?php
/**
 * PonponPay WooCommerce 支付网关
 *
 * 继承 WC_Payment_Gateway，实现加密货币支付流程
 *
 * @package PonponPay_WooCommerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PonponPay_Gateway extends WC_Payment_Gateway
{
	/** @var PonponPay_API API 客户端实例 */
	private $api;

	/**
	 * 构造函数
	 */
	public function __construct()
	{
		$this->id = 'ponponpay';
		$this->icon = PONPONPAY_PLUGIN_URL . 'assets/images/ponponpay-icon.png';
		if (!file_exists(PONPONPAY_PLUGIN_DIR . 'assets/images/ponponpay-icon.png')) {
			$this->icon = '';
		}
		$this->has_fields = false;
		$this->method_title = __('PonponPay - Crypto Payment', 'ponponpay-woocommerce');
		$this->method_description = __('Accept cryptocurrency payments (USDT, USDC, etc.) via PonponPay. Supports Tron, Ethereum, BSC, Polygon, Solana networks.', 'ponponpay-woocommerce');
		$this->supports = ['products'];

		// 加载设置
		$this->init_form_fields();
		$this->init_settings();

		// 用户配置
		$this->title = $this->get_option('title', __('Crypto Payment (PonponPay)', 'ponponpay-woocommerce'));
		$this->description = $this->get_option('description', __('Pay with USDT, USDC and other cryptocurrencies via PonponPay.', 'ponponpay-woocommerce'));
		$this->enabled = $this->get_option('enabled', 'no');

		// 初始化 API 客户端
		$api_key = $this->get_option('api_key');
		if ($api_key) {
			$this->api = new PonponPay_API($api_key);
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
				'title' => __('Enable/Disable', 'ponponpay-woocommerce'),
				'type' => 'checkbox',
				'label' => __('Enable PonponPay Crypto Payment', 'ponponpay-woocommerce'),
				'default' => 'no',
			],
			'title' => [
				'title' => __('Title', 'ponponpay-woocommerce'),
				'type' => 'text',
				'description' => __('Payment method title displayed to customers at checkout.', 'ponponpay-woocommerce'),
				'default' => __('Crypto Payment (PonponPay)', 'ponponpay-woocommerce'),
				'desc_tip' => true,
			],
			'description' => [
				'title' => __('Description', 'ponponpay-woocommerce'),
				'type' => 'textarea',
				'description' => __('Payment method description displayed to customers at checkout.', 'ponponpay-woocommerce'),
				'default' => __('Pay with USDT, USDC and other cryptocurrencies. Supports Tron, Ethereum, BSC, Polygon, Solana networks.', 'ponponpay-woocommerce'),
				'desc_tip' => true,
			],
			'api_key' => [
				'title' => __('API Key', 'ponponpay-woocommerce'),
				'type' => 'text',
				'description' => sprintf(
					/* translators: %s: PonponPay console URL */
					__('Enter your PonponPay API Key. Get it from %s.', 'ponponpay-woocommerce'),
					'<a href="https://ponponpay.com" target="_blank">ponponpay.com</a>'
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

		$api = new PonponPay_API($api_key);
		$result = $api->activate_plugin();

		if (is_wp_error($result)) {
			WC_Admin_Settings::add_error(
				__('PonponPay API connection failed: ', 'ponponpay-woocommerce') . $result->get_error_message()
			);
			return;
		}

		if (!isset($result['code']) || $result['code'] != 0) {
			$error_msg = $result['message'] ?? __('Unknown error', 'ponponpay-woocommerce');
			WC_Admin_Settings::add_error(
				__('PonponPay plugin activation failed: ', 'ponponpay-woocommerce') . $error_msg
			);
			return;
		}

		WC_Admin_Settings::add_message(__('PonponPay API Key verified successfully!', 'ponponpay-woocommerce'));
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
			wc_add_notice(__('PonponPay is not properly configured. Please contact the store administrator.', 'ponponpay-woocommerce'), 'error');
			return ['result' => 'failure'];
		}

		try {
			// 更新订单状态为等待支付
			$order->update_status('pending', __('Awaiting PonponPay crypto payment.', 'ponponpay-woocommerce'));

			// 组合独立的收银台 URL
			$checkout_url = home_url('/?ponponpay_checkout=WC_' . $order_id);

			// 日志记录
			$this->log('Redirecting WC order to PonponPay checkout: WC_' . $order_id);

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
		$trade_id = $order->get_meta('_ponponpay_trade_id');
		$network = $order->get_meta('_ponponpay_network');
		$currency = $order->get_meta('_ponponpay_currency');

		if ($trade_id) {
			echo '<p><strong>' . esc_html__('PonponPay Trade ID:', 'ponponpay-woocommerce') . '</strong> ' . esc_html($trade_id) . '</p>';
			echo '<p><strong>' . esc_html__('Network:', 'ponponpay-woocommerce') . '</strong> ' . esc_html($network) . '</p>';
			echo '<p><strong>' . esc_html__('Currency:', 'ponponpay-woocommerce') . '</strong> ' . esc_html($currency) . '</p>';
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
		$logger->log($level, $message, ['source' => 'ponponpay']);
	}
}
