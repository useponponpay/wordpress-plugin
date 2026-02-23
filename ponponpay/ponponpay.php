<?php
/**
 * Plugin Name: PonponPay - Crypto Payment Gateway
 * Plugin URI: https://ponponpay.com
 * Description: Accept cryptocurrency payments (USDT, USDC, etc.) on any WordPress site via PonponPay. Use shortcodes to embed payment buttons, or integrate with WooCommerce.
 * Version: 1.0.0
 * Author: PonponPay Engineering Team
 * Author URI: https://ponponpay.com
 * License: GPL-2.0+
 * Text Domain: ponponpay
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.7
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
}

/**
 * 插件初始化
 */
function ponponpay_init()
{
	// 加载翻译
	load_plugin_textdomain('ponponpay', false, dirname(plugin_basename(__FILE__)) . '/languages');

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
		esc_html__('Settings', 'ponponpay') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ponponpay_plugin_links');

/**
 * 加载前端资源
 */
function ponponpay_enqueue_scripts()
{
	// 仅在包含短代码的页面或结账页加载
	global $post;
	$load = false;

	if ($post && has_shortcode($post->post_content, 'ponponpay_button')) {
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
	$table = $wpdb->prefix . 'ponponpay_payments';
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
