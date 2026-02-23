<?php
/**
 * PonponPay 独立支付选择页面模板
 *
 * 当访问 /?ponponpay_checkout=<order_id> 时渲染此页面。
 *
 * @package PonponPay
 */

if (!defined('ABSPATH')) {
	exit;
}

$locale = function_exists('determine_locale') ? determine_locale() : get_locale();
$locale = strtolower((string) $locale);

$lang = 'en';
if (strpos($locale, 'zh') === 0) {
	$lang = 'zh';
} elseif (strpos($locale, 'ja') === 0) {
	$lang = 'ja';
} elseif (strpos($locale, 'ko') === 0) {
	$lang = 'ko';
} elseif (strpos($locale, 'fr') === 0) {
	$lang = 'fr';
} elseif (strpos($locale, 'de') === 0) {
	$lang = 'de';
} elseif (strpos($locale, 'es') === 0) {
	$lang = 'es';
} elseif (strpos($locale, 'pt') === 0) {
	$lang = 'pt';
} elseif (strpos($locale, 'ru') === 0) {
	$lang = 'ru';
} elseif (strpos($locale, 'ar') === 0) {
	$lang = 'ar';
}

$translations = [
	'en' => [
		'invalid_request' => 'Invalid request',
		'order_not_found' => 'Order not found',
		'pay_with_crypto' => 'Pay with Crypto',
		'checkout_title' => 'PonponPay Checkout',
		'selected' => 'Selected',
		'select_payment_method' => 'Select Payment Method',
		'loading_methods' => 'Loading methods...',
		'confirm_pay' => 'Confirm & Pay',
		'cancel_and_return' => 'Cancel and return',
		'processing' => 'Processing...',
		'secured_by' => 'Secured by',
		'no_payment_methods' => 'No payment methods available',
		'no_payment_methods_contact' => 'No payment methods available. Contact administrator.',
		'network_error_loading_methods' => 'Network error while loading methods.',
		'failed_create_order' => 'Failed to create order',
		'network_error_creating_order' => 'Network error while creating order.',
	],
	'zh' => [
		'invalid_request' => '无效请求',
		'order_not_found' => '订单不存在',
		'pay_with_crypto' => '使用加密货币支付',
		'checkout_title' => 'PonponPay 收银台',
		'selected' => '已选择',
		'select_payment_method' => '选择支付方式',
		'loading_methods' => '正在加载支付方式...',
		'confirm_pay' => '确认并支付',
		'cancel_and_return' => '取消并返回',
		'processing' => '处理中...',
		'secured_by' => '安全支持',
		'no_payment_methods' => '暂无可用支付方式',
		'no_payment_methods_contact' => '暂无可用支付方式，请联系管理员。',
		'network_error_loading_methods' => '加载支付方式时发生网络错误。',
		'failed_create_order' => '创建订单失败',
		'network_error_creating_order' => '创建订单时发生网络错误。',
	],
	'ja' => [
		'invalid_request' => '無効なリクエストです',
		'order_not_found' => '注文が見つかりません',
		'pay_with_crypto' => '暗号資産で支払う',
		'checkout_title' => 'PonponPay チェックアウト',
		'selected' => '選択済み',
		'select_payment_method' => '支払い方法を選択',
		'loading_methods' => '支払い方法を読み込み中...',
		'confirm_pay' => '確認して支払う',
		'cancel_and_return' => 'キャンセルして戻る',
		'processing' => '処理中...',
		'secured_by' => '提供',
		'no_payment_methods' => '利用可能な支払い方法がありません',
		'no_payment_methods_contact' => '利用可能な支払い方法がありません。管理者に連絡してください。',
		'network_error_loading_methods' => '支払い方法の読み込み中にネットワークエラーが発生しました。',
		'failed_create_order' => '注文の作成に失敗しました',
		'network_error_creating_order' => '注文作成中にネットワークエラーが発生しました。',
	],
	'ko' => [
		'invalid_request' => '잘못된 요청입니다',
		'order_not_found' => '주문을 찾을 수 없습니다',
		'pay_with_crypto' => '암호화폐로 결제',
		'checkout_title' => 'PonponPay 결제',
		'selected' => '선택됨',
		'select_payment_method' => '결제 수단 선택',
		'loading_methods' => '결제 수단 불러오는 중...',
		'confirm_pay' => '확인 후 결제',
		'cancel_and_return' => '취소하고 돌아가기',
		'processing' => '처리 중...',
		'secured_by' => '보안 제공',
		'no_payment_methods' => '사용 가능한 결제 수단이 없습니다',
		'no_payment_methods_contact' => '사용 가능한 결제 수단이 없습니다. 관리자에게 문의하세요.',
		'network_error_loading_methods' => '결제 수단을 불러오는 중 네트워크 오류가 발생했습니다.',
		'failed_create_order' => '주문 생성에 실패했습니다',
		'network_error_creating_order' => '주문 생성 중 네트워크 오류가 발생했습니다.',
	],
	'fr' => [
		'invalid_request' => 'Requête invalide',
		'order_not_found' => 'Commande introuvable',
		'pay_with_crypto' => 'Payer en crypto',
		'checkout_title' => 'Paiement PonponPay',
		'selected' => 'Sélectionné',
		'select_payment_method' => 'Sélectionner le mode de paiement',
		'loading_methods' => 'Chargement des moyens de paiement...',
		'confirm_pay' => 'Confirmer et payer',
		'cancel_and_return' => 'Annuler et revenir',
		'processing' => 'Traitement en cours...',
		'secured_by' => 'Sécurisé par',
		'no_payment_methods' => 'Aucun moyen de paiement disponible',
		'no_payment_methods_contact' => 'Aucun moyen de paiement disponible. Contactez l’administrateur.',
		'network_error_loading_methods' => 'Erreur réseau lors du chargement des moyens de paiement.',
		'failed_create_order' => 'Échec de la création de la commande',
		'network_error_creating_order' => 'Erreur réseau lors de la création de la commande.',
	],
	'de' => [
		'invalid_request' => 'Ungültige Anfrage',
		'order_not_found' => 'Bestellung nicht gefunden',
		'pay_with_crypto' => 'Mit Krypto bezahlen',
		'checkout_title' => 'PonponPay Checkout',
		'selected' => 'Ausgewählt',
		'select_payment_method' => 'Zahlungsmethode auswählen',
		'loading_methods' => 'Zahlungsmethoden werden geladen...',
		'confirm_pay' => 'Bestätigen und bezahlen',
		'cancel_and_return' => 'Abbrechen und zurück',
		'processing' => 'Verarbeitung...',
		'secured_by' => 'Gesichert durch',
		'no_payment_methods' => 'Keine Zahlungsmethoden verfügbar',
		'no_payment_methods_contact' => 'Keine Zahlungsmethoden verfügbar. Kontaktieren Sie den Administrator.',
		'network_error_loading_methods' => 'Netzwerkfehler beim Laden der Zahlungsmethoden.',
		'failed_create_order' => 'Bestellung konnte nicht erstellt werden',
		'network_error_creating_order' => 'Netzwerkfehler beim Erstellen der Bestellung.',
	],
	'es' => [
		'invalid_request' => 'Solicitud inválida',
		'order_not_found' => 'Pedido no encontrado',
		'pay_with_crypto' => 'Pagar con criptomonedas',
		'checkout_title' => 'Pago de PonponPay',
		'selected' => 'Seleccionado',
		'select_payment_method' => 'Seleccionar método de pago',
		'loading_methods' => 'Cargando métodos de pago...',
		'confirm_pay' => 'Confirmar y pagar',
		'cancel_and_return' => 'Cancelar y volver',
		'processing' => 'Procesando...',
		'secured_by' => 'Protegido por',
		'no_payment_methods' => 'No hay métodos de pago disponibles',
		'no_payment_methods_contact' => 'No hay métodos de pago disponibles. Contacte al administrador.',
		'network_error_loading_methods' => 'Error de red al cargar los métodos de pago.',
		'failed_create_order' => 'No se pudo crear el pedido',
		'network_error_creating_order' => 'Error de red al crear el pedido.',
	],
	'pt' => [
		'invalid_request' => 'Solicitação inválida',
		'order_not_found' => 'Pedido não encontrado',
		'pay_with_crypto' => 'Pagar com cripto',
		'checkout_title' => 'Checkout PonponPay',
		'selected' => 'Selecionado',
		'select_payment_method' => 'Selecionar método de pagamento',
		'loading_methods' => 'Carregando métodos de pagamento...',
		'confirm_pay' => 'Confirmar e pagar',
		'cancel_and_return' => 'Cancelar e voltar',
		'processing' => 'Processando...',
		'secured_by' => 'Protegido por',
		'no_payment_methods' => 'Nenhum método de pagamento disponível',
		'no_payment_methods_contact' => 'Nenhum método de pagamento disponível. Contate o administrador.',
		'network_error_loading_methods' => 'Erro de rede ao carregar métodos de pagamento.',
		'failed_create_order' => 'Falha ao criar pedido',
		'network_error_creating_order' => 'Erro de rede ao criar pedido.',
	],
	'ru' => [
		'invalid_request' => 'Неверный запрос',
		'order_not_found' => 'Заказ не найден',
		'pay_with_crypto' => 'Оплатить криптовалютой',
		'checkout_title' => 'Оплата через PonponPay',
		'selected' => 'Выбрано',
		'select_payment_method' => 'Выберите способ оплаты',
		'loading_methods' => 'Загрузка способов оплаты...',
		'confirm_pay' => 'Подтвердить и оплатить',
		'cancel_and_return' => 'Отменить и вернуться',
		'processing' => 'Обработка...',
		'secured_by' => 'Защищено',
		'no_payment_methods' => 'Нет доступных способов оплаты',
		'no_payment_methods_contact' => 'Нет доступных способов оплаты. Обратитесь к администратору.',
		'network_error_loading_methods' => 'Ошибка сети при загрузке способов оплаты.',
		'failed_create_order' => 'Не удалось создать заказ',
		'network_error_creating_order' => 'Ошибка сети при создании заказа.',
	],
	'ar' => [
		'invalid_request' => 'طلب غير صالح',
		'order_not_found' => 'الطلب غير موجود',
		'pay_with_crypto' => 'الدفع بالعملات الرقمية',
		'checkout_title' => 'الدفع عبر PonponPay',
		'selected' => 'المحدد',
		'select_payment_method' => 'اختر طريقة الدفع',
		'loading_methods' => 'جارٍ تحميل طرق الدفع...',
		'confirm_pay' => 'تأكيد الدفع',
		'cancel_and_return' => 'إلغاء والعودة',
		'processing' => 'جارٍ المعالجة...',
		'secured_by' => 'مؤمّن بواسطة',
		'no_payment_methods' => 'لا توجد طرق دفع متاحة',
		'no_payment_methods_contact' => 'لا توجد طرق دفع متاحة. يرجى التواصل مع المسؤول.',
		'network_error_loading_methods' => 'خطأ في الشبكة أثناء تحميل طرق الدفع.',
		'failed_create_order' => 'فشل إنشاء الطلب',
		'network_error_creating_order' => 'خطأ في الشبكة أثناء إنشاء الطلب.',
	],
];

