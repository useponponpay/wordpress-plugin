<?php
/**
 * Plugin Name: PonponPay - Crypto Payment Gateway
 * Plugin URI: https://ponponpay.com/docs
 * Description: Accept cryptocurrency payments (USDT, USDC, etc.) on any WordPress site via PonponPay. Use shortcodes to embed payment buttons, or integrate with WooCommerce.
 * Version: 1.0.0
 * Author: PonponPay Engineering Team
 * Author URI: https://ponponpay.com
 * License: GPL-2.0+
 * Text Domain: ponponpay-crypto-payment-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

// 插件常量
if (!defined('PONPONPAY_VERSION')) {
	define('PONPONPAY_VERSION', '1.0.0');
}
if (!defined('PONPONPAY_PLUGIN_DIR')) {
	define('PONPONPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('PONPONPAY_PLUGIN_URL')) {
	define('PONPONPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('PONPONPAY_API_URL')) {
	// 默认使用生产 API，可通过常量或 filter 覆盖，这里为了测试阶段改为本地
	define('PONPONPAY_API_URL', apply_filters('ponponpay_api_url', 'https://api.ponponpay.com'));
	// define('PONPONPAY_API_URL', apply_filters('ponponpay_api_url', 'http://localhost:11050'));
}

/**
 * 插件初始化
 */
function ponponpay_init()
{
	// 加载核心类（不依赖 WooCommerce）
	require_once PONPONPAY_PLUGIN_DIR . 'includes/class-ponponpay-api.php';
	require_once PONPONPAY_PLUGIN_DIR . 'includes/class-ponponpay-settings.php';
	require_once PONPONPAY_PLUGIN_DIR . 'includes/class-ponponpay-shortcode.php';
	require_once PONPONPAY_PLUGIN_DIR . 'includes/class-ponponpay-rest-callback.php';

	// 初始化核心组件
	new PonponPay_Settings();
	new PonponPay_Shortcode();
	new PonponPay_REST_Callback();

	// WooCommerce 集成（可选）
	if (class_exists('WooCommerce')) {
		require_once PONPONPAY_PLUGIN_DIR . 'includes/class-ponponpay-gateway.php';
		require_once PONPONPAY_PLUGIN_DIR . 'includes/class-ponponpay-callback.php';
		new PonponPay_Callback();
	}
}
add_action('plugins_loaded', 'ponponpay_init');

/**
 * 生成收银台访问令牌。
 *
 * @param string $order_id 订单号
 * @param string $secret   附加密钥
 * @return string
 */
function ponponpay_create_checkout_token($order_id, $secret = '')
{
	$payload = (string)$order_id . '|' . (string)$secret;
	return hash_hmac('sha256', $payload, wp_salt('ponponpay_checkout'));
}

/**
 * 构造收银台 URL。
 *
 * @param string $order_id 订单号
 * @param string $secret   附加密钥
 * @return string
 */
function ponponpay_build_checkout_url($order_id, $secret = '')
{
	return add_query_arg([
		'ponponpay_checkout' => $order_id,
		'ponponpay_token' => ponponpay_create_checkout_token($order_id, $secret),
	], home_url('/'));
}

/**
 * 获取 PonponPay 支付记录表名。
 *
 * @return string
 */
function ponponpay_payments_table()
{
	global $wpdb;

	return $wpdb->prefix . 'ponponpay_payments';
}

/**
 * 获取支付记录缓存键。
 *
 * @param string $order_id 订单号
 * @return string
 */
function ponponpay_payment_cache_key($order_id)
{
	return 'ponponpay_payment_' . md5((string)$order_id);
}

/**
 * 查询 PonponPay 支付记录。
 *
 * @param string $order_id 订单号
 * @return object|null
 */
function ponponpay_get_payment($order_id)
{
	global $wpdb;

	$order_id = sanitize_text_field((string)$order_id);
	if ($order_id === '') {
		return null;
	}

	$cache_key = ponponpay_payment_cache_key($order_id);
	$payment = wp_cache_get($cache_key, 'ponponpay_payments');
	if (false !== $payment) {
		return $payment;
	}

	$table = esc_sql(ponponpay_payments_table());
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter -- 自定义插件表名固定且来自 $wpdb->prefix，字段值仍通过 prepare() 绑定。
	$payment = $wpdb->get_row(
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- WordPress 6.1 及以下不支持 %i，占位值仅用于 order_id，表名已通过 esc_sql() 处理。
		$wpdb->prepare(
			'SELECT * FROM ' . $table . ' WHERE order_id = %s',
			$order_id
		)
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	);

	wp_cache_set($cache_key, $payment, 'ponponpay_payments', 5 * MINUTE_IN_SECONDS);

	return $payment;
}

/**
 * 清理支付记录缓存。
 *
 * @param string $order_id 订单号
 */
function ponponpay_delete_payment_cache($order_id)
{
	wp_cache_delete(ponponpay_payment_cache_key($order_id), 'ponponpay_payments');
}

/**
 * 新增 PonponPay 支付记录。
 *
 * @param array $data 支付记录数据
 * @return int|false
 */
function ponponpay_insert_payment($data)
{
	global $wpdb;

	$table = ponponpay_payments_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- 写入自定义插件表，WordPress 没有对应的高级 API。
	$result = $wpdb->insert($table, $data);

	if (!empty($data['order_id'])) {
		ponponpay_delete_payment_cache($data['order_id']);
	}

	return $result;
}

/**
 * 更新 PonponPay 支付记录。
 *
 * @param string $order_id 订单号
 * @param array  $data     更新数据
 * @return int|false
 */
function ponponpay_update_payment($order_id, $data)
{
	global $wpdb;

	$order_id = sanitize_text_field((string)$order_id);
	if ($order_id === '') {
		return false;
	}

	$table = ponponpay_payments_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- 更新自定义插件表，且会立即清理对象缓存。
	$result = $wpdb->update($table, $data, ['order_id' => $order_id]);

	ponponpay_delete_payment_cache($order_id);

	return $result;
}

/**
 * 注册 WooCommerce 支付网关（仅在 WooCommerce 可用时）
 */
function ponponpay_add_wc_gateway($gateways)
{
	if (class_exists('PonponPay_Gateway')) {
		$gateways[] = 'PonponPay_Gateway';
	}
	return $gateways;
}
add_filter('woocommerce_payment_gateways', 'ponponpay_add_wc_gateway');

/**
 * 在插件列表中添加设置链接
 */
function ponponpay_plugin_links($links)
{
	$settings_link = '<a href="' . admin_url('options-general.php?page=ponponpay') . '">' .
		esc_html__('Settings', 'ponponpay-crypto-payment-gateway') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ponponpay_plugin_links');

/**
 * 检查文本中是否包含 PonponPay 短代码。
 *
 * @param string $content 文本内容
 * @return bool
 */
function ponponpay_has_shortcode($content)
{
	return is_string($content) && has_shortcode($content, 'ponponpay_button');
}

/**
 * 检查当前查询结果中是否包含 PonponPay 短代码。
 *
 * @return bool
 */
function ponponpay_current_query_has_shortcode()
{
	global $wp_query;

	if (empty($wp_query) || empty($wp_query->posts) || !is_array($wp_query->posts)) {
		return false;
	}

	foreach ($wp_query->posts as $ponponpay_post) {
		if (!($ponponpay_post instanceof WP_Post)) {
			continue;
		}

		if (ponponpay_has_shortcode($ponponpay_post->post_content) || ponponpay_has_shortcode($ponponpay_post->post_excerpt)) {
			return true;
		}
	}

	return false;
}

/**
 * 在摘要中执行 PonponPay 短代码。
 *
 * @param string $excerpt 摘要内容
 * @return string
 */
function ponponpay_render_shortcode_in_excerpt($excerpt)
{
	if (!ponponpay_has_shortcode($excerpt)) {
		return $excerpt;
	}

	return do_shortcode($excerpt);
}
add_filter('get_the_excerpt', 'ponponpay_render_shortcode_in_excerpt', 12);

/**
 * 加载前端资源
 */
function ponponpay_enqueue_scripts()
{
	// 仅在包含短代码的页面或结账页加载
	global $post;
	$load = false;

	if ($post && (ponponpay_has_shortcode($post->post_content) || ponponpay_has_shortcode($post->post_excerpt))) {
		$load = true;
	}

	if (!$load && ponponpay_current_query_has_shortcode()) {
		$load = true;
	}

	if (function_exists('is_checkout') && (is_checkout() || is_wc_endpoint_url('order-pay'))) {
		$load = true;
	}

	if ($load) {
		wp_enqueue_style(
			'ponponpay-style',
			PONPONPAY_PLUGIN_URL . 'assets/css/ponponpay.css',
			[],
			PONPONPAY_VERSION
		);
		wp_enqueue_script(
			'ponponpay-script',
			PONPONPAY_PLUGIN_URL . 'assets/js/ponponpay.js',
			['jquery'],
			PONPONPAY_VERSION,
			true
		);
		wp_localize_script('ponponpay-script', 'ponponpayAjax', [
			'restUrl' => esc_url_raw(rest_url('ponponpay/v1/')),
			'nonce' => wp_create_nonce('wp_rest'),
			'i18n' => [
				'failedInitPayment' => __('Failed to initialize payment', 'ponponpay-crypto-payment-gateway'),
				'networkErrorTryAgain' => __('Network error. Please try again.', 'ponponpay-crypto-payment-gateway'),
			],
		]);
	}
}
add_action('wp_enqueue_scripts', 'ponponpay_enqueue_scripts');

/**
 * 插件激活 - 创建支付记录表
 */
function ponponpay_activate()
{
	global $wpdb;
	$table = ponponpay_payments_table();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_id varchar(64) NOT NULL DEFAULT '' COMMENT '商户订单号',
        trade_id varchar(64) NOT NULL DEFAULT '' COMMENT 'PonponPay 交易 ID',
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

	update_option('ponponpay_db_version', '1.0.0');
}
register_activation_hook(__FILE__, 'ponponpay_activate');

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
	$vars[] = 'ponponpay_checkout';
	$vars[] = 'ponponpay_token';
	return $vars;
});

/**
 * 模板重定向：当检测到 ponponpay_checkout 时加载独立收银台
 */
add_action('template_redirect', function () {
	$order_id = get_query_var('ponponpay_checkout');
	if (!empty($order_id)) {
		$template_path = PONPONPAY_PLUGIN_DIR . 'templates/payment-checkout.php';
		if (file_exists($template_path)) {
			include($template_path);
			exit;
		}
	}
});
