<?php
/*
Plugin Name: Integração | WC PoS - TeraWallet
Description: Plugin para permitir o uso de Cashback no Orquidário. Caso o WC-POS atualize, olhe o arquivo do plugin para colocar novamente o window.obpos
Author: Shirkit
Version: 1.1
*/

/*
Colocar de novo o
`window.obpos = oW;`
logo antes do
`function aW(e,t){`
*/

/*

Cliente

Valor mínimo da compra para gerar Cashback: R$100

4% Dinheiro / PIX
2% Débito

0 - 1% - Cliente
300 - 2% - Cliente Nivel 1
600 - 3% - Cliente Nivel 2
900 - 4% - Cliente Nivel 3
1300 - 5% - Cliente Nivel 4
1700 - 6% - Cliente Nivel 5
2300 - 7% - Cliente Nivel 6
2800 - 8% - Cliente Nivel 7
3500 - 9% - Cliente Nivel 8
4300 - 10% - Cliente Nivel 9

---------------------

Revenda

Valor mínimo da compra para gerar Cashback: R$300

3% Dinheiro / PIX
1,5% Débito

0,5% - 1000 - Revendedor Nivel 1
1% - 2000 - Revendedor Nivel 2
1,5% - 4000 - Revendedor Nivel 3
2% - 8000 - Revendedor Nivel 4	

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
					if (round(floatval($item['discount']), 2) > round(floatval(WooWallet::instance()->wallet->get_wallet_balance($order->get_user_id(), 'real')), 2) ) {
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
 * We set the % cashback here since this is the earlier function before Woo_Wallet_Cashback::calculate_cashback_form_order gets called
 */
function ob_process_woo_wallet_general_cashback($process, $order) {
	$role = new WC_Customer($order->get_customer_id)->get_role('edit');	
	$cashback = Woo_Wallet_Cashback::$global_cashbak_amount;
	
	switch($role) {
		case 'reseller1':
			$cashback = 0.5;
			break;
		case 'reseller2':
			$cashback = 1;
			break;
		case 'reseller3':
			$cashback = 1.5;
			break;
		case 'reseller4':
			$cashback = 2;
			break;
		
		case 'customer':
			$cashback = 1;
			break;
		case 'customer1':
			$cashback = 2;
			break;
		case 'customer2':
			$cashback = 3;
			break;
		case 'customer3':
			$cashback = 4;
			break;
		case 'customer4':
			$cashback = 5;
			break;
		case 'customer5':
			$cashback = 6;
			break;
		case 'customer6':
			$cashback = 7;
			break;
		case 'customer7':
			$cashback = 8;
			break;
		case 'customer8':
			$cashback = 9;
			break;
		case 'customer9':
			$cashback = 10;
			break;
		
		case 'orchider':
			$cashback = 10;
			break;
	}
	
	$method = $order->get_payment_method('edit');
	
	/*
	pos_cash = Dinheiro
	pos_chip_and_pin = Crédito
	pos_chip_and_pin_2 = Débito
	pos_chip_and_pin_3 = Outros
	pos_chip_and_pin_4 = PIX
	*/
	
	if (strpos($role, 'customer') !== false || strpos($role, 'orchider') !== false) {
		switch($method) {
			case 'pos_cash':
			case 'pos_chip_and_pin_4':
				$cashback += 4;
				break;
			case 'pos_chip_and_pin_2':
				$cashback += 2;
		}
	} else if (strpos($role, 'reseller') !== false) {
		switch($method) {
			case 'pos_cash':
			case 'pos_chip_and_pin_4':
				$cashback += 3;
				break;
			case 'pos_chip_and_pin_2':
				$cashback += 1.5;
		}
	}
	
	Woo_Wallet_Cashback::$global_cashbak_amount = $cashback;

	return $process;
}
add_filter( 'process_woo_wallet_general_cashback', 'ob_process_woo_wallet_general_cashback', 10, 2 );

/**
 * Process new orders coming from POS.
 * Since we only process cashback after the order migrates from Processing -> Completed, we can safely upgrade the tier after order paid
 */
