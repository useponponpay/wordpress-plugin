<?php
/**
 * PonponPay API 客户端
 *
 * 封装与 PonponPay 后端 API 的所有交互
 *
 * @package PonponPay_WooCommerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PonponPay_API
{
	/** @var string API 基础 URL */
	private $api_url;

	/** @var string API Key */
	private $api_key;

	/** @var int 请求超时时间（秒） */
	private $timeout;

	/**
	 * 构造函数
	 *
	 * @param string $api_key API Key
	 * @param string $api_url API 基础 URL
	 * @param int    $timeout 超时时间
	 */
	public function __construct($api_key, $api_url = '', $timeout = 30)
	{
		$this->api_key = $api_key;
		$this->api_url = $api_url ?: PONPONPAY_API_URL;
		$this->timeout = $timeout;
	}

	/**
	 * 获取商户支持的支付方式
	 *
	 * @return array|WP_Error
	 */
	public function get_payment_methods()
	{
		return $this->request('/api/v1/pay/sdk/payment-methods', []);
	}

	/**
	 * 创建支付订单
	 *
	 * @param array $params 订单参数
	 * @return array|WP_Error
	 */
	public function create_order($params)
	{
		return $this->request('/api/v1/pay/sdk/order/add', $params);
	}

	/**
	 * 查询订单详情
	 *
	 * @param string $trade_id   交易ID
	 * @param string $mch_order_id 商户订单号
	 * @return array|WP_Error
	 */
	public function get_order_detail($trade_id = '', $mch_order_id = '')
	{
		$params = [];
		if ($trade_id) {
			$params['trade_id'] = $trade_id;
		}
		if ($mch_order_id) {
			$params['mch_order_id'] = $mch_order_id;
		}
		return $this->request('/api/v1/pay/sdk/order/detail', $params);
	}

	/**
	 * 激活插件
	 *
	 * @return array|WP_Error
	 */
	public function activate_plugin()
	{
		return $this->request('/api/v1/pay/sdk/plugin/activate', [
			'plugin_type' => 'woocommerce',
		]);
	}

	/**
	 * 发送 API 请求
	 *
	 * @param string $endpoint API 端点
	 * @param array  $data     请求数据
	 * @return array|WP_Error
	 */
	private function request($endpoint, $data = [])
	{
		$url = rtrim($this->api_url, '/') . $endpoint;

		$args = [
			'method' => 'POST',
			'timeout' => $this->timeout,
			'headers' => [
				'Content-Type' => 'application/json',
				'X-API-Key' => $this->api_key,
				'User-Agent' => 'WordPress-PonponPay/' . PONPONPAY_VERSION,
			],
			'body' => wp_json_encode($data),
		];

		// 调试日志 - 写入文件
		$log = date('Y-m-d H:i:s') . " Request URL: {$url}\n";
		$log .= "API Key: " . substr($this->api_key, 0, 8) . "...\n";
		$log .= "Request Body: " . wp_json_encode($data) . "\n";
		file_put_contents('/tmp/ponponpay-debug.log', $log, FILE_APPEND);

		$response = wp_remote_post($url, $args);

		if (is_wp_error($response)) {
			file_put_contents('/tmp/ponponpay-debug.log', "WP Error: " . $response->get_error_message() . "\n\n", FILE_APPEND);
			return $response;
		}

		$http_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		file_put_contents('/tmp/ponponpay-debug.log', "HTTP Code: {$http_code}\nResponse: {$body}\n\n", FILE_APPEND);

		$decoded = json_decode($body, true);

		if ($http_code !== 200) {
			$error_msg = $decoded['message'] ?? 'HTTP Error ' . $http_code;
			return new WP_Error('ponponpay_api_error', $error_msg, ['status' => $http_code]);
		}

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new WP_Error('ponponpay_json_error', 'Invalid JSON response');
		}

		return $decoded;
	}
}
