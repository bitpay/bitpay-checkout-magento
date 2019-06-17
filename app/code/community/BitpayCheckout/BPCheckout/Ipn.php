<?php
class BitpayCheckout_BPCheckout_Ipn
{
    public function toOptionArray()
    {
        return array(
	        array('value' => 'pending', 'label'=>Mage::helper('adminhtml')->__('Pending Payment')),
	        array('value' => 'processing', 'label'=>Mage::helper('adminhtml')->__('Processing'))
        );
    }
}
