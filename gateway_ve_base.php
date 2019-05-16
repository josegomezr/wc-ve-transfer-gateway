<?php

class GateawayVEBase extends WC_Gateway_BACS {
    public $id = 'base_ve';
    public $domain = 'woocommerce';

    public function __construct() {
        $this->_cached_exchange_rate = NULL;
        $this->icon               = apply_filters( 'woocommerce_'.$this->id.'_icon', '' );
        $this->has_fields         = true;
        $this->method_title       = __( 'Transferencia', 'woocommerce' );
        $this->method_description = __( 'Maneje pagos via transferencia bancaria en VE', 'woocommerce' );

        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.

        $this->enable_exchange_to_ves  = $this->get_option( 'enable_exchange_to_ves' );
        
        $this->exchange_rate        = $this->get_option( 'exchange_rate' );
        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->instructions = $this->get_option( 'instructions' );

        $this->account_details = get_option(
            'woocommerce_'.$this->id.'_accounts',
            array(
                array(
                    'account_document'   => $this->get_option( 'account_document' ),
                    'account_number' => $this->get_option( 'account_number' ),
                    'bank_name'      => $this->get_option( 'bank_name' ),
                ),
            )
        );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
        add_action( 'woocommerce_thankyou_'.$this->id, array( $this, 'thankyou_page' ) );

        // Customer Emails.
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        // add_action( 'woocommerce_admin_order_data_after_billing_address', array($this, 'checkout_order_meta'), 10, 1 );

    }

    public function fetch_exchange_rate()
    {
        /*$api_url ='https://bitven.com/assets/js/rates.js?random='.time();
        $args = array(
            'timeout'     => 5,
            'redirection' => 1,
            'httpversion' => '1.1',
            'user-agent'  => 'Webstore',
            'blocking'    => true,
            'compress'    => true,
            'decompress'  => true,
        );
        $response = wp_remote_get($api_url, $args);
        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        // $api_response['USD_TO_BSF_RATE'];*/
        return 1234;
    }

    public function get_exchange_rate()
    {
    	return $this->exchange_rate;
    }

    public function payment_fields(){
        

        if ( $description = $this->get_description() ) {
            echo wpautop( wptexturize( $description ) );
        }
        ?>
        <?php foreach($this->account_details as $account): ?>
        <table>
            <tr>
                <th>Banco</th>
                <td><?php echo $account['bank_name'] ?></td>
            </tr>
            <tr>
                <th>Cédula/RIF</th>
                <td><?php echo $account['account_document'] ?></td>
            </tr>
            <tr>
                <th><?php echo $this->account_number_name(); ?></th>
                <td><?php echo $account['account_number'] ?></td>
            </tr>
        </table>
        <hr>
        <?php endforeach; ?>
        <div id="custom_input">
        	<?php if ($this->has_to_exchange()): ?>
            <p class="form-row form-row-wide">
                <label for="<?php echo $this->id ?>_rate">Tasa:</label>
                <input type="text" id="<?php echo $this->id ?>_rate" name="<?php echo $this->id ?>_rate" class="input-text" readonly="readonly" value="<?php echo $this->get_exchange_rate() ?>">
            </p>
            <p class="form-row form-row-wide">
                <label>Total a transferir:</label>
                <input type="text" name="<?php echo $this->id ?>_total_ves" class="input-text" readonly="readonly" value="<?php echo WC()->cart->get_total(false) * $this->get_exchange_rate() ?>">
            </p>
        	<?php endif ?>
            <p class="form-row form-row-wide">
                <label for="mobile" class=""><?php echo $this->account_number_name() ?></label>
                <select class="select-box" name="<?php echo $this->id ?>_payment_account">
                    <option value="">Seleccione...</option>
                    <?php foreach($this->account_details as $account): ?>
                        <option value="<?php echo $account['account_number'] ?>">
                            <?php echo $account['bank_name'] ?> - <?php echo $account['account_number'] ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </p>
            <p class="form-row form-row-wide">
                <label for="<?php echo $this->id ?>_payment_reference" class=""><?php _e('Referencia', $this->domain); ?></label>
                <input type="text" class="" name="<?php echo $this->id ?>_payment_reference" id="<?php echo $this->id ?>_payment_reference" placeholder="" value="">
            </p>
        </div>
        <?php
    }


    public function init_form_fields() {

        $this->form_fields = array(
            'enabled'         => array(
                'title'   => __( 'Enable/Disable', 'woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Activar', 'woocommerce' ),
                'default' => 'no',
            ),
            'enable_exchange_to_ves'         => array(
                'title'   => 'Conversión a Bs.',
                'type'    => 'checkbox',
                'label'   => __( 'Activar', 'woocommerce' ),
                'default' => 'no',
                'description' => 'Esto convierte de US$ a VES automáticamente los totales de pedido.',
            ),
            'exchange_rate'	  => array(
                'title'       => 'Tasa de cambio',
                'type'   	  => 'text',
                'default' 	  => 1,
            ),
            'title'           => array(
                'title'       => __( 'Title', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                'default'     => __( 'Direct bank transfer', 'woocommerce' ),
                'desc_tip'    => true,
            ),
            'description'     => array(
                'title'       => __( 'Description', 'woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
                'default'     => __( 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'woocommerce' ),
                'desc_tip'    => true,
            ),
            'instructions'    => array(
                'title'       => __( 'Instructions', 'woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'account_details' => array(
                'type' => 'account_details',
            ),
        );
    }


    public function save_account_details() {

        $accounts = array();
        // phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification -- Nonce verification already handled in WC_Admin_Settings::save()
        if ( isset( $_POST[$this->id.'_account_document'] ) && isset( $_POST[$this->id.'_account_number'] ) && isset( $_POST[$this->id.'_bank_name'] ) ) {

            $account_document = wc_clean( wp_unslash( $_POST[$this->id.'_account_document'] ) );
            $account_number   = wc_clean( wp_unslash( $_POST[$this->id.'_account_number'] ) );
            $bank_name        = wc_clean( wp_unslash( $_POST[$this->id.'_bank_name'] ) );

            foreach ( $account_document as $i => $name ) {
                if ( ! isset( $account_document[ $i ] ) ) {
                    continue;
                }

                $accounts[] = array(
                    'account_document' => $account_document[ $i ],
                    'account_number'   => $account_number[ $i ],
                    'bank_name'        => $bank_name[ $i ],
                );
            }
        }
        // phpcs:enable

        update_option( 'woocommerce_'.$this->id.'_accounts', $accounts );
    }


    public function generate_account_details_html()
    {
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php esc_html_e( 'Account details:', 'woocommerce' ); ?></th>
            <td class="forminp" id="<?php echo $this->id ?>_accounts">
                <div class="wc_input_table_wrapper">
                    <table class="widefat wc_input_table sortable" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="sort">&nbsp;</th>
                                <th><?php esc_html_e( 'Banco', 'woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Cédula/RIF', 'woocommerce' ); ?></th>
                                <th><?php echo $this->account_number_name(); ?></th>
                            </tr>
                        </thead>
                        <tbody class="accounts">
                            <?php
                            $i = -1;
                            if ( $this->account_details ):
                            foreach ( $this->account_details as $account ):
                                $i++;
                            ?>
                                <tr class="account">
                                    <td class="sort"></td>
                                    <td>
                                    	<input type="text" value="<?php echo esc_attr( wp_unslash( $account['bank_name'] ) )?>" name="<?php echo $this->id; ?>_bank_name[<?php echo esc_attr( $i ) ?>]" />
                                    </td>
                                    <td>
                                    	<input type="text" value="<?php echo esc_attr( wp_unslash( $account['account_document'] ) )?>" name="<?php echo $this->id; ?>_account_document[<?php echo esc_attr( $i ) ?>]" />
                                    </td>
                                    <td>
                                    	<input type="text" value="<?php echo esc_attr( $account['account_number'] ) ?>" name="<?php echo $this->id ?>_account_number[<?php echo esc_attr( $i ) ?>]" /></td>
                                </tr>
                            <?php endforeach;
                            endif
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="7"><a href="#" class="add button">
                                	<?php esc_html_e( '+ Add account', 'woocommerce' ); ?></a>
                                	<a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected account(s)', 'woocommerce' ); ?></a>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <script type="text/javascript">
                    jQuery(function() {
                        jQuery('#<?php echo $this->id ?>_accounts').on( 'click', 'a.add', function(){

                            var size = jQuery('#<?php echo $this->id ?>_accounts').find('tbody .account').length;

                            jQuery('<tr class="account">\
                                    <td class="sort"></td>\
                                    <td><input type="text" name="<?php echo $this->id ?>_bank_name[' + size + ']" /></td>\
                                    <td><input type="text" name="<?php echo $this->id ?>_account_document[' + size + ']" /></td>\
                                    <td><input type="text" name="<?php echo $this->id ?>_account_number[' + size + ']" /></td>\
                                </tr>').appendTo('#<?php echo $this->id ?>_accounts table tbody');

                            return false;
                        });
                    });
                </script>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
    
    public function has_to_exchange()
    {
    	return $this->enable_exchange_to_ves == 'yes';
    }

    public function thankyou_page( $order_id ) {

		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( wp_kses_post( $this->instructions ) ) ) );
		}
		$this->bank_details( $order_id );

	}

    private function bank_details( $order_id = '' ) {

		if ( empty( $this->account_details ) ) {
			return;
		}

		// Get order and store in $order.
		$order = wc_get_order( $order_id );
		$key = $order->get_payment_method();

		$is_exchanged = $order->get_meta($key.'_is_exchanged');
		$account = $order->get_meta($key.'_account');
		$reference = $order->get_meta($key.'_reference');
		$rate = $order->get_meta($key.'_rate');
		$total_ves = $order->get_meta($key.'_total_ves');
		?>
		<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
			<li class="woocommerce-order-overview__payment-method method">
				<?php _e( $this->account_number_name(), 'woocommerce' ); ?>
				<strong><?php echo $account ?></strong>
			</li>
			<li class="woocommerce-order-overview__payment-method method">
				<?php _e( 'Referencia', 'woocommerce' ); ?>
				<strong><?php echo $reference ?></strong>
			</li>
			<?php if ($is_exchanged): ?>
			<li class="woocommerce-order-overview__payment-method method">
				<?php _e( 'Tasa:', 'woocommerce' ); ?>
				<strong>Bs. <?php echo number_format($rate, 2) ?></strong>
			</li>
			<li class="woocommerce-order-overview__payment-method method">
				<?php _e( 'Total pagado:', 'woocommerce' ); ?>
				<strong>Bs. <?php echo number_format($total_ves, 2) ?></strong>
			</li>
			<?php endif ?>
		</ul>
		<?php
	}

	public function process_payment( $order_id ) {

		$fail = false;

	    if( !isset($_POST[$this->id.'_payment_account']) || empty($_POST[$this->id.'_payment_account']) ){
	    	$fail = true;
	        wc_add_notice( __( 'Seleccione '.$this->account_number_name() . ' donde realizó el pago', 'woocommerce' ), 'error' );
	    }


	    if( !isset($_POST[$this->id.'_payment_reference']) || empty($_POST[$this->id.'_payment_reference']) ){
	    	$fail = true;
	        wc_add_notice( __( 'Por favor indica tu número de referencia de pago', 'woocommerce' ), 'error' );
	    }

	    if($fail){
	    	return array(
				'result'   => 'error',
			);
	    }
		

		$order = wc_get_order( $order_id );

		$account = $_POST[$this->id.'_payment_account'];
		$reference = $_POST[$this->id.'_payment_reference'];
		$rate = $this->get_exchange_rate();
		$total_ves = $_POST[$this->id.'_total_ves'];

		$order->add_meta_data($this->id.'_is_exchanged', $this->has_to_exchange(), true);

		$order->add_meta_data($this->id.'_account', $account, true);
		$order->add_meta_data($this->id.'_reference', $reference, true);
		
		if ($this->has_to_exchange()) {
			$order->add_meta_data($this->id.'_rate', $rate, true);
			$order->add_meta_data($this->id.'_total_ves', $total_ves, true);
		}

		if ( $order->get_total() > 0 ) {
			// Mark as on-hold (we're awaiting the payment).

			$note  = "Esperando confirmación de pago\n\n";
			$note .= "Cuenta: $account\n";
			$note .= "Referencia: $rate\n";

			if ($this->has_to_exchange()) {
				$note .= "Tipo de cambio: $rate\n";
				$note .= "Total Bs. $total_ves\n";
			}
			$note .= "\n";

			$order->update_status( apply_filters( 'woocommerce_'.$this->id.'_process_payment_order_status', 'processing', $order ), $note );


		} else {
			$order->payment_complete();
		}

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);

	}
}