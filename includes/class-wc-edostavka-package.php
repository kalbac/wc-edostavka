<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Edostavka_Package {
	
	protected $package = array();
	protected $minimum_height = 10;
	protected $minimum_width = 7;
	protected $minimum_length = 5;
	protected $minimum_weight = 0.1;
	
	public function __construct( $package = array() ) {
		$this->package = $package;
	}

	
	public function set_minimum_height( $minimum_height ) {
		$this->minimum_height = $minimum_height;
	}

	
	public function set_minimum_width( $minimum_width ) {
		$this->minimum_width = $minimum_width;
	}

	
	public function set_minimum_length( $minimum_length ) {
		$this->minimum_length = $minimum_length;
	}
	
	public function set_minimum_weight( $minimum_weight ) {
		$this->minimum_weight = $minimum_weight;
	}

	
	private function fix_format( $value ) {
		$value = str_replace( ',', '.', $value );
		return $value;
	}

	
	protected function get_package_data() {
		$count  = 0;
		$height = array();
		$width  = array();
		$length = array();
		$weight = array();

		foreach ( $this->package['contents'] as $item_id => $values ) {
			$product = $values['data'];
			$qty     = $values['quantity'];
			if ( $qty > 0 && $product->needs_shipping() ) {

				$_height = wc_get_dimension( $this->fix_format( $product->height ), 'cm' );
				$_width  = wc_get_dimension( $this->fix_format( $product->width ), 'cm' );
				$_length = wc_get_dimension( $this->fix_format( $product->length ), 'cm' );
				$_weight = wc_get_weight( $this->fix_format( $product->weight ), 'kg' );

				$height[ $count ] = $_height;
				$width[ $count ]  = $_width;
				$length[ $count ] = $_length;
				$weight[ $count ] = ( ! empty( $_weight ) && $_weight > 0 ) ? $_weight : $this->minimum_weight;
				if ( $qty > 1 ) {
					$n = $count;
					for ( $i = 0; $i < $qty; $i++ ) {
						$height[ $n ] = $_height;
						$width[ $n ]  = $_width;
						$length[ $n ] = $_length;
						$weight[ $n ] = ( ! empty( $_weight ) && $_weight > 0 ) ? $_weight : $this->minimum_weight;
						$n++;
					}
					$count = $n;
				}
				$count++;
			}
		}
		return array(
			'height' => array_values( $height ),
			'length' => array_values( $length ),
			'width'  => array_values( $width ),
			'weight' => array_sum( $weight )
		);
	}
	
	protected function cubage_total( $height, $width, $length ) {

		$all   = array();
		$total = 0;
		for ( $i = 0; $i < count( $height ); $i++ ) {
			$all[ $i ] = $height[ $i ] * $width[ $i ] * $length[ $i ];
		}
		foreach ( $all as $value ) {
			$total += $value;
		}
		return $total;
	}
	
	protected function get_max_values( $height, $width, $length ) {
		$find = array(
			'height' => max( $height ),
			'width'  => max( $width ),
			'length' => max( $length ),
		);
		return $find;
	}
	
	protected function calculate_root( $height, $width, $length, $max_values ) {
		$cubage_total = $this->cubage_total( $height, $width, $length );
		$root        = 0;
		if ( 0 != $cubage_total ) {
			$division = $cubage_total / max( $max_values );
			$root = round( sqrt( $division ), 1 );
		}
		return $root;
	}

	protected function get_cubage( $height, $width, $length ) {
		$cubage     = array();
		$max_values = $this->get_max_values( $height, $width, $length );
		$root       = $this->calculate_root( $height, $width, $length, $max_values );
		$greatest   = array_search( max( $max_values ), $max_values );
		switch ( $greatest ) {
			case 'height':
				$cubage = array(
					'height' => max( $height ),
					'width'  => $root,
					'length' => $root,
				);
				break;
			case 'width':
				$cubage = array(
					'height' => $root,
					'width'  => max( $width ),
					'length' => $root,
				);
				break;
			case 'length':
				$cubage = array(
					'height' => $root,
					'width'  => $root,
					'length' => max( $length ),
				);
				break;
			default:
				$cubage = array(
					'height' => 0,
					'width'  => 0,
					'length' => 0,
				);
				break;
		}
		return $cubage;
	}

	public function get_data() {
		$data = apply_filters( 'woocommerce_edostavka_default_package', $this->get_package_data() );
		$cubage = $this->get_cubage( $data['height'], $data['width'], $data['length'] );
		$height = ( $cubage['height'] < $this->minimum_height ) ? $this->minimum_height : $cubage['height'];
		$width  = ( $cubage['width'] < $this->minimum_width ) ? $this->minimum_width : $cubage['width'];
		$length = ( $cubage['length'] < $this->minimum_length ) ? $this->minimum_length : $cubage['length'];
		return array(
			'height' => $height,
			'length' => $length,
			'width'  => $width,
			'weight' => $data['weight'],
		);
	}
	
	public function get_goods() {
		$goods = array();
		$count = 0;
		
		foreach ( $this->package['contents'] as $item_id => $values ) {
			$product = $values['data'];
			$qty     = $values['quantity'];
			if ( $qty > 0 && $product->needs_shipping() ) {

				$_height = wc_get_dimension( $this->fix_format( $product->height ), 'cm' );
				$_width  = wc_get_dimension( $this->fix_format( $product->width ), 'cm' );
				$_length = wc_get_dimension( $this->fix_format( $product->length ), 'cm' );
				$_weight = wc_get_weight( $this->fix_format( $product->weight ), 'kg' );
				
				$goods[ $count ]['height'] = ( ! empty( $_height ) && $_height > 0 ) ? $_height : $this->minimum_height;
				$goods[ $count ]['width'] = ( ! empty( $_width ) && $_width > 0 ) ? $_width : $this->minimum_width;
				$goods[ $count ]['length'] = ( ! empty( $_length ) && $_length > 0 ) ? $_length : $this->minimum_length;
				$goods[ $count ]['weight'] = ( ! empty( $_weight ) && $_weight > 0 ) ? $_weight : $this->minimum_weight;
				
				if ( $qty > 1 ) {
					$n = $count;
					for ( $i = 0; $i < $qty; $i++ ) {
						$goods[ $n ]['height'] = ( ! empty( $_height ) && $_height > 0 ) ? $_height : $this->minimum_height;
						$goods[ $n ]['width'] = ( ! empty( $_width ) && $_width > 0 ) ? $_width : $this->minimum_width;
						$goods[ $n ]['length'] = ( ! empty( $_length ) && $_length > 0 ) ? $_length : $this->minimum_length;
						$goods[ $n ]['weight'] = ( ! empty( $_weight ) && $_weight > 0 ) ? $_weight : $this->minimum_weight;
						$n++;
					}
					$count = $n;
				}
				$count++;
			}
		}		
		
		return $goods;
	}
}