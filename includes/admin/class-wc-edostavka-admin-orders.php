<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Edostavka_Admin_Orders {
	
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_delivery_points' ) );
	}
	
	public function register_metabox() {
		add_meta_box(
			'wc_edostavka',
			'СДЕК ПВЗ',
			array( $this, 'metabox_content' ),
			'shop_order',
			'side',
			'default'
		);
	}
	
	public function metabox_content( $post ) {
		$order = wc_get_order( $post );
		$points = WC_Edostavka::get_delivery_points( $order->billing_state );
		$shipping_method = WC_Edostavka::get_order_shipping_method( $order );
		$is_stock = WC_Edostavka::wc_edostavka_delivery_tariffs_type( str_replace( 'edostavka_', '', $shipping_method ) ) == 'stock' ? true : false;
		$html = '<label for="delivery_point">Пункт выдачи</label><br />';
		if( sizeof( $points ) > 0 ) {
			$html .= '<select id="delivery_point" name="delivery_point">';
			foreach( $points as $point ) {
				$html .= sprintf('<option value="%s" %s>%s</option>', $point['Code'], selected( get_post_meta( $post->ID, '_delivery_point', true ), $point['Code'], false ), $point['Name'] );
			} 
			$html .= '</select>';
		} else {
			$html .= '<input type="hidden" id="delivery_point" name="delivery_point" value="0">';
			$html .= '<p>Нет пунктов выдачи заказов.</p>';
		}
		
		if( strpos( $shipping_method, 'edostavka_' ) === 0 && $is_stock ) echo $html;
	}
	
	public function save_delivery_points( $post_id ) {
		
		$order = wc_get_order( $post_id );
		$shipping_method = WC_Edostavka::get_order_shipping_method( $order );
		
		if ( isset( $_POST['delivery_point'] ) && strpos( $shipping_method, 'edostavka_' ) === 0 ) {
			$old = get_post_meta( $post_id, '_delivery_point', true );
			$new = $_POST['delivery_point'];
			if ( ( $new && $new != $old ) ) {
				update_post_meta( $post_id, '_delivery_point', $new );				
				$order->add_order_note( sprintf( __( 'Пункт выдачи заказа: %s' ), $new ) );
				$this->trigger_email_notification( $order );
			} elseif ( '' == $new && $old ) {
				delete_post_meta( $post_id, '_delivery_point', $old );
			}
		}
	}
	
	protected function trigger_email_notification( $order ) {
		$mailer       = WC()->mailer();
		$notification = $mailer->emails['WC_Email_Edostavka_Points'];
		$notification->trigger( $order );
	}
}
new WC_Edostavka_Admin_Orders();