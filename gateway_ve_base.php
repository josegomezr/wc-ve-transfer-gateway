<?php

class GateawayVEBase extends WC_Gateway_BACS {
    public $id = 'base_ve';

    public function __construct() {
        $this->_cached_exchange_rate = NULL;
        $this->icon               = apply_filters( 'woocommerce_'.$this->id.'_icon', '' );
        $this->has_fields         = false;
        $this->method_title       = __( 'Transferencia', 'woocommerce' );
        $this->method_description = __( 'Maneje pagos via transferencia bancaria en VE', 'woocommerce' );

        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
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
        add_action( 'woocommerce_thankyou_bacs', array( $this, 'thankyou_page' ) );

        // Customer Emails.
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        // add_action( 'woocommerce_admin_order_data_after_billing_address', array($this, 'checkout_order_meta'), 10, 1 );

    }

    public function fetch_exchange_rate()
    {
        $api_url ='https://bitven.com/assets/js/rates.js?random='.time();
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
        return $api_response['USD_TO_BSF_RATE'];
    }

    public function get_exchange_rate()
    {
        if(!$this->_cached_exchange_rate){
            $this->_cached_exchange_rate = $this->fetch_exchange_rate();
        }
        return $this->_cached_exchange_rate;
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
                <th>No. Cuenta</th>
                <td><?php echo $account['account_number'] ?></td>
            </tr>
        </table>
        <hr>
        <?php endforeach; ?>
        <div id="custom_input">
            <p class="form-row form-row-wide">
                <label>Tasa:</label>
                <input type="text" name="rate" class="input-text" readonly="readonly" value="<?php echo $this->get_exchange_rate() ?>">
            </p>
            <p class="form-row form-row-wide">
                <label>Total a transferir:</label>
                <input type="text" name="total_to_be_transfered" class="input-text" readonly="readonly" value="<?php echo WC()->cart->get_total(false) * $this->get_exchange_rate() ?>">
            </p>
            <p class="form-row form-row-wide">
                <label for="mobile" class=""><?php _e('Cuenta', $this->domain); ?></label>
                <select name="<?php echo $this->id ?>_payment_account">
                    <option value="">Seleccione...</option>
                    <?php foreach($this->account_details as $account): ?>
                        <option value="<?php echo $account['account_number'] ?>">
                            <?php echo $account['bank_name'] ?> - <?php echo $account['account_number'] ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </p>
            <p class="form-row form-row-wide">
                <label for="transaction" class=""><?php _e('Referencia', $this->domain); ?></label>
                <input type="text" class="" name="transaction" id="transaction" placeholder="" value="">
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
                                <th><?php esc_html_e( 'Bank name', 'woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Account Document', 'woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Account number', 'woocommerce' ); ?></th>
                            </tr>
                        </thead>
                        <tbody class="accounts">
                            <?php
                            $i = -1;
                            if ( $this->account_details ) {
                                foreach ( $this->account_details as $account ) {
                                    $i++;

                                    echo '<tr class="account">
                                        <td class="sort"></td>
                                        <td><input type="text" value="' . esc_attr( wp_unslash( $account['bank_name'] ) ) . '" name="'.$this->id.'_bank_name[' . esc_attr( $i ) . ']" /></td>
                                        <td><input type="text" value="' . esc_attr( wp_unslash( $account['account_document'] ) ) . '" name="'.$this->id.'_account_document[' . esc_attr( $i ) . ']" /></td>
                                        <td><input type="text" value="' . esc_attr( $account['account_number'] ) . '" name="'.$this->id.'_account_number[' . esc_attr( $i ) . ']" /></td>
                                    </tr>';
                                }
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="7"><a href="#" class="add button"><?php esc_html_e( '+ Add account', 'woocommerce' ); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected account(s)', 'woocommerce' ); ?></a></th>
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
}