$i18n = isset($translations[$lang]) ? $translations[$lang] : $translations['en'];

$order_id = get_query_var('ponponpay_checkout');
if (empty($order_id)) {
	wp_die(esc_html($i18n['invalid_request']));
}

// 查询订单
global $wpdb;
$table = $wpdb->prefix . 'ponponpay_payments';
$payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %s", $order_id));

$is_wc_order = false;
$amount = 0;
$fiat_currency = 'USD';
$description = '';
$redirect_url = home_url();

if (!$payment) {
	// 检查是否为 WooCommerce 订单
	if (strpos($order_id, 'WC_') === 0 && class_exists('WC_Order')) {
		$wc_order_id = str_replace('WC_', '', $order_id);
		$wc_order = wc_get_order($wc_order_id);
		if (!$wc_order) {
			wp_die(esc_html($i18n['order_not_found']));
		}

		$is_wc_order = true;
		$amount = $wc_order->get_total();
		$fiat_currency = $wc_order->get_currency();
		$description = sprintf(__('Order #%s', 'woocommerce'), $wc_order->get_order_number());
		$redirect_url = $wc_order->get_checkout_order_received_url();

		// 检查是否已经标记了 payment_url（说明已经创建过后台订单）
		$existing_payment_url = $wc_order->get_meta('_ponponpay_payment_url');
		if ($existing_payment_url) {
			wp_redirect($existing_payment_url);
			exit;
		}
	} else {
		wp_die(esc_html($i18n['order_not_found']));
	}
} else {
	if ($payment->status > 0 && !empty($payment->payment_url)) {
		// 已支付或已有 payment_url
		wp_redirect($payment->payment_url);
		exit;
	}
	$amount = $payment->amount;
	$fiat_currency = $payment->fiat_currency;
	$description = $payment->description;
	$redirect_url = $payment->redirect_url ?: home_url();
}

