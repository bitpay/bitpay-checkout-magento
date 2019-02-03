<?php
class BitpayCheckout_BPCheckout_Ux
{
    public function toOptionArray()
    {
        return array(
	        array('value' => 'redirect', 'label'=>Mage::helper('adminhtml')->__('Redirect')),
	        array('value' => 'modal', 'label'=>Mage::helper('adminhtml')->__('Modal'))
        );
    }
}