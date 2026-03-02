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

	/** @var array 最近一次请求调试上下文 */
	private $last_debug_context = [];

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
	 * 获取日志文件路径
	 *
	 * @return string
	 */
	public static function get_debug_log_file()
	{
		return '/tmp/ponponpay-debug.log';
	}

	/**
	 * 获取最近一次请求调试上下文
	 *
	 * @return array
	 */
	public function get_last_debug_context()
	{
		return $this->last_debug_context;
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
		$request_id = wp_generate_uuid4();

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

		$this->last_debug_context = [
			'request_id' => $request_id,
			'endpoint' => $endpoint,
			'url' => $url,
			'timeout' => $this->timeout,
			'request_body' => $data,
			'api_key_prefix' => substr((string)$this->api_key, 0, 12),
		];
		$this->write_debug_log("[$request_id] Request URL: {$url}");
		$this->write_debug_log("[$request_id] API Key Prefix: " . substr($this->api_key, 0, 12) . '...');
		$this->write_debug_log("[$request_id] Request Body: " . wp_json_encode($data));

		$response = wp_remote_post($url, $args);

		if (is_wp_error($response)) {
			$this->last_debug_context['wp_error_code'] = $response->get_error_code();
			$this->last_debug_context['wp_error_message'] = $response->get_error_message();
			$this->last_debug_context['wp_error_data'] = $response->get_error_data();
			$this->write_debug_log("[$request_id] WP Error Code: " . $response->get_error_code());
			$this->write_debug_log("[$request_id] WP Error Message: " . $response->get_error_message());
			$this->write_debug_log("[$request_id] WP Error Data: " . wp_json_encode($response->get_error_data()));
			$this->write_debug_log('');
			return $response;
		}

		$http_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$response_message = wp_remote_retrieve_response_message($response);
		$response_headers = wp_remote_retrieve_headers($response);

		$this->last_debug_context['http_code'] = $http_code;
		$this->last_debug_context['http_message'] = $response_message;
		$this->last_debug_context['response_headers'] = $response_headers;
		$this->last_debug_context['response_body'] = $body;
		$this->write_debug_log("[$request_id] HTTP Code: {$http_code}");
		$this->write_debug_log("[$request_id] HTTP Message: {$response_message}");
		$this->write_debug_log("[$request_id] Response Headers: " . wp_json_encode($response_headers));
		$this->write_debug_log("[$request_id] Response Body: {$body}");

		$decoded = json_decode($body, true);
		$this->last_debug_context['decoded'] = $decoded;
		$this->last_debug_context['json_error'] = json_last_error();
		$this->last_debug_context['json_error_message'] = json_last_error_msg();

		if ($http_code !== 200) {
			$error_msg = $decoded['message'] ?? 'HTTP Error ' . $http_code;
			$this->write_debug_log("[$request_id] API Error: {$error_msg}");
			$this->write_debug_log('');
			return new WP_Error('ponponpay_api_error', $error_msg, ['status' => $http_code]);
		}

		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->write_debug_log("[$request_id] JSON Decode Error: " . json_last_error_msg());
			$this->write_debug_log('');
			return new WP_Error('ponponpay_json_error', 'Invalid JSON response');
		}

		$this->write_debug_log("[$request_id] API Response Code: " . ($decoded['code'] ?? 'null'));
		$this->write_debug_log("[$request_id] API Response Message: " . ($decoded['message'] ?? ''));
		$this->write_debug_log('');
		return $decoded;
	}

	/**
	 * 写调试日志
	 *
	 * @param string $message 日志内容
	 * @return void
	 */
	private function write_debug_log($message)
	{
		$line = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
		file_put_contents(self::get_debug_log_file(), $line, FILE_APPEND);
	}
}
