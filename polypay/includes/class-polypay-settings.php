<?php
/**
 * PolyPay standalone settings page
 *
 * Provides an admin configuration page via the WordPress Settings API, without depending on WooCommerce
 *
 * @package PolyPay
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PolyPay_Settings
{
	/** @var string Option group */
	private $option_group = 'polypay_settings';

	/** @var string Option name */
	private $option_name = 'polypay_options';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'register_settings']);
	}

	/**
	 * Add the settings page to the WordPress admin menu
	 */
	public function add_settings_page()
	{
		add_options_page(
			__('PolyPay Settings', 'polypay-crypto-payment-gateway'),
			__('PolyPay', 'polypay-crypto-payment-gateway'),
			'manage_options',
			'polypay',
			[$this, 'render_settings_page']
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings()
	{
		register_setting($this->option_group, $this->option_name, [
			'sanitize_callback' => [$this, 'sanitize_options'],
		]);

		// API configuration section
		add_settings_section(
			'polypay_api_section',
			__('API Configuration', 'polypay-crypto-payment-gateway'),
			function () {
				echo '<p>' . esc_html__('Configure your PolyPay API credentials. Get your API Key from', 'polypay-crypto-payment-gateway')
					. ' <a href="https://polypay.ai" target="_blank">polypay.ai</a></p>';
			},
			'polypay'
		);

		add_settings_field(
			'api_key',
			__('API Key', 'polypay-crypto-payment-gateway'),
			[$this, 'render_api_key_field'],
			'polypay',
			'polypay_api_section'
		);

		add_settings_field(
			'mch_id',
			__('Merchant ID', 'polypay-crypto-payment-gateway'),
			[$this, 'render_mch_id_field'],
			'polypay',
			'polypay_api_section'
		);

		// Shortcode usage instructions section
		add_settings_section(
			'polypay_shortcode_section',
			__('Shortcode Usage', 'polypay-crypto-payment-gateway'),
			[$this, 'render_shortcode_help'],
			'polypay'
		);
	}

	/**
	 * Render the API Key input field
	 */
	public function render_api_key_field()
	{
		$options = get_option($this->option_name, []);
		$api_key = $options['api_key'] ?? '';
		?>
		<input type="text" name="<?php echo esc_attr($this->option_name); ?>[api_key]" value="<?php echo esc_attr($api_key); ?>"
			class="regular-text" placeholder="<?php esc_attr_e('Enter your PolyPay API Key', 'polypay-crypto-payment-gateway'); ?>" />
		<p class="description">
			<?php
			printf(
				/* translators: %s: callback URL */
				esc_html__('Callback URL: %s', 'polypay-crypto-payment-gateway'),
				'<code>' . esc_html(rest_url('polypay/v1/callback')) . '</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the merchant ID input field
	 */
	public function render_mch_id_field()
	{
		$options = get_option($this->option_name, []);
		$mch_id = $options['mch_id'] ?? '';
		?>
		<input type="text" name="<?php echo esc_attr($this->option_name); ?>[mch_id]" value="<?php echo esc_attr($mch_id); ?>"
			class="regular-text" placeholder="MCH17790986189696" />
		<p class="description">
			<?php esc_html_e('Merchant ID from the PolyPay console. Used as a short prefix of the merchant order number; falls back to an API Key derived identifier when empty.', 'polypay-crypto-payment-gateway'); ?>
		</p>
		<?php
	}

	/**
	 * Render the shortcode usage instructions
	 */
	public function render_shortcode_help()
	{
		?>
		<div style="background:#f9f9f9;border:1px solid #ddd;border-radius:6px;padding:16px;max-width:700px;">
			<h4 style="margin-top:0;">
				<?php esc_html_e('Basic Usage', 'polypay-crypto-payment-gateway'); ?>
			</h4>
			<code>[polypay_button amount="100"]</code>

			<h4>
				<?php esc_html_e('Full Parameters', 'polypay-crypto-payment-gateway'); ?>
			</h4>
			<code>[polypay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]</code>

			<h4>
				<?php esc_html_e('Parameters', 'polypay-crypto-payment-gateway'); ?>
			</h4>
			<table class="widefat" style="max-width:700px;">
				<thead>
					<tr>
						<th>
							<?php esc_html_e('Parameter', 'polypay-crypto-payment-gateway'); ?>
						</th>
						<th>
							<?php esc_html_e('Required', 'polypay-crypto-payment-gateway'); ?>
						</th>
						<th>
							<?php esc_html_e('Default', 'polypay-crypto-payment-gateway'); ?>
						</th>
						<th>
							<?php esc_html_e('Description', 'polypay-crypto-payment-gateway'); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>amount</code></td>
						<td>✅</td>
						<td>—</td>
						<td>
							<?php esc_html_e('Payment amount', 'polypay-crypto-payment-gateway'); ?>
						</td>
					</tr>
					<tr>
						<td><code>fiat_currency</code></td>
						<td>❌</td>
						<td>USD</td>
						<td>
							<?php esc_html_e('Fiat currency code', 'polypay-crypto-payment-gateway'); ?>
						</td>
					</tr>
					<tr>
						<td><code>description</code></td>
						<td>❌</td>
						<td>—</td>
						<td>
							<?php esc_html_e('Payment description', 'polypay-crypto-payment-gateway'); ?>
						</td>
					</tr>
					<tr>
						<td><code>button_text</code></td>
						<td>❌</td>
						<td>Pay with Crypto</td>
						<td>
							<?php esc_html_e('Button label', 'polypay-crypto-payment-gateway'); ?>
						</td>
					</tr>
					<tr>
						<td><code>redirect_url</code></td>
						<td>❌</td>
						<td>—</td>
						<td>
							<?php esc_html_e('URL to redirect after payment', 'polypay-crypto-payment-gateway'); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the settings page
	 */
	public function render_settings_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e('PolyPay Settings', 'polypay-crypto-payment-gateway'); ?>
			</h1>

			<?php if (class_exists('WooCommerce')): ?>
				<div class="notice notice-info">
					<p>
						✅
						<?php esc_html_e('WooCommerce detected! PolyPay is available as a payment gateway in WooCommerce → Settings → Payments.', 'polypay-crypto-payment-gateway'); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields($this->option_group);
				do_settings_sections('polypay');
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize and validate options
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize_options($input)
	{
		$sanitized = [];
		$sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
		$sanitized['mch_id'] = sanitize_text_field($input['mch_id'] ?? '');

		// Prevent duplicate validation caused by WordPress calling sanitize_callback multiple times
		static $validated = false;

		// Validate the API Key
		if (!empty($sanitized['api_key']) && !$validated) {
			$validated = true;
			$api = new PolyPay_API($sanitized['api_key']);
			$result = $api->activate_plugin();
			$debug_context = $api->get_last_debug_context();
			$log_file = defined('WP_DEBUG') && WP_DEBUG ? PolyPay_API::get_debug_log_file() : '';
			$debug_log_message = $log_file !== '' ? ' ' . sprintf(
				/* translators: %s: debug log file path */
				__('(Debug log: %s)', 'polypay-crypto-payment-gateway'),
				$log_file
			) : '';

			if (is_wp_error($result)) {
				$this->log_api_key_validation_failure('wp_error', [
					'error_code' => $result->get_error_code(),
					'error_message' => $result->get_error_message(),
					'error_data' => $result->get_error_data(),
					'debug_context' => $debug_context,
				]);
				add_settings_error(
					'polypay_messages',
					'polypay_api_error',
					__('PolyPay API connection failed: ', 'polypay-crypto-payment-gateway') . $result->get_error_message() . $debug_log_message,
					'error'
				);
			} elseif (!isset($result['code']) || $result['code'] != 0) {
				$this->log_api_key_validation_failure('business_error', [
					'result' => $result,
					'debug_context' => $debug_context,
				]);
				add_settings_error(
					'polypay_messages',
					'polypay_activation_error',
					__('Plugin activation failed: ', 'polypay-crypto-payment-gateway') . ($result['message'] ?? 'Unknown error') . $debug_log_message,
					'error'
				);
			} else {
				add_settings_error(
					'polypay_messages',
					'polypay_success',
					__('API Key verified successfully!', 'polypay-crypto-payment-gateway'),
					'success'
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Log an API Key validation failure
	 *
	 * @param string $type    Failure type
	 * @param array  $payload Context data
	 * @return void
	 */
	private function log_api_key_validation_failure($type, $payload)
	{
		if (!defined('WP_DEBUG') || !WP_DEBUG) {
			return;
		}

		$log_file = PolyPay_API::get_debug_log_file();
		if ($log_file === '') {
			return;
		}

		$log_line = [
			'time' => gmdate('c'),
			'type' => $type,
			'payload' => $payload,
		];
		$encoded = wp_json_encode($log_line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Debug logs are written to a plugin-specific directory under wp-content/uploads.
		file_put_contents($log_file, $encoded . PHP_EOL, FILE_APPEND);
	}

	/**
	 * Get the API Key
	 *
	 * @return string
	 */
	public static function get_api_key()
	{
		$options = get_option('polypay_options', []);
		return $options['api_key'] ?? '';
	}

	/**
	 * Get the merchant ID
	 *
	 * @return string
	 */
	public static function get_mch_id()
	{
		$options = get_option('polypay_options', []);
		return $options['mch_id'] ?? '';
	}
}
