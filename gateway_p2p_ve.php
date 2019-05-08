<?php

class GatewayP2PVE extends GateawayVEBase {
    public $id = 'p2p_ve';

    public function __construct() {
        parent::__construct();
        
        $this->method_title       = __( 'Pago Móvil', 'woocommerce' );
        $this->method_description = __( 'Maneje pagos via pagomovil interbancario en VE', 'woocommerce' );
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['enabled']['label'] = __('Activar Pago Móvil', 'woocommerce');
        $this->form_fields['description']['default'] = __('Recibe pagos con la plataforma P2P interbancaria', 'woocommerce');
    }
}