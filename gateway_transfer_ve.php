<?php

class GatewayTransferVE extends GateawayVEBase {
    public $id = 'banks_ve';
    public function __construct() {
        parent::__construct();

        $this->method_title       = __( 'Transferencia', 'woocommerce' );
        $this->method_description = __( 'Maneje pagos via transferencia de fondos en VE', 'woocommerce' );
    }

    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->form_fields['enabled']['label'] = __('Activar Transferencias Bancarias', 'woocommerce');
        $this->form_fields['description']['default'] = __('Recibe pagos a través de transferencia de fondos', 'woocommerce');
    }
}