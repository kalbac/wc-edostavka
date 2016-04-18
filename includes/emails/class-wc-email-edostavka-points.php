<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Email_Edostavka_Points extends WC_Email {

	public function __construct() {
		$this->id               = 'edostavka_points';
		$this->title            = __( 'СДЕК ПВЗ' );
		$this->enabled          = 'yes';
		$this->description      = __( 'Этот email отправляется когда пользователь оформил и оплатил доставку через СДЕК.' );
		$this->heading          = __( 'Ваш заказ отправлен' );
		$this->subject          = __( '[{blogname}] Ваш заказ {order_number} отправлен через службу доставки СДЕК' );
		$this->message          = __( 'Ваш заказ с сайта {blogname} был отправлен через службу доставки СДЕК. Ниже указана контактная информация пункта выдачи заказов:' );
		$this->delivery_points  = $this->get_option( 'delivery_points', $this->message );
		$this->template_html    = 'emails/edostavka-delivery-points.php';
		$this->template_plain   = 'emails/plain/edostavka-delivery-points.php';

		parent::__construct();
		$this->template_base = WC_Edostavka::get_templates_path();
		
		add_filter( 'woocommerce_resend_order_emails_available', array( $this, 'email_available_edostavka_points' ) );
	}
	
	public function email_available_edostavka_points( $emails ) {
		if( ! in_array( $this->id, $emails ) ) array_push(
			$emails,
			$this->id
		);
		
		return $emails;
	}
	
	public function init_form_fields() {
		$this->form_fields = array(
			'subject' => array(
				'title'       => __( 'Тема' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Тема сообщения. Оставте пустым что бы использовать тему по умолчнию: <code>%s</code>.' ), $this->subject ),
				'placeholder' => '',
				'default'     => ''
			),
			'heading' => array(
				'title'       => __( 'Заголовок сообщения' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Текст который будет отображатся в заголовке сообщения. Оставте пустым что бы использовать заголовок по умочанию: <code>%s</code>.' ), $this->heading ),
				'placeholder' => '',
				'default'     => ''
			),
			'tracking_message' => array(
				'title'       => __( 'Контент' ),
				'type'        => 'textarea',
				'description' => sprintf( __( 'Основной текст письма. Оставьте пустым если хотите использовать стандартный текст: <code>%s</code>.' ), $this->message ),
				'placeholder' => '',
				'default'     => ''
			),
			'email_type' => array(
				'title'       => __( 'Тип сообщения' ),
				'type'        => 'select',
				'description' => __( 'Укажите в каком формате отправлять сообщение.' ),
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => array(
					'plain'     => __( 'Обычный текст' ),
					'html'      => __( 'HTML' ),
					'multipart' => __( 'Multipart' ),
				)
			)
		);
	}
	
	public function get_message() {
		return apply_filters( 'woocommerce_edostavka_email_tracking_message', $this->format_string( $this->delivery_points ), $this->object );
	}
	
	public function trigger( $order ) {
		if ( is_numeric( $order ) ) {
			$this->object	= new WC_Order( absint( $order ) );
		} elseif( $order instanceof WC_Order ) {
			$this->object    = $order;
		}
		
		$this->recipient = $this->object->billing_email;
		$this->find[]    = '{order_number}';
		$this->replace[] = $this->object->get_order_number();
		$this->find[]    = '{date}';
		$this->replace[] = date_i18n( woocommerce_date_format(), time() );
		
		if ( ! $this->get_recipient() ) {
			return;
		}
		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}
	
	public function get_content_html() {
		ob_start();
		woocommerce_get_template( $this->template_html, array(
			'order'            => $this->object,
			'email_heading'    => $this->get_heading(),
			'message' 		   => $this->get_message(),
			'points'		   => WC_Edostavka::get_delivery_points( $this->object->billing_state_id ),
			'sent_to_admin'    => false,
			'plain_text'       => false
		), '', $this->template_base );
		return ob_get_clean();
	}
	
	public function get_content_plain() {
		ob_start();
		woocommerce_get_template( $this->template_plain, array(
			'order'            => $this->object,
			'email_heading'    => $this->get_heading(),
			'message' 		   => $this->get_message(),
			'points'		   => WC_Edostavka::get_delivery_points( $this->object->billing_state_id ),
			'sent_to_admin'    => false,
			'plain_text'       => true
		), '', $this->template_base );
		return ob_get_clean();
	}
}
return new WC_Email_Edostavka_Points();