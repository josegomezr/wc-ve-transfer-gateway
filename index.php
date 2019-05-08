<?php
/*
Plugin Name: Pagos via Transferencia de fondos en Venezuela
Description: Pasarela fuera de linea para administrar pagos recibidos via Pago Móvil (P2P) o Transferencia de fondos.
Author: José D. Gómez R.
Author URI: https://josegomezr.com.ve
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Custom Payment Gateway.
 *
 * Provides a Custom Payment Gateway, mainly for testing purposes.
 */
add_action('plugins_loaded', 'init_custom_gateway_class');
function init_custom_gateway_class(){
    require dirname( __FILE__ ) . '/gateway_ve_base.php';
    require dirname( __FILE__ ) . '/gateway_transfer_ve.php';
    require dirname( __FILE__ ) . '/gateway_p2p_ve.php';
}

add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway_class' );
function add_custom_gateway_class( $methods ) {
    $methods[] = 'GatewayTransferVE'; 
    $methods[] = 'GatewayP2PVE'; 
    return $methods;
}

// add_action('woocommerce_checkout_process', 'process_custom_payment');
function process_custom_payment(){

    if($_POST['payment_method'] != 'custom')
        return;

    if( !isset($_POST['mobile']) || empty($_POST['mobile']) )
        wc_add_notice( __( 'Please add your mobile number', 'wxxx' ), 'error' );


    if( !isset($_POST['transaction']) || empty($_POST['transaction']) )
        wc_add_notice( __( 'Please add your transaction ID', 'wxxx' ), 'error' );

}

/**
 * Update the order meta with field value
 */
// add_action( 'woocommerce_checkout_update_order_meta', 'custom_payment_update_order_meta' );
function custom_payment_update_order_meta( $order_id ) {

    if($_POST['payment_method'] != 'custom')
        return;

    // echo "<pre>";
    // print_r($_POST);
    // echo "</pre>";
    // exit();

    update_post_meta( $order_id, 'mobile', $_POST['mobile'] );
    update_post_meta( $order_id, 'transaction', $_POST['transaction'] );
}

/**
 * Display field value on the order edit page
 */
// add_action( 'woocommerce_admin_order_data_after_billing_address', 'custom_checkout_field_display_admin_order_meta', 10, 1 );
function custom_checkout_field_display_admin_order_meta($order){
    $method = get_post_meta( $order->id, '_payment_method', true );
    if($method != 'custom')
        return;

    $mobile = get_post_meta( $order->id, 'mobile', true );
    $transaction = get_post_meta( $order->id, 'transaction', true );

    echo '<p><strong>'.__( 'Mobile Number' ).':</strong> ' . $mobile . '</p>';
    echo '<p><strong>'.__( 'Transaction ID').':</strong> ' . $transaction . '</p>';
}