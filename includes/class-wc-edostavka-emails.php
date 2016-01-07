<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Edostavka_Emails {

	public function __construct() {
		add_filter( 'woocommerce_email_classes', array( $this, 'emails' ) );
	}

	public function emails( $emails ) {
		if ( ! isset( $emails['WC_Email_Edostavka_Points'] ) ) {
			$emails['WC_Email_Edostavka_Points'] = include( 'emails/class-wc-email-edostavka-points.php' );
		}
		return $emails;
	}
}
new WC_Edostavka_Emails();