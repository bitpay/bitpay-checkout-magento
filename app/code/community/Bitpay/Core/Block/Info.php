<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

class Bitpay_Core_Block_Info extends Mage_Payment_Block_Info
{
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('bitpay/info/default.phtml');
    }

    public function getBitpayInvoiceUrl()
    {
        $order       = $this->getInfo()->getOrder();

        if (false === isset($order) || true === empty($order)) {
            \Mage::helper('bitpay')->debugData('[ERROR] In Bitpay_Core_Block_Info::getBitpayInvoiceUrl(): could not obtain the order.');
            throw new \Exception('In Bitpay_Core_Block_Info::getBitpayInvoiceUrl(): could not obtain the order.');
        }

        $incrementId = $order->getIncrementId();

        if (false === isset($incrementId) || true === empty($incrementId)) {
            \Mage::helper('bitpay')->debugData('[ERROR] In Bitpay_Core_Block_Info::getBitpayInvoiceUrl(): could not obtain the incrementId.');
            throw new \Exception('In Bitpay_Core_Block_Info::getBitpayInvoiceUrl(): could not obtain the incrementId.');
        }

        $bitpayInvoice = \Mage::getModel('bitpay/invoice')->load($incrementId, 'increment_id');

        if (true === isset($bitpayInvoice) && false === empty($bitpayInvoice)) {
            return $bitpayInvoice->getUrl();
        }
    }
}
