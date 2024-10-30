<?php 

add_filter( 'woocommerce_package_rates', 'my_hide_shipping_when_free_is_available_ithink', 100 );

function my_hide_shipping_when_free_is_available_ithink( $rates ) {

   // $cart_weight = WC()->cart->cart_contents_weight; // Cart total weight
	$cart_weight = WC()->cart->get_subtotal();
    $freeshipping = get_option('freeshipping_totalvalue');
    $free = array();
    foreach ( $rates as $rate_id => $rate ) {
        if ( $rate->method_id =='free_shipping' && $cart_weight > $freeshipping ) { // <= your weight condition
            $free[ $rate_id ] = $rate;
            break;
        }
		//echo '<pre>'; print_r($cart_weight); echo '</pre>';
		//echo '<pre>'; print_r($rate->method_id); echo '</pre>';
		//echo '<pre>'; print_r($freeshipping); echo '</pre>';
    }
    return ! empty( $free ) ? $free : $rates;
}