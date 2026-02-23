/**
 * PonponPay WordPress Frontend Script
 *
 * 处理短代码支付按钮的交互逻辑
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		// 点击短代码支付按钮 → 初始化本地订单并跳到收银台
		$(document).on('click', '.ponponpay-pay-btn', function () {
			var $widget = $(this).closest('.ponponpay-payment-widget');
			var $btn = $(this);

			var amount = $btn.data('amount');
			var fiatCurrency = $btn.data('fiat-currency') || 'USD';
			var description = $btn.data('description') || '';
			var redirectUrl = $btn.data('redirect-url') || '';

			// 显示处理中
			$widget.find('.ponponpay-step-button').hide();
			$widget.find('.ponponpay-step-processing').show();
			$widget.find('.ponponpay-error').hide();

			// 初始化订单
			$.ajax({
				url: ponponpayAjax.restUrl + 'init-payment',
				method: 'POST',
				headers: { 'X-WP-Nonce': ponponpayAjax.nonce },
				contentType: 'application/json',
				data: JSON.stringify({
					amount: parseFloat(amount),
					fiat_currency: fiatCurrency,
					description: description,
					redirect_url: redirectUrl
				}),
				success: function (res) {
					if (res.success && res.checkout_url) {
						window.location.href = res.checkout_url;
					} else {
						$widget.find('.ponponpay-step-processing').hide();
						$widget.find('.ponponpay-step-button').show();
						$widget.find('.ponponpay-error').text(res.error || 'Failed to initialize payment').show();
					}
				},
				error: function () {
					$widget.find('.ponponpay-step-processing').hide();
					$widget.find('.ponponpay-step-button').show();
					$widget.find('.ponponpay-error').text('Network error. Please try again.').show();
				}
			});
		});
	});
})(jQuery);
