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
				$data = $_POST;
			}

			// 验证必要字段
			if (empty($data['order_no']) || empty($data['status'])) {
				$logger->error('Callback missing required fields: order_no or status', ['source' => 'ponponpay']);
				wp_send_json_error('Missing required fields', 400);
				return;
			}

			// 验证 API Key
			$received_api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
			$gateway = $this->get_gateway();

			if (!$gateway) {
				$logger->error('Gateway not configured', ['source' => 'ponponpay']);
				wp_send_json_error('Gateway not configured', 500);
				return;
			}

			$expected_api_key = $gateway->get_option('api_key');

			if (empty($received_api_key) || $received_api_key !== $expected_api_key) {
				$logger->error('API Key validation failed', ['source' => 'ponponpay']);
				status_header(401);
				echo 'Unauthorized';
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
					$order->update_status('cancelled', __('PonponPay: Payment expired.', 'ponponpay-woocommerce'));
					$logger->info('Payment expired for order: ' . $order_id, ['source' => 'ponponpay']);
					break;

				case 4:  // 取消支付
					$order->update_status('cancelled', __('PonponPay: Payment cancelled.', 'ponponpay-woocommerce'));
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
			__('PonponPay payment completed. Transaction: %1$s, Currency: %2$s, Network: %3$s', 'ponponpay-woocommerce'),
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
}
