<?php
/*
Plugin Name: Integração | WC PoS - TeraWallet
Description: Plugin para permitir o uso de Cashback no Orquidário. Caso o WC-POS atualize, olhe o arquivo do plugin para colocar novamente o window.obpos
Author: Shirkit
Version: 1.0
*/

/*
Colocar de novo o
`window.obpos = oW;`
logo antes do
`function aW(e,t){`
*/

/*
 * Allow to search customers by CPF and CNPJ in WC-POS Register page
 */
function ob_woocommerce_rest_customer_query_custom($args, $request) {
	$referer = $request->get_header( 'referer' );
	if ( strpos( $referer, 'point-of-sale' ) === false ) {
		return $args;
	}

	$meta_query = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array();
	if ( array_key_exists( 'search', $request->get_params() ) ) {
		array_push(
			$meta_query[0],
			array(
				'key'     => 'billing_cpf',
				'value'   => isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '',
				'compare' => 'LIKE',
			),
			array(
				'key'     => 'billing_cnpj',
				'value'   => isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '',
				'compare' => 'LIKE',
			)
		);
	}

	$args['meta_query'] = $meta_query;

	return $args;
}
add_filter( "woocommerce_rest_customer_query", "ob_woocommerce_rest_customer_query_custom", 101, 2 );

function ob_woo_wallet_form_order_cashback_amount($cashback_amount, $order_id) {
	$codes = WC()->order_factory->get_order($order_id)->get_coupon_codes();
	foreach ($codes as $code) {
		if ($code == 'pos_discount_member_discount') {
			print_r($code);
		}
	}
}
//add_filter('woo_wallet_form_order_cashback_amount', "ob_woo_wallet_form_order_cashback_amount", 10, 2);

/**
 * Prevents an order when user has insuficient funds for cashback
 */
function ob_woocommerce_rest_pre_insert_shop_order_object($order, $request, $creating) {
	if ($order->get_meta('wc_pos_order_type', true) == 'POS') {
		foreach ( $request['coupon_lines'] as $item ) {
			if ( is_array( $item ) && empty( $item['id'] ) && !empty( $item['code'] ) ) {
				if ($item['code'] == 'pos_discount_member_discount') {
					if (floatval($item['discount']) > floatval(WooWallet::instance()->wallet->get_wallet_balance($order->get_user_id()))) {
						return new WP_Error( 'woocommerce_api_user_cannot_create_order', 'Usuário sem saldo suficiente para realizar cashback', 401 );
					}
				}
			}
		}
	}
	return $order;
}
add_filter('woocommerce_rest_pre_insert_shop_order_object', 'ob_woocommerce_rest_pre_insert_shop_order_object', 10, 3);

/**
 * Process new orders coming from POS
 */
function ob_woocommerce_pos_new_order($order_id) {
	$order = WC()->order_factory->get_order($order_id);
	foreach ($order->get_coupons() as $code) {
		if ($code->get_code() == 'pos_discount_member_discount') {
			WooWallet::instance()->wallet->debit($order->get_user_id(), $code->get_discount(), 'Resgate de cashback p/ pedido #' . $order_id);
		}
	}
}
add_action('woocommerce_pos_new_order', 'ob_woocommerce_pos_new_order');

/**
 * Enqueue register javascript to the Register screen
 */
function ob_wp_print_footer_scripts() {
	if ( is_pos() ) {
		wp_enqueue_script( 'wc-pos-before-main', plugin_dir_url( __FILE__ ) . '/assets/js/register.js' );
	}
}
add_action( 'wp_enqueue_scripts', 'ob_wp_print_footer_scripts');
add_action( 'wp_print_footer_scripts', 'ob_wp_print_footer_scripts');

/**
 * Prints user role as a class on <body>
 */
function ob_admin_body_class( $classes ) {
    global $current_user;
    foreach( $current_user->roles as $role )
        $classes .= ' role-' . $role;
    return trim( $classes );
}
add_filter( 'admin_body_class', 'ob_admin_body_class' );

/**
 * Allows empty username on Add New User page and auto-uncheck send email
 */
add_action('user_new_form', 'my_user_new_form', 10, 1);
function my_user_new_form($form_type) {
    ?>
    <script type="text/javascript">
				jQuery('#user_login').closest('tr').removeClass('form-required').hide().find('.description').remove();
        // Uncheck send new user email option by default
        <?php if (isset($form_type) && $form_type === 'add-new-user') : ?>
            jQuery('#send_user_notification').removeAttr('checked').closest('tr').hide();
        <?php endif; ?>
    </script>
    <?php
}

/**
 * Generates a username when creating a new user if it's empty
 */
function ob_sanitize_user($u1, $u2, $strict) {
	if (isset($_POST['action']) && $_POST['action'] == 'createuser') {
		if (empty($u1) && empty($u2)) {
			$u1 = wc_create_new_customer_username($_POST['email'], array('first_name' => $_POST['first_name'], 'last_name' => $_POST['last_name']));
			$_POST['user_login'] = $u1;
		}
	}
	return $u1;
}
add_filter( 'sanitize_user', 'ob_sanitize_user', 10, 3);
?>
