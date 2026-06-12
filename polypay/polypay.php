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

// Plugin constants
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
	// Defaults to the production API; can be overridden via constant or filter. Switched to local for the testing phase.
	define('POLYPAY_API_URL', apply_filters('polypay_api_url', 'https://api.polypay.ai'));
	// define('POLYPAY_API_URL', apply_filters('polypay_api_url', 'http://localhost:11050'));
}

/**
 * Plugin initialization
 */
function polypay_init()
{
	// Load core classes (not dependent on WooCommerce)
	require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-api.php';
	require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-settings.php';
	require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-shortcode.php';
	require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-rest-callback.php';

	// Initialize core components
	new PolyPay_Settings();
	new PolyPay_Shortcode();
	new PolyPay_REST_Callback();

	// WooCommerce integration (optional)
	if (class_exists('WooCommerce')) {
		require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-gateway.php';
		require_once POLYPAY_PLUGIN_DIR . 'includes/class-polypay-callback.php';
		new PolyPay_Callback();
	}
}
add_action('plugins_loaded', 'polypay_init');

/**
 * Generate the checkout access token.
 *
 * @param string $order_id Order number
 * @param string $secret   Additional secret
 * @return string
 */
function polypay_create_checkout_token($order_id, $secret = '')
{
	$payload = (string)$order_id . '|' . (string)$secret;
	return hash_hmac('sha256', $payload, wp_salt('polypay_checkout'));
}

/**
 * Build the checkout URL.
 *
 * @param string $order_id Order number
 * @param string $secret   Additional secret
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
 * Generate the shortened merchant ID identifier.
 *
 * Strips the MCH prefix and takes the last 6 characters; when no merchant ID
 * is configured, falls back to a stable 6-character identifier derived from
 * the API Key. Reads the WooCommerce gateway settings first, then the
 * standalone settings page configuration.
 *
 * @return string
 */
function polypay_merchant_short_id()
{
	$wc_options = get_option('woocommerce_polypay_settings', []);
	$mch_id = trim((string)($wc_options['mch_id'] ?? ''));
	if ($mch_id === '' && class_exists('PolyPay_Settings')) {
		$mch_id = trim((string)PolyPay_Settings::get_mch_id());
	}

	$short = substr((string)preg_replace('/^MCH/', '', strtoupper($mch_id)), -6);
	if ($short === '' || $short === false) {
		$api_key = trim((string)($wc_options['api_key'] ?? ''));
		if ($api_key === '' && class_exists('PolyPay_Settings')) {
			$api_key = (string)PolyPay_Settings::get_api_key();
		}
		$short = strtoupper(substr(md5($api_key), 0, 6));
	}

	return $short;
}

/**
 * Generate the merchant order number for a WooCommerce order.
 *
 * Format: B{shortened merchant ID}_{order_id}, e.g. B189696_55.
 * The first letter is the order source identifier: P = PolyPay platform,
 * other plugins follow in sequence: A = WHMCS, B = WordPress (WooCommerce),
 * C = Shopify, and so on.
 *
 * @param int|string $wc_order_id WooCommerce order ID
 * @return string
 */
function polypay_build_wc_order_no($wc_order_id)
{
	return 'B' . polypay_merchant_short_id() . '_' . $wc_order_id;
}

/**
 * Parse the WooCommerce order ID from a merchant order number.
 *
 * Supports the new format B{shortened merchant ID}_{order_id}, and is
 * backward compatible with the legacy formats WC_{order_id} and
 * WC_{order_id}_{hash}. Returns 0 if parsing fails.
 *
 * @param string $order_no Merchant order number
 * @return int
 */
function polypay_parse_wc_order_id($order_no)
{
	if (preg_match('/^B[A-Za-z0-9]+_(\d+)$/', (string)$order_no, $matches)) {
		return (int)$matches[1];
	}
	if (preg_match('/^WC_(\d+)(?:_[a-zA-Z0-9]+)?$/', (string)$order_no, $matches)) {
		return (int)$matches[1];
	}

	return 0;
}

/**
 * Get the PolyPay payment record table name.
 *
 * @return string
 */
function polypay_payments_table()
{
	global $wpdb;

	return $wpdb->prefix . 'polypay_payments';
}

/**
 * Get the payment record cache key.
 *
 * @param string $order_id Order number
 * @return string
 */
function polypay_payment_cache_key($order_id)
{
	return 'polypay_payment_' . md5((string)$order_id);
}

/**
 * Query a PolyPay payment record.
 *
 * @param string $order_id Order number
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
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The custom plugin table name is fixed and derived from $wpdb->prefix; field values are still bound via prepare().
	$payment = $wpdb->get_row(
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- WordPress 6.1 and below do not support %i; the placeholder is only used for order_id, and the table name has been escaped via esc_sql().
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
 * Clear the payment record cache.
 *
 * @param string $order_id Order number
 */
function polypay_delete_payment_cache($order_id)
{
	wp_cache_delete(polypay_payment_cache_key($order_id), 'polypay_payments');
}

/**
 * Insert a new PolyPay payment record.
 *
 * @param array $data Payment record data
 * @return int|false
 */
function polypay_insert_payment($data)
{
	global $wpdb;

	$table = polypay_payments_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes to a custom plugin table; WordPress has no corresponding high-level API.
	$result = $wpdb->insert($table, $data);

	if (!empty($data['order_id'])) {
		polypay_delete_payment_cache($data['order_id']);
	}

	return $result;
}

/**
 * Update a PolyPay payment record.
 *
 * @param string $order_id Order number
 * @param array  $data     Data to update
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
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Updates a custom plugin table, and the object cache is cleared immediately afterwards.
	$result = $wpdb->update($table, $data, ['order_id' => $order_id]);

	polypay_delete_payment_cache($order_id);

	return $result;
}

/**
 * Register the WooCommerce payment gateway (only when WooCommerce is available)
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
 * Add a settings link to the plugin list
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
 * Check whether the text contains a PolyPay shortcode.
 *
 * @param string $content Text content
 * @return bool
 */
function polypay_has_shortcode($content)
{
	return is_string($content) && has_shortcode($content, 'polypay_button');
}

/**
 * Check whether the current query results contain a PolyPay shortcode.
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
 * Execute PolyPay shortcodes within the excerpt.
 *
 * @param string $excerpt Excerpt content
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
 * Load front-end assets
 */
function polypay_enqueue_scripts()
{
	// Only load on pages containing the shortcode or on the checkout page
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
 * Plugin activation - create the payment record table
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
 * Declare WooCommerce HPOS compatibility (optional)
 */
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

/**
 * Register custom query vars
 */
add_filter('query_vars', function ($vars) {
	$vars[] = 'polypay_checkout';
	$vars[] = 'polypay_token';
	return $vars;
});

/**
 * Template redirect: load the standalone checkout when polypay_checkout is detected
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
