<?php
class BitpayCheckout_BPCheckout_Crypto
{
    public function toOptionArray()
    {
        return array(
	        array('value' => 'BTC', 'label'=>Mage::helper('adminhtml')->__('BTC')),
	        array('value' => 'BCH', 'label'=>Mage::helper('adminhtml')->__('BCH'))
        );
    }
}
