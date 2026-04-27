/**
 * PonponPay 独立收银台 JS
 *
 * 依赖 wp_localize_script 注入的 ponponpayCheckout 全局变量：
 * - restUrl   REST API 基础路径
 * - nonce     WP REST nonce
 * - orderId   订单号
 * - token     收银台令牌
 * - i18n      国际化文案
 *
 * @package PonponPay
 * @version 1.0.0
 */

/**
 * 构建 SVG 图标 data URL
 *
 * @param {string} label      图标文字
 * @param {string} background 背景颜色
 * @return {string}
 */
function buildIconUrl(label, background) {
	var safeLabel = String(label || '?').slice(0, 4).toUpperCase();
	var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28">' +
		'<rect width="28" height="28" rx="14" fill="' + background + '"/>' +
		'<text x="14" y="17" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" font-weight="700" fill="#ffffff">' + safeLabel + '</text>' +
		'</svg>';
	return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
}

/**
 * 创建图标 img 元素
 *
 * @param {string} className  CSS 类名
 * @param {string} label      图标文字
 * @param {string} background 背景颜色
 * @return {HTMLImageElement}
 */
function createIcon(className, label, background) {
	var img = document.createElement('img');
	img.className = className;
	img.alt = label;
	img.src = buildIconUrl(label, background);
	return img;
}

/**
 * 创建下拉箭头 SVG 元素
 *
 * @return {SVGElement}
 */
function createChevronIcon() {
	var svgNS = 'http://www.w3.org/2000/svg';
	var svg = document.createElementNS(svgNS, 'svg');
	svg.setAttribute('class', 'chevron');
	svg.setAttribute('width', '20');
	svg.setAttribute('height', '20');
	svg.setAttribute('viewBox', '0 0 24 24');
	svg.setAttribute('fill', 'none');
	svg.setAttribute('stroke', 'currentColor');
	svg.setAttribute('stroke-width', '2');
	svg.setAttribute('stroke-linecap', 'round');
	svg.setAttribute('stroke-linejoin', 'round');
	var polyline = document.createElementNS(svgNS, 'polyline');
	polyline.setAttribute('points', '6 9 12 15 18 9');
	svg.appendChild(polyline);
	return svg;
}

/**
 * 创建选中对勾 SVG 元素
 *
 * @return {SVGElement}
 */
function createCheckIcon() {
	var svgNS = 'http://www.w3.org/2000/svg';
	var svg = document.createElementNS(svgNS, 'svg');
	svg.setAttribute('class', 'check-icon');
	svg.setAttribute('viewBox', '0 0 24 24');
	svg.setAttribute('fill', 'none');
	svg.setAttribute('stroke', 'currentColor');
	svg.setAttribute('stroke-width', '4');
	svg.setAttribute('stroke-linecap', 'round');
	svg.setAttribute('stroke-linejoin', 'round');
	var polyline = document.createElementNS(svgNS, 'polyline');
	polyline.setAttribute('points', '20 6 9 17 4 12');
	svg.appendChild(polyline);
	return svg;
}

