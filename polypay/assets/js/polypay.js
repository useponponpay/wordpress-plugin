/**
 * PolyPay WordPress Frontend Script
 *
 * 处理短代码支付按钮的交互逻辑
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		// 点击短代码支付按钮 → 初始化本地订单并跳到收银台
		$(document).on('click', '.polypay-pay-btn', function () {
			var $widget = $(this).closest('.polypay-payment-widget');
			var $btn = $(this);

			var amount = $btn.data('amount');
			var fiatCurrency = $btn.data('fiat-currency') || 'USD';
			var description = $btn.data('description') || '';
			var redirectUrl = $btn.data('redirect-url') || '';

			// 显示处理中
			$widget.find('.polypay-step-button').hide();
			$widget.find('.polypay-step-processing').show();
			$widget.find('.polypay-error').hide();

			// 初始化订单
			$.ajax({
				url: polypayAjax.restUrl + 'init-payment',
				method: 'POST',
				headers: { 'X-WP-Nonce': polypayAjax.nonce },
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
						$widget.find('.polypay-step-processing').hide();
						$widget.find('.polypay-step-button').show();
						$widget.find('.polypay-error').text(
							res.error || polypayAjax.i18n.failedInitPayment
						).show();
					}
				},
				error: function () {
					$widget.find('.polypay-step-processing').hide();
					$widget.find('.polypay-step-button').show();
					$widget.find('.polypay-error').text(polypayAjax.i18n.networkErrorTryAgain).show();
				}
			});
		});
	});
})(jQuery);
