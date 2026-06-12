<?php
/**
 * PolyPay shortcode
 *
 * Provides the [polypay_button] shortcode to embed a cryptocurrency payment button on any WordPress page
 *
 * @package PolyPay
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class PolyPay_Shortcode
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_shortcode('polypay_button', [$this, 'render_button']);
	}

	/**
	 * Render the payment button shortcode
	 *
	 * Usage: [polypay_button amount="100" fiat_currency="USD" description="Premium Plan" button_text="Pay with Crypto" redirect_url="/thank-you"]
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML
	 */
	public function render_button($atts)
	{
		$atts = shortcode_atts([
			'amount' => '',
			'fiat_currency' => 'USD',
			'description' => '',
			'button_text' => __('Pay with Crypto', 'polypay-crypto-payment-gateway'),
			'redirect_url' => '',
		], $atts, 'polypay_button');

		// Amount is required
		if (empty($atts['amount']) || !is_numeric($atts['amount'])) {
			if (current_user_can('manage_options')) {
				return '<p style="color:red;">' . esc_html__('[PolyPay] Error: amount parameter is required.', 'polypay-crypto-payment-gateway') . '</p>';
			}
			return '';
		}

		// Check the API Key
		$api_key = PolyPay_Settings::get_api_key();
		if (empty($api_key)) {
			if (current_user_can('manage_options')) {
				return '<p style="color:red;">' . esc_html__('[PolyPay] Error: API Key not configured. Go to Settings → PolyPay.', 'polypay-crypto-payment-gateway') . '</p>';
			}
			return '';
		}

		// Generate a unique ID
		$unique_id = 'polypay-' . wp_unique_id();

		ob_start();
		?>
		<div id="<?php echo esc_attr($unique_id); ?>" class="polypay-payment-widget">
			<div class="polypay-widget-info">
				<span class="polypay-amount">
					<?php echo esc_html(number_format(floatval($atts['amount']), 2)); ?>
					<?php echo esc_html($atts['fiat_currency']); ?>
				</span>
				<?php if (!empty($atts['description'])): ?>
					<span class="polypay-desc">
						<?php echo esc_html($atts['description']); ?>
					</span>
				<?php endif; ?>
			</div>

			<!-- Payment button -->
			<div class="polypay-step polypay-step-button">
				<button type="button" class="polypay-btn polypay-btn-primary polypay-pay-btn"
					data-amount="<?php echo esc_attr($atts['amount']); ?>"
					data-fiat-currency="<?php echo esc_attr($atts['fiat_currency']); ?>"
					data-description="<?php echo esc_attr($atts['description']); ?>"
					data-redirect-url="<?php echo esc_attr($atts['redirect_url']); ?>">
					🔐
					<?php echo esc_html($atts['button_text']); ?>
				</button>
			</div>

			<!-- Processing -->
			<div class="polypay-step polypay-step-processing" style="display:none;">
				<div class="polypay-loading">
					<?php esc_html_e('Initializing payment...', 'polypay-crypto-payment-gateway'); ?>
				</div>
			</div>

			<div class="polypay-error" style="display:none;"></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