// 提取当前 REST API 基础路径和 nonce 给前端 JS 调用
$rest_url = esc_url_raw(rest_url('ponponpay/v1/'));
$nonce = wp_create_nonce('wp_rest');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>
		<?php echo esc_html($i18n['pay_with_crypto']); ?> -
		<?php bloginfo('name'); ?>
	</title>
	<style>
		:root {
			--primary: #4f46e5;
			--primary-hover: #4338ca;
			--bg: #f3f4f6;
			--card-bg: rgba(255, 255, 255, 0.9);
			--text-main: #111827;
			--text-muted: #6b7280;
			--border: #e5e7eb;
			--ring: rgba(79, 70, 229, 0.2);
		}

		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			background: linear-gradient(135deg, #f6f8fb 0%, #e5e7eb 100%);
			color: var(--text-main);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}

		.checkout-container {
			width: 100%;
			max-width: 440px;
			background: var(--card-bg);
			backdrop-filter: blur(12px);
			-webkit-backdrop-filter: blur(12px);
			border-radius: 24px;
			box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06), 0 1px 3px rgba(0, 0, 0, 0.05);
			overflow: hidden;
			border: 1px solid rgba(255, 255, 255, 0.5);
			animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
		}

		@keyframes slideUp {
			from {
				opacity: 0;
				transform: translateY(20px);
			}

			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.header {
			padding: 32px 32px 24px;
			text-align: center;
			border-bottom: 1px solid var(--border);
		}

		.brand {
			font-size: 14px;
			font-weight: 600;
			color: var(--text-muted);
			text-transform: uppercase;
			letter-spacing: 1px;
			margin-bottom: 12px;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 6px;
		}

		.amount {
			font-size: 42px;
			font-weight: 800;
			color: var(--text-main);
			line-height: 1.1;
			letter-spacing: -1px;
		}

		.currency {
			font-size: 20px;
			color: var(--text-muted);
			font-weight: 500;
		}

		.desc {
			margin-top: 8px;
			font-size: 15px;
			color: var(--text-muted);
		}

		.selected-method {
			margin-top: 12px;
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 12px;
			border-radius: 9999px;
			background: #eef2ff;
			color: #4338ca;
			font-size: 13px;
			font-weight: 600;
			line-height: 1;
		}

		.selected-method-label {
			color: #6366f1;
			font-weight: 500;
		}

		.content {
			padding: 32px;
		}

		.section-title {
			font-size: 14px;
			font-weight: 600;
			color: var(--text-main);
			margin-bottom: 16px;
		}

		.select-group {
			margin-bottom: 24px;
			position: relative;
		}

		.methods-container {
			display: flex;
			flex-direction: column;
			gap: 12px;
		}

		.network-group {
			border: 1px solid var(--border);
			border-radius: 16px;
			background: #fff;
			overflow: hidden;
			transition: all 0.2s ease;
		}
		
		.network-group:hover {
			border-color: #d1d5db;
		}

		.network-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 16px;
			cursor: pointer;
			user-select: none;
			background: #fff;
		}

		.network-title {
			display: flex;
			align-items: center;
			gap: 12px;
			font-size: 16px;
			font-weight: 600;
			color: var(--text-main);
		}

		.network-logo {
			width: 28px;
			height: 28px;
			border-radius: 50%;
			object-fit: contain;
			background: #f3f4f6;
			padding: 4px;
		}

		.chevron {
			color: var(--text-muted);
			transition: transform 0.3s ease;
		}

		.network-group.is-open .chevron {
			transform: rotate(180deg);
		}

		.network-body {
			display: none;
			padding: 0 16px 16px;
			border-top: 1px solid var(--border);
			background: #fafafa;
		}

		.network-group.is-open .network-body {
			display: block;
			animation: slideDown 0.3s ease;
		}

		@keyframes slideDown {
			from { opacity: 0; transform: translateY(-10px); }
			to { opacity: 1; transform: translateY(0); }
		}

		.currency-grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 12px;
			margin-top: 16px;
		}

		.method-card {
			border: 2px solid var(--border);
			border-radius: 12px;
			padding: 14px 12px;
			cursor: pointer;
			transition: all 0.2s ease;
			background: #fff;
			display: flex;
			align-items: center;
			gap: 10px;
			position: relative;
			overflow: hidden;
		}

		.method-card:hover {
			border-color: var(--primary);
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(0,0,0,0.05);
		}

		.method-card.selected {
			border-color: var(--primary);
			background: rgba(79, 70, 229, 0.04);
			box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
		}

		/* Selected indicator */
		.method-card::after {
			content: '';
			position: absolute;
			top: 0;
			right: 0;
			width: 0;
			height: 0;
			border-style: solid;
			border-width: 0 24px 24px 0;
			border-color: transparent var(--primary) transparent transparent;
			opacity: 0;
			transition: opacity 0.2s;
		}

		.method-card.selected::after {
			opacity: 1;
		}

		.method-card .check-icon {
			position: absolute;
			top: 2px;
			right: 2px;
			color: #fff;
			width: 10px;
			height: 10px;
			opacity: 0;
			z-index: 1;
			transition: opacity 0.2s;
		}

		.method-card.selected .check-icon {
			opacity: 1;
		}

		.currency-logo {
			width: 24px;
			height: 24px;
			object-fit: contain;
		}

		.method-currency {
			font-size: 15px;
			font-weight: 600;
			color: var(--text-main);
			line-height: 1;
		}

		.btn-submit {
			width: 100%;
			padding: 16px;
			background: var(--primary);
			color: #fff;
			border: none;
			border-radius: 12px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s ease;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
		}

		.btn-submit:hover:not(:disabled) {
			background: var(--primary-hover);
			transform: translateY(-1px);
			box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
		}

		.btn-submit:disabled {
			background: #9ca3af;
			cursor: not-allowed;
			box-shadow: none;
			transform: none;
		}

		.btn-cancel {
			display: block;
			text-align: center;
			margin-top: 20px;
			color: var(--text-muted);
			text-decoration: none;
			font-size: 14px;
			font-weight: 500;
			transition: color 0.2s ease;
		}

		.btn-cancel:hover {
			color: var(--text-main);
		}

		.loading-overlay {
			position: absolute;
			inset: 0;
			background: rgba(255, 255, 255, 0.8);
			backdrop-filter: blur(4px);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			border-radius: 24px;
			z-index: 10;
		}

		.spinner {
			width: 40px;
			height: 40px;
			border: 3px solid rgba(79, 70, 229, 0.1);
			border-left-color: var(--primary);
			border-radius: 50%;
			animation: spin 1s linear infinite;
			margin-bottom: 16px;
		}

		@keyframes spin {
			to {
				transform: rotate(360deg);
			}
		}

		.error-msg {
			padding: 12px 16px;
			background: #fef2f2;
			color: #b91c1c;
			border-radius: 8px;
			font-size: 14px;
			margin-bottom: 24px;
			display: none;
			border: 1px solid #fecaca;
		}

		.footer {
			text-align: center;
			padding-bottom: 24px;
			font-size: 13px;
			color: #9ca3af;
		}

		.footer a {
			color: #9ca3af;
			text-decoration: none;
		}

		.footer a:hover {
			text-decoration: underline;
		}
	</style>
