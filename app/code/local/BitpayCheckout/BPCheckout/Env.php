<?php
class BitpayCheckout_BPCheckout_Env
{
    public function toOptionArray()
    {
        return array(
	        array('value' => 'test', 'label'=>Mage::helper('adminhtml')->__('Test')),
	        array('value' => 'production', 'label'=>Mage::helper('adminhtml')->__('Production'))
        );
    }
}