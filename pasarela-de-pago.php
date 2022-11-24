<?php
/*
Plugin Name: pasarela-de-pago
Plugin URI: https://github.com/JoseSCH/CGateway
Description: Pasarela de pago
Author: Luis Alejandro Ramos Reyes
Version: 1.0.0
Author URI: https://github.com/LuisAlejandroRamos
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

//seguridad
defined('ABSPATH') or die('No se admiten trampas');

add_filter( 'woocommerce_payment_gateways', 'CG_Agregar_Pasarela' );
function CG_Agregar_Pasarela( $gateways ) {
	$gateways[] = 'CG_CHEPE_Gateway';
	return $gateways;
}

add_action( 'plugins_loaded', 'CG_init_pasarela_class' );
function CG_init_pasarela_class() {

    class CG_CHEPE_Gateway extends WC_Payment_Gateway {

        public function __construct() {

            $this->id = 'chepe';
            $this->icon = ''; 
            $this->has_fields = true;
            $this->method_title = 'Pasarela de pago CG';
            $this->method_description = 'Bienvenido a esta pequeña y amiga pasarela';
        
           
            $this->supports = array(
                'products'
            );
        
            
            $this->init_form_fields();
        

            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
        
          
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
           
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            
        }

        public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Misha Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'Controla el título de lo que el usuario ve al momento de pagar.',
                    'default'     => 'Credit Card - Por Pasarela CG',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'La descripción que el cliente mira al momento de pagar',
                    'default'     => 'Paga con tu tarjeta de crédito de una manera tuani.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Pone al servicio el modo de prueba con sus respectivas API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Test Publishable Key',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'Test Private Key',
                    'type'        => 'password',
                ),
                'publishable_key' => array(
                    'title'       => 'Live Publishable Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'password'
                )
            );
        }

        public function payment_scripts() {


            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }
        
 
            if ( 'no' === $this->enabled ) {
                return;
            }
        
 
            if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
                return;
            }
        
           
            if ( ! $this->testmode && ! is_ssl() ) {
                return;
            }
        
           
            wp_enqueue_script( 'rigel_js', 'https://www.mishapayments.com/api/token.js' );
        
           
            wp_register_script( 'woocommerce_misha', plugins_url( 'rigel.js', __FILE__ ), array( 'jquery', 'rigel_js' ) );
        
        
            wp_localize_script( 'woocommerce_chepe', 'chepe_params', array(
                'publishableKey' => $this->publishable_key
            ) );
        
            wp_enqueue_script( 'woocommerce_chepe' );
        
        }

        public function payment_fields() {
 

            if ( $this->description ) {
   
                if ( $this->testmode ) {
                    $this->description .= 'El modo de prueba esta activado';
                    $this->description  = trim( $this->description );
                }
  
                echo wpautop( wp_kses_post( $this->description ) );
            }
         
            
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
         
           
            do_action( 'woocommerce_credit_card_form_start', $this->id );
         
           
            echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
                <input id="misha_ccNo" type="text" autocomplete="off">
                </div>
                <div class="form-row form-row-first">
                    <label>Expiry Date <span class="required">*</span></label>
                    <input id="misha_expdate" type="text" autocomplete="off" placeholder="MM / YY">
                </div>
                <div class="form-row form-row-last">
                    <label>Card Code (CVC) <span class="required">*</span></label>
                    <input id="misha_cvv" type="password" autocomplete="off" placeholder="CVC">
                </div>
                <div class="clear"></div>';
         
            do_action( 'woocommerce_credit_card_form_end', $this->id );
         
            echo '<div class="clear"></div></fieldset>';
         
        }

        public function validate_fields(){
 
            if( empty( $_POST[ 'billing_first_name' ]) ) {
                wc_add_notice(  'First name is required!', 'error' );
                return false;
            }
            return true;
         
        }

        public function process_payment( $order_id ) {
 
            global $woocommerce;
         
           
            $order = wc_get_order( $order_id );

            $response = wp_remote_post( 'https://eureka.free.beeceptor.com', $args );

            if( !is_wp_error( $response ) ) {
 
                $body = json_decode( $response['body'], true );
        
                
                if ( $body['response']['responseCode'] == 'APPROVED' ) {
        
                  
                   $order->payment_complete();
                   $order->reduce_order_stock();
        
                
                   $order->add_order_note( 'Hey, your order is paid! Thank you!', true );
        
                
                   $woocommerce->cart->empty_cart();
        
           
                   return array(
                       'result' => 'success',
                       'redirect' => $this->get_return_url( $order )
                   );
        
                } else {
                   wc_add_notice(  'Please try again.', 'error' );
                   return;
               }
        
           } else {
               wc_add_notice(  'Connection error.', 'error' );
               return;
           }
        
        }

    }

}


?>