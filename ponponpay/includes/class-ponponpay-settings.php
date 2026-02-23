<?php
/**
 * PonponPay 独立设置页
 *
 * 通过 WordPress Settings API 提供管理后台配置页面，不依赖 WooCommerce
 *
 * @package PonponPay
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PonponPay_Settings
{
	/** @var string 选项组 */
	private $option_group = 'ponponpay_settings';

	/** @var string 选项名 */
	private $option_name = 'ponponpay_options';

	/**
	 * 构造函数
	 */
	public function __construct()
	{
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'register_settings']);
	}

	/**
	 * 添加设置页到 WordPress 管理菜单
	 */
	public function add_settings_page()
	{
		add_options_page(
			__('PonponPay Settings', 'ponponpay'),
			__('PonponPay', 'ponponpay'),
			'manage_options',
			'ponponpay',
			[$this, 'render_settings_page']
		);
	}

	/**
	 * 注册设置项
	 */
	public function register_settings()
	{
		register_setting($this->option_group, $this->option_name, [
			'sanitize_callback' => [$this, 'sanitize_options'],
		]);

		// API 配置段
		add_settings_section(
			'ponponpay_api_section',
			__('API Configuration', 'ponponpay'),
			function () {
				echo '<p>' . esc_html__('Configure your PonponPay API credentials. Get your API Key from', 'ponponpay')
					. ' <a href="https://ponponpay.com" target="_blank">ponponpay.com</a></p>';
			},
			'ponponpay'
		);

		add_settings_field(
			'api_key',
			__('API Key', 'ponponpay'),
			[$this, 'render_api_key_field'],
			'ponponpay',
			'ponponpay_api_section'
		);

		// 短代码使用说明段
		add_settings_section(
			'ponponpay_shortcode_section',
			__('Shortcode Usage', 'ponponpay'),
			[$this, 'render_shortcode_help'],
			'ponponpay'
		);
	}

	/**
	 * 渲染 API Key 输入框
	 */
	public function render_api_key_field()
	{
		$options = get_option($this->option_name, []);
		$api_key = $options['api_key'] ?? '';
		?>
		<input type="text" name="<?php echo esc_attr($this->option_name); ?>[api_key]" value="<?php echo esc_attr($api_key); ?>"
			class="regular-text" placeholder="<?php esc_attr_e('Enter your PonponPay API Key', 'ponponpay'); ?>" />
		<p class="description">
			<?php
			printf(
				/* translators: %s: callback URL */
				esc_html__('Callback URL: %s', 'ponponpay'),
				'<code>' . esc_html(rest_url('ponponpay/v1/callback')) . '</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * 渲染短代码使用说明
	 */
	public function render_shortcode_help()
	{
		?>
		<div style="background:#f9f9f9;border:1px solid #ddd;border-radius:6px;padding:16px;max-width:700px;">
			<h4 style="margin-top:0;">
				<?php esc_html_e('Basic Usage', 'ponponpay'); ?>
			</h4>
			<code>[ponponpay_button amount="100"]</code>

			<h4>
				<?php esc_html_e('Full Parameters', 'ponponpay'); ?>
			</h4>
			<code>[ponponpay_button amount="99.99" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="https://example.com/thank-you"]</code>

			<h4>
				<?php esc_html_e('Parameters', 'ponponpay'); ?>
			</h4>
			<table class="widefat" style="max-width:700px;">
				<thead>
					<tr>
						<th>
							<?php esc_html_e('Parameter', 'ponponpay'); ?>
						</th>
						<th>
							<?php esc_html_e('Required', 'ponponpay'); ?>
						</th>
						<th>
							<?php esc_html_e('Default', 'ponponpay'); ?>
						</th>
						<th>
							<?php esc_html_e('Description', 'ponponpay'); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>amount</code></td>
						<td>✅</td>
						<td>—</td>
						<td>
							<?php esc_html_e('Payment amount', 'ponponpay'); ?>
						</td>
					</tr>
					<tr>
						<td><code>fiat_currency</code></td>
						<td>❌</td>
						<td>USD</td>
						<td>
							<?php esc_html_e('Fiat currency code', 'ponponpay'); ?>
						</td>
					</tr>
					<tr>
						<td><code>description</code></td>
						<td>❌</td>
						<td>—</td>
						<td>
							<?php esc_html_e('Payment description', 'ponponpay'); ?>
						</td>
					</tr>
					<tr>
						<td><code>button_text</code></td>
						<td>❌</td>
						<td>Pay with Crypto</td>
						<td>
							<?php esc_html_e('Button label', 'ponponpay'); ?>
						</td>
					</tr>
					<tr>
						<td><code>redirect_url</code></td>
						<td>❌</td>
						<td>—</td>
						<td>
							<?php esc_html_e('URL to redirect after payment', 'ponponpay'); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * 渲染设置页面
	 */
	public function render_settings_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e('PonponPay Settings', 'ponponpay'); ?>
			</h1>

			<?php if (class_exists('WooCommerce')): ?>
				<div class="notice notice-info">
					<p>
						✅
						<?php esc_html_e('WooCommerce detected! PonponPay is available as a payment gateway in WooCommerce → Settings → Payments.', 'ponponpay'); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields($this->option_group);
				do_settings_sections('ponponpay');
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * 清理和验证选项
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize_options($input)
	{
		$sanitized = [];
		$sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');

		// 防止 WordPress 多次调用 sanitize_callback 导致重复验证
		static $validated = false;

		// 验证 API Key
		if (!empty($sanitized['api_key']) && !$validated) {
			$validated = true;
			$api = new PonponPay_API($sanitized['api_key']);
			$result = $api->activate_plugin();

			if (is_wp_error($result)) {
				add_settings_error(
					'ponponpay_messages',
					'ponponpay_api_error',
					__('PonponPay API connection failed: ', 'ponponpay') . $result->get_error_message(),
					'error'
				);
			} elseif (!isset($result['code']) || $result['code'] != 0) {
				add_settings_error(
					'ponponpay_messages',
					'ponponpay_activation_error',
					__('Plugin activation failed: ', 'ponponpay') . ($result['message'] ?? 'Unknown error'),
					'error'
				);
			} else {
				add_settings_error(
					'ponponpay_messages',
					'ponponpay_success',
					__('API Key verified successfully!', 'ponponpay'),
					'success'
				);
			}
		}

		return $sanitized;
	}

	/**
	 * 获取 API Key
	 *
	 * @return string
	 */
	public static function get_api_key()
	{
		$options = get_option('ponponpay_options', []);
		return $options['api_key'] ?? '';
	}
}
