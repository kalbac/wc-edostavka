<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
echo $email_heading . "\n\n";
echo $message . "\n\n";
echo "****************************************************\n\n";
do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text );
echo sprintf( __( 'Номер заказа: %s' ), $order->get_order_number() ) . "\n";
echo sprintf( __( 'Дата: %s' ), date_i18n( wc_date_format(), strtotime( $order->order_date ) ) ) . "\n";
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text );

echo "----------\n\n";
foreach ( $points as $point ) {
	echo $point['Name'] . "\t " . $point['Address'] . "\n";
}
echo "\n****************************************************\n\n";
do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text );
echo __( 'Информация о клиенте:' ) . "\n\n";
if ( $order->billing_email ) {
	echo __( 'Email:' ) . ' ' . $order->billing_email . "\n";
}
if ( $order->billing_phone ) {
	echo __( 'Телефон:' ) . ' ' . $order->billing_phone . "\n";
}
woocommerce_get_template( 'emails/plain/email-addresses.php', array( 'order' => $order ) );
echo "\n****************************************************\n\n";
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );