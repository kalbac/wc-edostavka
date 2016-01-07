<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php do_action( 'woocommerce_email_header', $email_heading ); ?>

<?php echo wptexturize( wpautop( $message ) ); ?>

<?php do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text ); ?>

<h2>Пункты выдачи</h2>

<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;" border="1" bordercolor="#eee">
	<thead>
		<tr>
			<th scope="col" style="text-align:left; border: 1px solid #eee;">Название</th>
			<th scope="col" style="text-align:left; border: 1px solid #eee;">Адрес</th>
			<th scope="col" style="text-align:left; border: 1px solid #eee;">Режим работы</th>
			<th scope="col" style="text-align:left; border: 1px solid #eee;">Телефон</th>
		</tr>
	</thead>
	<tbody>
	
		<?php 
		
		$delivery_point = get_post_meta( $order->id, '_delivery_point', true );
		
		if( ! empty( $delivery_point ) && isset( $points[ $delivery_point ] ) ) : ?>
		<?php 
			$yaMap = '';
			$yaUrl = 'https://maps.yandex.ru/';
			if( ! empty( $points[ $delivery_point ]['coordX'] ) && ! empty( $points[ $delivery_point ]['coordY'] ) ) {
				$yaMap = sprintf(__('<br /><a href="%s"><small>Посмотеть на карте</small></a>'), add_query_arg( array( 'z' => 16, 'll' => esc_attr( $points[ $delivery_point ]['coordX'] ) . '%2C' . esc_attr( $points[ $delivery_point ]['coordY'] ) ), $yaUrl ) );
			}
			
			?>
			<tr>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $points[ $delivery_point ]['Name']; ?></th>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $points[ $delivery_point ]['Address']; ?></td>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $points[ $delivery_point ]['WorkTime']; ?></td>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $points[ $delivery_point ]['Phone']; ?></td>
			</tr>
		<?php else : ?>	
		<?php foreach ( $points as $point ) : ?>
			<?php 
			$yaMap = '';
			$yaUrl = 'https://maps.yandex.ru/';
			if( ! empty( $point['coordX'] ) && ! empty( $point['coordY'] ) ) {
				$yaMap = sprintf(__('<br /><a href="%s"><small>Посмотеть на карте</small></a>'), add_query_arg( array( 'z' => 16, 'll' => esc_attr( $point['coordX'] ) . '%2C' . esc_attr( $point['coordY'] ) ), $yaUrl ) );
			}
			
			?>
			<tr>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $point['Name']; ?></th>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $point['Address'] . $yaMap; ?></td>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $point['WorkTime']; ?></td>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $point['Phone']; ?></td>
			</tr>
		<?php endforeach; ?>
		<?php endif;?>
	</tbody>
</table>

<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text ); ?>

<?php do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text ); ?>

<h2><?php _e( 'Информация о клиенте:' ); ?></h2>

<?php if ( $order->billing_email ) : ?>
	<p><strong>Email:</strong> <?php echo $order->billing_email; ?></p>
<?php endif; ?>

<?php if ( $order->billing_phone ) : ?>
	<p><strong>Телефон:</strong> <?php echo $order->billing_phone; ?></p>
<?php endif; ?>

<?php woocommerce_get_template( 'emails/email-addresses.php', array( 'order' => $order ) ); ?>

<?php do_action( 'woocommerce_email_footer' ); ?>