<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

class Bitpay_Core_Block_Form_Bitpay extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $payment_template = 'bitpay/form/bitpay.phtml';

        parent::_construct();
        
        $this->setTemplate($payment_template);
    }
}
