<?php
class BitpayCheckout_BPCheckout_PaymentController extends Mage_Core_Controller_Front_Action
{
    public function gatewayAction()
    {
        if ($this->getRequest()->get("orderId")) {
            $arr_querystring = array(
                'flag' => 1,
                'orderId' => $this->getRequest()->get("orderId"),
            );

            Mage_Core_Controller_Varien_Action::_redirect('bpcheckout/payment/response', array('_secure' => false, '_query' => $arr_querystring));
        }
    }
    
}
