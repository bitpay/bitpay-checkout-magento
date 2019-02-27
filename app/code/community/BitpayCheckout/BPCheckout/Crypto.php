<?php
class BitpayCheckout_BPCheckout_Crypto
{
    public function toOptionArray()
    {
        return array(
	        array('name'=>'bitpay_crypto[]','value' => 'BTC', 'label'=>Mage::helper('adminhtml')->__('BTC')),
	        array('name'=>'bitpay_crypto[]','value' => 'BCH', 'label'=>Mage::helper('adminhtml')->__('BCH'))
        );
    }
}
