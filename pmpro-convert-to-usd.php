<?php
/**
 * Plugin Name: PMPro Currency Converter
 * Description: Convert all non USD currencies to USD.
 * Plugin URI: http...
 * Author: Author
 * Author URI: http...
 * Version: 1.0
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: Text Domain
 * Domain Path: Domain Path
 * Network: false
 */

defined( 'ABSPATH' ) or exit;

/**
 * Change currency to USD before checking out.
 * @since 1.0
 */
function pmpro_change_to_usd_before_checkout(){
	global $pmpro_level, $pmpro_currency;

	// Bail if already in USD
	if( $pmpro_currency == 'USD' ){
		return;
	}

	// Set currency to USD
	$pmpro_currency = 'USD';

	if( $pmpro_level->initial_payment !== '0.00' ){
		$pmpro_level->initial_payment = pmpro_convert_amount_usd( $pmpro_level->initial_payment );
	}

	if( $pmpro_level->billing_amount !== '0.00' ){
		$pmpro_level->billing_amount = pmpro_convert_amount_usd( $pmpro_level->billing_amount );
	}
	
}

add_action( 'pmpro_checkout_before_processing', 'pmpro_change_to_usd_before_checkout' );

/**
 * Change level cost text to show USD values next to locale values.
 * @since 1.0
 */
function pmpro_show_usd_cost_text_value( $cost, $level ){

	global $pmpro_currency;

	// Bail if already in USD
	if( $pmpro_currency == 'USD' ){
		return $cost;
	}

	$initial_payment = $level->initial_payment;
	$billing_amount = $level->billing_amount;

	if( $initial_payment !== '0.00' ){
		$initial_payment_usd = round( pmpro_convert_amount_usd( $initial_payment ), 2 );
		$cost = str_replace( $initial_payment, $initial_payment . ' ( $' . $initial_payment_usd . ' approx. ) ', $cost );
	}

	if( $billing_amount !== '0.00' ){
		$billing_payment_usd = round( pmpro_convert_amount_usd( $billing_amount ), 2 );
		$cost = str_replace( $billing_amount, $billing_amount . ' ( $' . $billing_payment_usd . ' approx. ) ', $cost );
	}


	return $cost;
}

add_filter( 'pmpro_level_cost_text', 'pmpro_show_usd_cost_text_value', 20, 2 );

/**
 * Function that converts the amount to USD
 * @uses https://api.fixer.io
 * @since 1.0
 */
function pmpro_convert_amount_usd( $amount ){

	$amount = floatval( $amount );

	delete_transient('pmpro_usd_value');

	if( false === ( $conversion_rate = get_transient( 'pmpro_usd_value' ) ) ){

		$base_currency = pmpro_getOption( 'currency' );
		$currency = 'USD';

		// lets build the URL
		$url = 'https://api.fixer.io/latest?base=' . $base_currency . '&symbols=' . $currency;

		$response = wp_remote_get( $url );
		$response_code = wp_remote_retrieve_response_code( $response );

		if( is_array( $response ) && $response_code == 200 ){
			$body = $response['body'];
		}

		// the data from the API
		$conversion_data = json_decode( $body );
		$conversion_rate = $conversion_data->rates->USD;

		// cache it for 12 hours.
		set_transient( 'pmpro_usd_value', $conversion_rate, 12 * HOUR_IN_SECONDS );
	}

	return $amount * $conversion_rate;
}