function ob_woocommerce_pos_new_order($order_id) {
	$order = WC()->order_factory->get_order($order_id);
	foreach ($order->get_coupons() as $code) {
		if ($code->get_code() == 'pos_discount_member_discount') {
			WooWallet::instance()->wallet->debit($order->get_user_id(), $code->get_discount(), 'Resgate de cashback p/ pedido #' . $order_id);
		}
	}
	
	$customer = new WC_Customer($order->get_user_id());
	$total_orders = get_user_orders_total(get_user_id($order->get_user_id()));
	$role = $customer->get_role('edit');
	
	if (is_array($role))
		$role = $role[0];
	
	$id = substr($role, strlen($role), 1);
	if (is_numeric($id)) {
		$id = intval($id);
		$role = substr($role, 0, strlen($role) - 1); 
	} else
		$id = 0;
	
	$old_id = $id;
	
	if ($id < 9 && strpos($role, 'customer') !== false) {
		if ($total_orders > 4300)
			$id = 9;
		else if ($total_orders > 3500 && $id != 8)
			$id = 8;
		else if ($total_orders > 2800 && $id != 7)
			$id = 7;
		else if ($total_orders > 2300 && $id != 6)
			$id = 6;
		else if ($total_orders > 1700 && $id != 5)
			$id = 5;
		else if ($total_orders > 1300 && $id != 4)
			$id = 4;
		else if ($total_orders > 900 && $id != 3)
			$id = 3;
		else if ($total_orders > 600 && $id != 2)
			$id = 2;
		else if ($total_orders > 300 && $id != 1)
			$id = 1;
	} else if ($id < 4 && strpos($role, 'reseller') !== false) {
		if ($total_orders > 8000)
			$id = 4;
		else if ($total_orders > 4000 && $id != 3)
			$id = 3;
		else if ($total_orders > 2000 && $id != 2)
			$id = 2;
		else if ($total_orders > 1000 && $id != 1)
			$id = 1;
	}
	
	if ($id != $Old_id) {
		$customer->set_role($role . $id);
	}
	
}
add_action('woocommerce_pos_new_order', 'ob_woocommerce_pos_new_order');

function get_user_orders_total($user_id) {
    // Use other args to filter more
    $args = array(
        'customer_id' => $user_id,
		'post_status' => array('processing','completed')
    );
    // call WC API
    $orders = wc_get_orders($args);

    if (empty($orders) || !is_array($orders)) {
        return 0;
    }

    // One implementation of how to sum up all the totals
    $total = array_reduce($orders, function ($carry, $order) {
		$carry += (float)$order->get_total();

        return $carry;
    }, 0.0);

    return $total;
}

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
				jQuery('#email').closest('tr').removeClass('form-required').find('.description').remove();
				jQuery('#role').val('customer');
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

add_action('user_profile_update_errors', 'my_user_profile_update_errors', 10, 3 );
function my_user_profile_update_errors($errors, $update, $user) {
    $errors->remove('empty_email');
}

/**
 * Allows shop managers to assign custom roles
 */
function ob_woocommerce_shop_manager_editable_roles( $roles ) {
    $roles[] = 'reseller';
	$roles[] = 'reseller1';
	$roles[] = 'reseller2';
	$roles[] = 'reseller3';
	$roles[] = 'reseller4';
	$roles[] = 'orchider';
	$roles[] = 'customer1';
	$roles[] = 'customer2';
	$roles[] = 'customer3';
	$roles[] = 'customer4';
	$roles[] = 'customer5';
	$roles[] = 'customer6';
	$roles[] = 'customer7';
	$roles[] = 'customer8';
	$roles[] = 'customer9';
    return $roles;
}
add_filter( 'woocommerce_shop_manager_editable_roles', 'ob_woocommerce_shop_manager_editable_roles' );

function custom_user_profile_fields($user) {
  ?>
    <table class="form-table">
        <tr>
            <th><label for="billing_phone">Telefone</label></th>
            <td>
                <input type="text" class="regular-text" name="billing_phone" value="<?php echo esc_attr( get_the_author_meta( 'billing_phone', $user->ID ) ); ?>" id="billing_phone" /><br />
            </td>
        </tr>
    </table>
  <?php
}
add_action( "user_new_form", "custom_user_profile_fields" );

function save_custom_user_profile_fields($user_id) {
    # again do this only if you can
    if(!current_user_can('manage_options'))
        return false;

    # save my custom field
    update_usermeta($user_id, 'billing_phone', $_POST['billing_phone']);
}
add_action('user_register', 'save_custom_user_profile_fields');


?>