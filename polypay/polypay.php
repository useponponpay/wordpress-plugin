<?php
/**
 * Plugin Name: PolyPay - Crypto Payment Gateway
 * Plugin URI: https://polypay.ai/docs
 * Description: Accept cryptocurrency payments (USDT, USDC, etc.) on any WordPress site via PolyPay. Use shortcodes to embed payment buttons, or integrate with WooCommerce.
 * Version: 1.0.0
 * Author: PolyPay Engineering Team
 * Author URI: https://polypay.ai
 * License: GPL-2.0+
 * Text Domain: polypay-crypto-payment-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

// 插件常量
if (!defined('POLYPAY_VERSION')) {
	define('POLYPAY_VERSION', '1.0.0');
}
if (!defined('POLYPAY_PLUGIN_DIR')) {
	define('POLYPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('POLYPAY_PLUGIN_URL')) {
	define('POLYPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('POLYPAY_API_URL')) {
	// 默认使用生产 API，可通过常量或 filter 覆盖，这里为了测试阶段改为本地
	define('POLYPAY_API_URL', apply_filters('polypay_api_url', 'https://api.polypay.ai'));
	// define('POLYPAY_API_URL', apply_filters('polypay_api_url', 'http://localhost:11050'));
}

/**
 * 插件初始化
 */
function polypay_init()
{
	// 加载核心类（不依赖 WooCommerce）
	require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-api.php';
	require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-settings.php';
	require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-shortcode.php';
	require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-rest-callback.php';

	// 初始化核心组件
	new PolyPay_Settings();
	new PolyPay_Shortcode();
	new PolyPay_REST_Callback();

	// WooCommerce 集成（可选）
	if (class_exists('WooCommerce')) {
		require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-gateway.php';
		require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-callback.php';
		new PolyPay_Callback();
	}
}
add_action('plugins_loaded', 'polypay_init');

/**
 * 生成收银台访问令牌。
 *
 * @param string $order_id 订单号
 * @param string $secret   附加密钥
 * @return string
 */
function polypay_create_checkout_token($order_id, $secret = '')
{
	$payload = (string)$order_id . '|' . (string)$secret;
	return hash_hmac('sha256', $payload, wp_salt('polypay_checkout'));
}

/**
 * 构造收银台 URL。
 *
 * @param string $order_id 订单号
 * @param string $secret   附加密钥
 * @return string
 */
function polypay_build_checkout_url($order_id, $secret = '')
{
	return add_query_arg([
		'polypay_checkout' => $order_id,
		'polypay_token' => polypay_create_checkout_token($order_id, $secret),
	], home_url('/'));
}

/**
 * 获取 PolyPay 支付记录表名。
 *
 * @return string
 */
function polypay_payments_table()
{
	global $wpdb;

	return $wpdb->prefix . 'polypay_payments';
}

/**
 * 获取支付记录缓存键。
 *
 * @param string $order_id 订单号
 * @return string
 */
function polypay_payment_cache_key($order_id)
{
	return 'polypay_payment_' . md5((string)$order_id);
}

/**
 * 查询 PolyPay 支付记录。
 *
 * @param string $order_id 订单号
 * @return object|null
 */
function polypay_get_payment($order_id)
{
	global $wpdb;

	$order_id = sanitize_text_field((string)$order_id);
	if ($order_id === '') {
		return null;
	}

	$cache_key = polypay_payment_cache_key($order_id);
	$payment = wp_cache_get($cache_key, 'polypay_payments');
	if (false !== $payment) {
		return $payment;
	}

	$table = esc_sql(polypay_payments_table());
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter -- 自定义插件表名固定且来自 $wpdb->prefix，字段值仍通过 prepare() 绑定。
	$payment = $wpdb->get_row(
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- WordPress 6.1 及以下不支持 %i，占位值仅用于 order_id，表名已通过 esc_sql() 处理。
		$wpdb->prepare(
			'SELECT * FROM ' . $table . ' WHERE order_id = %s',
			$order_id
		)
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	);

	wp_cache_set($cache_key, $payment, 'polypay_payments', 5 * MINUTE_IN_SECONDS);

	return $payment;
}

/**
 * 清理支付记录缓存。
 *
 * @param string $order_id 订单号
 */
function polypay_delete_payment_cache($order_id)
{
	wp_cache_delete(polypay_payment_cache_key($order_id), 'polypay_payments');
}

/**
 * 新增 PolyPay 支付记录。
 *
 * @param array $data 支付记录数据
 * @return int|false
 */
function polypay_insert_payment($data)
{
	global $wpdb;

	$table = polypay_payments_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- 写入自定义插件表，WordPress 没有对应的高级 API。
	$result = $wpdb->insert($table, $data);

	if (!empty($data['order_id'])) {
		polypay_delete_payment_cache($data['order_id']);
	}

	return $result;
}

/**
 * 更新 PolyPay 支付记录。
 *
 * @param string $order_id 订单号
 * @param array  $data     更新数据
 * @return int|false
 */
function polypay_update_payment($order_id, $data)
{
	global $wpdb;

	$order_id = sanitize_text_field((string)$order_id);
	if ($order_id === '') {
		return false;
	}

	$table = polypay_payments_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- 更新自定义插件表，且会立即清理对象缓存。
	$result = $wpdb->update($table, $data, ['order_id' => $order_id]);

	polypay_delete_payment_cache($order_id);

	return $result;
}

