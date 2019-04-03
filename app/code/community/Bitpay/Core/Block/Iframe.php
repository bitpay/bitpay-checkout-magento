<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 * 
 */

class Bitpay_Core_Block_Iframe extends Mage_Checkout_Block_Onepage_Payment
{
    /**
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('bitpay/iframe.phtml');
    }

    /**
     * create an invoice and return the url so that iframe.phtml can display it
     *
     * @return string
     */
    public function getIframeUrl()
    {

        if (!($quote = Mage::getSingleton('checkout/session')->getQuote()) 
            or !($payment = $quote->getPayment())
            or !($paymentMethod = $payment->getMethod())
            or ($paymentMethod !== 'bitpay')
            or (Mage::getStoreConfig('payment/bitpay/fullscreen')))
        {
            return 'notbitpay';
        }

        \Mage::helper('bitpay')->registerAutoloader();

        // fullscreen disabled?
        if (Mage::getStoreConfig('payment/bitpay/fullscreen'))
        {
            return 'disabled';
        }

        if (\Mage::getModel('bitpay/ipn')->getQuotePaid($this->getQuote()->getId())) {
            return 'paid'; // quote's already paid, so don't show the iframe
        }

        return 'bitpay';
    }
}