document.addEventListener('DOMContentLoaded', function () {
	var container = document.getElementById('methodsContainer');
	var submitBtn = document.getElementById('submitBtn');
	var errorMsg = document.getElementById('errorMsg');
	var loadingOverlay = document.getElementById('loadingOverlay');
	var selectedMethodText = document.getElementById('selectedMethodText');

	var config = window.ponponpayCheckout || {};
	var restUrl = config.restUrl || '';
	var nonce = config.nonce || '';
	var orderId = config.orderId || '';
	var checkoutToken = config.token || '';
	var i18n = config.i18n || {};

	var selectedValue = null;

	/**
	 * 显示错误信息
	 *
	 * @param {string} msg 错误文案
	 */
	function showError(msg) {
		errorMsg.textContent = msg;
		errorMsg.style.display = 'block';
		loadingOverlay.style.display = 'none';
	}

	// 1. 获取可用支付方式
	fetch(restUrl + 'methods', {
		method: 'GET',
		headers: { 'X-WP-Nonce': nonce }
	})
		.then(function (res) { return res.json(); })
		.then(function (data) {
			loadingOverlay.style.display = 'none';

			if (!data.success || !data.methods || data.methods.length === 0) {
				container.innerHTML = '<div style="text-align: center; color: #b91c1c; font-size: 14px; padding: 12px; background: #fef2f2; border-radius: 8px;">' + (i18n.no_payment_methods || 'No payment methods available') + '</div>';
				return showError(data.error || i18n.no_payment_methods_contact || 'No payment methods available. Contact administrator.');
			}

			container.innerHTML = '';

			// 构建网络分组手风琴
			data.methods.forEach(function (method, index) {
				var network = method.network || '';
				var currencies = method.currencies || [];
				if (currencies.length === 0) return;

				var group = document.createElement('div');
				group.className = 'network-group';
				// 默认展开第一个网络组
				if (index === 0) {
					group.classList.add('is-open');
				}

				// 构建 Header
				var header = document.createElement('div');
				header.className = 'network-header';
				var title = document.createElement('div');
				title.className = 'network-title';
				title.appendChild(createIcon('network-logo', network, '#6366f1'));
				var titleText = document.createTextNode(network);
				title.appendChild(titleText);
				header.appendChild(title);
				header.appendChild(createChevronIcon());

				// 切换手风琴状态
				header.addEventListener('click', function () {
					var isOpen = group.classList.contains('is-open');
					// 关闭所有其他组
					document.querySelectorAll('.network-group').forEach(function (g) { g.classList.remove('is-open'); });
					// 切换当前组
					if (!isOpen) {
						group.classList.add('is-open');
					}
				});

				// 构建 Body 和卡片
				var body = document.createElement('div');
				body.className = 'network-body';

				var grid = document.createElement('div');
				grid.className = 'currency-grid';

				currencies.forEach(function (curr) {
					var card = document.createElement('div');
					card.className = 'method-card';
					card.dataset.value = network + '|' + curr;
					card.appendChild(createIcon('currency-logo', curr, '#4f46e5'));
					var currencyText = document.createElement('div');
					currencyText.className = 'method-currency';
					currencyText.textContent = curr;
					card.appendChild(currencyText);
					card.appendChild(createCheckIcon());

					card.addEventListener('click', function (e) {
						e.stopPropagation(); // 防止触发手风琴切换
						document.querySelectorAll('.method-card').forEach(function (c) { c.classList.remove('selected'); });
						card.classList.add('selected');
						selectedValue = card.dataset.value;
						selectedMethodText.textContent = network + ' / ' + curr;
						submitBtn.disabled = false;
					});

					grid.appendChild(card);
				});

				body.appendChild(grid);
				group.appendChild(header);
				group.appendChild(body);
				container.appendChild(group);
			});
		})
		.catch(function () {
			showError(i18n.network_error_loading_methods || 'Network error while loading methods.');
		});

	// 2. 提交并创建订单
	submitBtn.addEventListener('click', function () {
		if (!selectedValue) return;

		var parts = selectedValue.split('|');
		var network = parts[0];
		var currency = parts[1];

		loadingOverlay.style.display = 'flex';
		errorMsg.style.display = 'none';

		fetch(restUrl + 'create-order', {
			method: 'POST',
			headers: {
				'X-WP-Nonce': nonce,
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				order_id: orderId,
				token: checkoutToken,
				network: network,
				currency: currency
			})
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data.success && data.payment_url) {
					window.open(data.payment_url, '_blank', 'noopener,noreferrer');
					loadingOverlay.style.display = 'none';
				} else {
					showError(data.error || i18n.failed_create_order || 'Failed to create order');
				}
			})
			.catch(function () {
				showError(i18n.network_error_creating_order || 'Network error while creating order.');
			});
	});
});