/**
 * 注册 WooCommerce 支付网关（仅在 WooCommerce 可用时）
 */
function polypay_add_wc_gateway($gateways)
{
	if (class_exists('PolyPay_Gateway')) {
		$gateways[] = 'PolyPay_Gateway';
	}
	return $gateways;
}
add_filter('woocommerce_payment_gateways', 'polypay_add_wc_gateway');

/**
 * 在插件列表中添加设置链接
 */
function polypay_plugin_links($links)
{
	$settings_link = '<a href="' . admin_url('options-general.php?page=polypay') . '">' .
		esc_html__('Settings', 'polypay-crypto-payment-gateway') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'polypay_plugin_links');

/**
 * 检查文本中是否包含 PolyPay 短代码。
 *
 * @param string $content 文本内容
 * @return bool
 */
function polypay_has_shortcode($content)
{
	return is_string($content) && has_shortcode($content, 'polypay_button');
}

/**
 * 检查当前查询结果中是否包含 PolyPay 短代码。
 *
 * @return bool
 */
function polypay_current_query_has_shortcode()
{
	global $wp_query;

	if (empty($wp_query) || empty($wp_query->posts) || !is_array($wp_query->posts)) {
		return false;
	}

	foreach ($wp_query->posts as $polypay_post) {
		if (!($polypay_post instanceof WP_Post)) {
			continue;
		}

		if (polypay_has_shortcode($polypay_post->post_content) || polypay_has_shortcode($polypay_post->post_excerpt)) {
			return true;
		}
	}

	return false;
}

/**
 * 在摘要中执行 PolyPay 短代码。
 *
 * @param string $excerpt 摘要内容
 * @return string
 */
function polypay_render_shortcode_in_excerpt($excerpt)
{
	if (!polypay_has_shortcode($excerpt)) {
		return $excerpt;
	}

	return do_shortcode($excerpt);
}
add_filter('get_the_excerpt', 'polypay_render_shortcode_in_excerpt', 12);

/**
 * 加载前端资源
 */
function polypay_enqueue_scripts()
{
	// 仅在包含短代码的页面或结账页加载
	global $post;
	$load = false;

	if ($post && (polypay_has_shortcode($post->post_content) || polypay_has_shortcode($post->post_excerpt))) {
		$load = true;
	}

	if (!$load && polypay_current_query_has_shortcode()) {
		$load = true;
	}

	if (function_exists('is_checkout') && (is_checkout() || is_wc_endpoint_url('order-pay'))) {
		$load = true;
	}

	if ($load) {
		wp_enqueue_style(
			'polypay-style',
			POLYPAY_PLUGIN_URL . 'assets/css/polypay.css',
			[],
			POLYPAY_VERSION
		);
		wp_enqueue_script(
			'polypay-script',
			POLYPAY_PLUGIN_URL . 'assets/js/polypay.js',
			['jquery'],
			POLYPAY_VERSION,
			true
		);
		wp_localize_script('polypay-script', 'polypayAjax', [
			'restUrl' => esc_url_raw(rest_url('polypay/v1/')),
			'nonce' => wp_create_nonce('wp_rest'),
			'i18n' => [
				'failedInitPayment' => __('Failed to initialize payment', 'polypay-crypto-payment-gateway'),
				'networkErrorTryAgain' => __('Network error. Please try again.', 'polypay-crypto-payment-gateway'),
			],
		]);
	}
}
add_action('wp_enqueue_scripts', 'polypay_enqueue_scripts');

/**
 * 插件激活 - 创建支付记录表
 */
function polypay_activate()
{
	global $wpdb;
	$table = polypay_payments_table();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_id varchar(64) NOT NULL DEFAULT '' COMMENT '商户订单号',
        trade_id varchar(64) NOT NULL DEFAULT '' COMMENT 'PolyPay 交易 ID',
        amount decimal(16,2) NOT NULL DEFAULT 0 COMMENT '金额',
        fiat_currency varchar(16) NOT NULL DEFAULT 'USD' COMMENT '法币币种',
        crypto_currency varchar(16) NOT NULL DEFAULT '' COMMENT '加密币种',
        network varchar(32) NOT NULL DEFAULT '' COMMENT '网络',
        status tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态: 0-待支付 1-已支付 2-过期 3-取消',
        tx_hash varchar(128) NOT NULL DEFAULT '' COMMENT '交易哈希',
        description varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
        payment_url varchar(512) NOT NULL DEFAULT '' COMMENT '支付链接',
        redirect_url varchar(512) NOT NULL DEFAULT '' COMMENT '支付后跳转',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_order_id (order_id),
        KEY idx_trade_id (trade_id),
        KEY idx_status (status)
    ) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta($sql);

	update_option('polypay_db_version', '1.0.0');
}
register_activation_hook(__FILE__, 'polypay_activate');

/**
 * 声明 WooCommerce HPOS 兼容（可选）
 */
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

/**
 * 注册自定义 Query Var
 */
add_filter('query_vars', function ($vars) {
	$vars[] = 'polypay_checkout';
	$vars[] = 'polypay_token';
	return $vars;
});

/**
 * 模板重定向：当检测到 polypay_checkout 时加载独立收银台
 */
add_action('template_redirect', function () {
	$order_id = get_query_var('polypay_checkout');
	if (!empty($order_id)) {
		$template_path = POLYPAY_PLUGIN_DIR . 'templates/payment-checkout.php';
		if (file_exists($template_path)) {
			include($template_path);
			exit;
		}
	}
});