</head>

<body>

	<div class="checkout-container" id="app">
		<div class="header">
			<div class="brand">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
				</svg>
				<?php echo esc_html($i18n['checkout_title']); ?>
			</div>
			<div class="amount">
				<?php echo esc_html(number_format(floatval($amount), 2)); ?>
				<span class="currency">
					<?php echo esc_html($fiat_currency); ?>
				</span>
			</div>
			<?php if ($description): ?>
				<div class="desc">
					<?php echo esc_html($description); ?>
				</div>
			<?php endif; ?>
			<div class="selected-method" id="selectedMethodDisplay">
				<span class="selected-method-label"><?php echo esc_html($i18n['selected']); ?>:</span>
				<span id="selectedMethodText">--</span>
			</div>
		</div>

		<div class="content">
			<div class="error-msg" id="errorMsg"></div>

			<div class="select-group">
				<div class="section-title"><?php echo esc_html($i18n['select_payment_method']); ?></div>
				<div id="methodsContainer" class="methods-container">
					<div style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 14px;">
						<?php echo esc_html($i18n['loading_methods']); ?>
					</div>
				</div>
			</div>

			<button type="button" class="btn-submit" id="submitBtn" disabled>
				<span>
					<?php echo esc_html($i18n['confirm_pay']); ?>
				</span>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round">
					<line x1="5" y1="12" x2="19" y2="12"></line>
					<polyline points="12 5 19 12 12 19"></polyline>
				</svg>
			</button>

			<a href="<?php echo esc_url($redirect_url); ?>" class="btn-cancel">
				<?php echo esc_html($i18n['cancel_and_return']); ?>
			</a>
		</div>

		<div class="loading-overlay" id="loadingOverlay">
			<div class="spinner"></div>
			<div style="font-weight: 500; color: var(--primary);"><?php echo esc_html($i18n['processing']); ?></div>
		</div>

		<div class="footer">
			<?php echo esc_html($i18n['secured_by']); ?> <a href="https://ponponpay.com" target="_blank">PonponPay</a>
		</div>
	</div>

	<script>
		// --- Icon Helper ---
		const CryptoIcons = {
			'USDT': 'https://cryptologos.cc/logos/tether-usdt-logo.svg',
			'USDC': 'https://cryptologos.cc/logos/usd-coin-usdc-logo.svg',
			'BUSD': 'https://cryptologos.cc/logos/binance-usd-busd-logo.svg',
			'ETH': 'https://cryptologos.cc/logos/ethereum-eth-logo.svg',
			'BTC': 'https://cryptologos.cc/logos/bitcoin-btc-logo.svg',
			'TRX': 'https://cryptologos.cc/logos/tron-trx-logo.svg',
			'BNB': 'https://cryptologos.cc/logos/bnb-bnb-logo.svg',
			'MATIC': 'https://cryptologos.cc/logos/polygon-matic-logo.svg',
			'SOL': 'https://cryptologos.cc/logos/solana-sol-logo.svg'
		};
		const NetworkIcons = {
			'TRON': 'https://cryptologos.cc/logos/tron-trx-logo.svg',
			'BSC': 'https://cryptologos.cc/logos/bnb-bnb-logo.svg',
			'Ethereum': 'https://cryptologos.cc/logos/ethereum-eth-logo.svg',
			'Polygon': 'https://cryptologos.cc/logos/polygon-matic-logo.svg',
			'Solana': 'https://cryptologos.cc/logos/solana-sol-logo.svg',
			'Bitcoin': 'https://cryptologos.cc/logos/bitcoin-btc-logo.svg'
		};

		function getIconUrl(type, name) {
			const dict = type === 'network' ? NetworkIcons : CryptoIcons;
			// Ignore case and find matched icon
			const key = Object.keys(dict).find(k => k.toLowerCase() === name.toLowerCase());
			return key ? dict[key] : 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="%239ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>';
		}

		document.addEventListener('DOMContentLoaded', () => {
			const container = document.getElementById('methodsContainer');
			const submitBtn = document.getElementById('submitBtn');
			const errorMsg = document.getElementById('errorMsg');
			const loadingOverlay = document.getElementById('loadingOverlay');
			const selectedMethodText = document.getElementById('selectedMethodText');

			const restUrl = <?php echo wp_json_encode($rest_url); ?>;
			const nonce = <?php echo wp_json_encode($nonce); ?>;
			const orderId = <?php echo wp_json_encode($order_id); ?>;
			const i18n = <?php echo wp_json_encode($i18n); ?>;

			let selectedValue = null;

			function showError(msg) {
				errorMsg.textContent = msg;
				errorMsg.style.display = 'block';
				loadingOverlay.style.display = 'none';
			}

			// 1. Fetch available methods
			fetch(restUrl + 'methods', {
				method: 'GET',
				headers: { 'X-WP-Nonce': nonce }
			})
				.then(res => res.json())
				.then(data => {
					loadingOverlay.style.display = 'none';

					if (!data.success || !data.methods || data.methods.length === 0) {
						container.innerHTML = `<div style="text-align: center; color: #b91c1c; font-size: 14px; padding: 12px; background: #fef2f2; border-radius: 8px;">${i18n.no_payment_methods}</div>`;
						return showError(data.error || i18n.no_payment_methods_contact);
					}

					container.innerHTML = '';
					
					// Build Network Accordions
					data.methods.forEach((method, index) => {
						const network = method.network || '';
						const currencies = method.currencies || [];
						if(currencies.length === 0) return;

						const group = document.createElement('div');
						group.className = 'network-group';
						// Open the first network group by default
						if (index === 0) {
							group.classList.add('is-open');
						}

						const networkLogo = getIconUrl('network', network);

						// Construct Header
						const header = document.createElement('div');
						header.className = 'network-header';
						header.innerHTML = `
							<div class="network-title">
								<img src="${networkLogo}" alt="${network}" class="network-logo" onerror="this.style.display='none'">
								${network}
							</div>
							<svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
						`;
						
						// Toggle accordion state
						header.addEventListener('click', () => {
							const isOpen = group.classList.contains('is-open');
							// Close all others
							document.querySelectorAll('.network-group').forEach(g => g.classList.remove('is-open'));
							// Toggle current
							if (!isOpen) {
								group.classList.add('is-open');
							}
						});

						// Construct Body & Cards
						const body = document.createElement('div');
						body.className = 'network-body';
						
						const grid = document.createElement('div');
						grid.className = 'currency-grid';

						currencies.forEach(curr => {
							const card = document.createElement('div');
							card.className = 'method-card';
							card.dataset.value = `${network}|${curr}`;
							
							const currLogo = getIconUrl('currency', curr);

							card.innerHTML = `
								<img src="${currLogo}" alt="${curr}" class="currency-logo" onerror="this.style.display='none'">
								<div class="method-currency">${curr}</div>
								<svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
							`;
							
							card.addEventListener('click', (e) => {
								e.stopPropagation(); // Prevent accordion toggle if clicking card
								document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
								card.classList.add('selected');
								selectedValue = card.dataset.value;
								selectedMethodText.textContent = `${network} / ${curr}`;
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
				.catch(err => {
					showError(i18n.network_error_loading_methods);
				});

			// 2. Submit and create order
			submitBtn.addEventListener('click', () => {
				if (!selectedValue) return;

				const [network, currency] = selectedValue.split('|');

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
						network: network,
						currency: currency
					})
				})
					.then(res => res.json())
					.then(data => {
						if (data.success && data.payment_url) {
							window.location.href = data.payment_url;
						} else {
							showError(data.error || i18n.failed_create_order);
						}
					})
					.catch(err => {
						showError(i18n.network_error_creating_order);
					});
			});
		});
	</script>

</body>

</html>
