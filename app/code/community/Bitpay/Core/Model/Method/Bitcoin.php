<?php
/**
 * @license Copyright 2011-2015 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 * Bitcoin payment method support by BitPay
 */
class Bitpay_Core_Model_Method_Bitcoin extends Mage_Payment_Model_Method_Abstract
{
    protected $_code                        = 'bitpay';
    protected $_formBlockType               = 'bitpay/form_bitpay';
    protected $_infoBlockType               = 'bitpay/info';

    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = false;
    protected $_canUseInternal              = false;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = false;
    protected $_canManagerRecurringProfiles = false;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = true;
    protected $_canCapturePartial           = false;
    protected $_canRefund                   = false;
    protected $_canVoid                     = false;

    protected $_debugReplacePrivateDataKeys = array();

    protected static $_redirectUrl;

    /**
     * @param  Mage_Sales_Model_Order_Payment  $payment
     * @param  float                           $amount
     * @return Bitpay_Core_Model_PaymentMethod
     */
    public function authorize(Varien_Object $payment, $amount, $iframe = false)
    {
        if (false === isset($payment) || false === isset($amount) || true === empty($payment) || true === empty($amount)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::authorize(): missing payment or amount parameters.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::authorize(): missing payment or amount parameters.');
        }

        // use the price in the currency of the store (not in the user selected currency)
        $amount = $payment->getOrder()->getQuote()->getBaseGrandTotal();

        $this->debugData('[INFO] Bitpay_Core_Model_Method_Bitcoin::authorize(): authorizing new order.');

        // Create BitPay Invoice
        $invoice = $this->initializeInvoice();

        if (false === isset($invoice) || true === empty($invoice)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::authorize(): could not initialize invoice.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::authorize(): could not initialize invoice.');
        }
        
        //add order id to the redirect url to match order in the checkout/onepage/success if bitpay invoice expired
        $invoice->setRedirectUrl(\Mage::getUrl(\Mage::getStoreConfig('payment/bitpay/redirect_url') . '/order_id/'.$payment->getOrder()->getId()));    

        $invoice = $this->prepareInvoice($invoice, $payment, $amount);
        
        try {
            $bitpayInvoice = \Mage::helper('bitpay')->getBitpayClient()->createInvoice($invoice);            
        } catch (\Exception $e) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::authorize(): ' . $e->getMessage());            
            //display min invoice value error    
            if(strpos($e->getMessage(), 'Invoice price must be') !== FALSE)
            {
                \Mage::throwException($e->getMessage());
            }
            \Mage::throwException('In Bitpay_Core_Model_Method_Bitcoin::authorize(): Could not authorize transaction.');
        }

        self::$_redirectUrl = (Mage::getStoreConfig('payment/bitpay/fullscreen')) ? $bitpayInvoice->getUrl(): $bitpayInvoice->getUrl().'&view=iframe';

        $this->debugData(
            array(
                '[INFO] BitPay Invoice created',
                sprintf('Invoice URL: "%s"', $bitpayInvoice->getUrl()),
            )
        );

        $quote = \Mage::getSingleton('checkout/session')->getQuote();
        $order = \Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');

        // Save BitPay Invoice in database for reference
        $mirrorInvoice = \Mage::getModel('bitpay/invoice')
            ->prepareWithBitpayInvoice($bitpayInvoice)
            ->prepareWithOrder(array('increment_id' => $order->getIncrementId(), 'quote_id'=> $quote->getId()))
            ->save();

        $this->debugData('[INFO] Leaving Bitpay_Core_Model_Method_Bitcoin::authorize(): invoice id ' . $bitpayInvoice->getId());

        return $this;
    }

    /**
     * This makes sure that the merchant has setup the extension correctly
     * and if they have not, it will not show up on the checkout.
     *
     * @see Mage_Payment_Model_Method_Abstract::canUseCheckout()
     * @return bool
     */
    public function canUseCheckout()
    {
        $token = \Mage::getStoreConfig('payment/bitpay/token');

        if (false === isset($token) || true === empty($token)) {
            /**
             * Merchant must goto their account and create a pairing code to
             * enter in.
             */
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::canUseCheckout(): There was an error retrieving the token store param from the database or this Magento store does not have a BitPay token.');

            return false;
        }

        $this->debugData('[INFO] Leaving Bitpay_Core_Model_Method_Bitcoin::canUseCheckout(): token obtained from storage successfully.');

        return true;
    }

    /**
     * Fetchs an invoice from BitPay
     *
     * @param string $id
     * @return Bitpay\Invoice
     */
    public function fetchInvoice($id)
    {
        if (false === isset($id) || true === empty($id)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::fetchInvoice(): missing or invalid id parameter.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::fetchInvoice(): missing or invalid id parameter.');
        } else {
            $this->debugData('[INFO] In Bitpay_Core_Model_Method_Bitcoin::fetchInvoice(): function called with id ' . $id);
        }

       \Mage::helper('bitpay')->registerAutoloader();

        $client  = \Mage::helper('bitpay')->getBitpayClient();

        if (false === isset($client) || true === empty($client)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::fetchInvoice(): could not obtain BitPay client.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::fetchInvoice(): could not obtain BitPay client.');
        } else {
            $this->debugData('[INFO] In Bitpay_Core_Model_Method_Bitcoin::fetchInvoice(): obtained BitPay client successfully.');
        }

        $invoice = $client->getInvoice($id);

        if (false === isset($invoice) || true === empty($invoice)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::fetchInvoice(): could not retrieve invoice from BitPay.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::fetchInvoice(): could not retrieve invoice from BitPay.');
        } else {
            $this->debugData('[INFO] In Bitpay_Core_Model_Method_Bitcoin::fetchInvoice(): successfully retrieved invoice id ' . $id . ' from BitPay.');
        }

        return $invoice;
    }

    /**
     * given Mage_Core_Model_Abstract, return api-friendly address
     *
     * @param $address
     *
     * @return array
     */
    public function extractAddress($address)
    {
        if (false === isset($address) || true === empty($address)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::extractAddress(): missing or invalid address parameter.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::extractAddress(): missing or invalid address parameter.');
        } else {
            $this->debugData('[INFO] In Bitpay_Core_Model_Method_Bitcoin::extractAddress(): called with good address parameter, extracting now.');
        }

        $options              = array();
        $options['buyerEmail']    = $address->getEmail();

        // trim to fit API specs
        foreach (array('buyerEmail') as $f) {
            if (true === isset($options[$f]) && strlen($options[$f]) > 100) {
                $this->debugData('[WARNING] In Bitpay_Core_Model_Method_Bitcoin::extractAddress(): the ' . $f . ' parameter was greater than 100 characters, trimming.');
                $options[$f] = substr($options[$f], 0, 100);
            }
        }

        return $options;
    }

    /**
     * This is called when a user clicks the `Place Order` button
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $this->debugData('[INFO] In Bitpay_Core_Model_Method_Bitcoin::getOrderPlaceRedirectUrl(): $_redirectUrl is ' . self::$_redirectUrl);

        return self::$_redirectUrl;

    }

    /**
     * Create a new invoice with as much info already added. It should add
     * some basic info and setup the invoice object.
     *
     * @return Bitpay\Invoice
     */
    private function initializeInvoice()
    {
        \Mage::helper('bitpay')->registerAutoloader();

        $invoice = new Bitpay\Invoice();

        if (false === isset($invoice) || true === empty($invoice)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::initializeInvoice(): could not construct new BitPay invoice object.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::initializeInvoice(): could not construct new BitPay invoice object.');
        } else {
            $this->debugData('[INFO] In Bitpay_Core_Model_Method_Bitcoin::initializeInvoice(): constructed new BitPay invoice object successfully.');
        }

        $invoice->setFullNotifications(true);
        $invoice->setTransactionSpeed('medium');
        $invoice->setNotificationUrl(\Mage::getUrl(\Mage::getStoreConfig('payment/bitpay/notification_url')));
        $invoice->setRedirectUrl(\Mage::getUrl(\Mage::getStoreConfig('payment/bitpay/redirect_url')));

        return $invoice;
    }

    /**
     * Prepares the invoice object to be sent to BitPay's API. This method sets
     * all the other info that we have to rely on other objects for.
     *
     * @param Bitpay\Invoice                  $invoice
     * @param  Mage_Sales_Model_Order_Payment $payment
     * @param  float                          $amount
     * @return Bitpay\Invoice
     */
    private function prepareInvoice($invoice, $payment, $amount)
    {
        if (false === isset($invoice) || true === empty($invoice) || false === isset($payment) || true === empty($payment) || false === isset($amount) || true === empty($amount)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::prepareInvoice(): missing or invalid invoice, payment or amount parameter.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::prepareInvoice(): missing or invalid invoice, payment or amount parameter.');
        } else {
            $this->debugData('[INFO] In Bitpay_Core_Model_Method_Bitcoin::prepareInvoice(): entered function with good invoice, payment and amount parameters.');
        }

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $order = \Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');

        $invoice->setOrderId($order->getIncrementId());
        $invoice->setExtendedNotifications(true);
        $invoice->setPosData(json_encode(array('orderId' => $order->getIncrementId())));

        $invoice = $this->addCurrencyInfo($invoice, $order);
        $invoice = $this->addPriceInfo($invoice, $amount);
        $invoice = $this->addBuyerInfo($invoice, $order);

        return $invoice;
    }

    /**
     * This adds the buyer information to the invoice.
     *
     * @param Bitpay\Invoice         $invoice
     * @param Mage_Sales_Model_Order $order
     * @return Bitpay\Invoice
     */
    private function addBuyerInfo($invoice, $order)
    {
        if (false === isset($invoice) || true === empty($invoice) || false === isset($order) || true === empty($order)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::addBuyerInfo(): missing or invalid invoice or order parameter.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::addBuyerInfo(): missing or invalid invoice or order parameter.');
        } else {
            $this->debugData('[INFO] In Bitpay_Core_Model_Method_Bitcoin::addBuyerInfo(): function called with good invoice and order parameters.');
        }

        $buyer = new Bitpay\Buyer();

        if (false === isset($buyer) || true === empty($buyer)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::addBuyerInfo(): could not construct new BitPay buyer object.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::addBuyerInfo(): could not construct new BitPay buyer object.');
        }

        if (Mage::getStoreConfig('payment/bitpay/fullscreen')) {
            $address = $order->getBillingAddress();
        } else {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $address = $quote->getBillingAddress();
        }

        $email = $address->getEmail();
        if (null !== $email && '' !== $email) {
            $buyer->setEmail($email);
        }

        $invoice->setBuyer($buyer);

        return $invoice;
    }

    /**
     * Adds currency information to the invoice
     *
     * @param Bitpay\Invoice         $invoice
     * @param Mage_Sales_Model_Order $order
     * @return Bitpay\Invoice
     */
    private function addCurrencyInfo($invoice, $order)
    {
        if (false === isset($invoice) || true === empty($invoice) || false === isset($order) || true === empty($order)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::addCurrencyInfo(): missing or invalid invoice or order parameter.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::addCurrencyInfo(): missing or invalid invoice or order parameter.');
        } else {
            $this->debugData('[INFO] In Bitpay_Core_Model_Method_Bitcoin::addCurrencyInfo(): function called with good invoice and order parameters.');
        }

        $currency = new Bitpay\Currency();

        if (false === isset($currency) || true === empty($currency)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::addCurrencyInfo(): could not construct new BitPay currency object.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::addCurrencyInfo(): could not construct new BitPay currency object.');
        }

        //$currency->setCode($order->getOrderCurrencyCode());
        //use the store currency code (not the customer selected currency)
        $currency->setCode(\Mage::app()->getStore()->getBaseCurrencyCode());
        $invoice->setCurrency($currency);

        return $invoice;
    }

    /**
     * Adds pricing information to the invoice
     *
     * @param Bitpay\Invoice  invoice
     * @param float           $amount
     * @return Bitpay\Invoice
     */
    private function addPriceInfo($invoice, $amount)
    {
        if (false === isset($invoice) || true === empty($invoice) || false === isset($amount) || true === empty($amount)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::addPriceInfo(): missing or invalid invoice or amount parameter.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::addPriceInfo(): missing or invalid invoice or amount parameter.');
        } else {
            $this->debugData('[INFO] In Bitpay_Core_Model_Method_Bitcoin::addPriceInfo(): function called with good invoice and amount parameters.');
        }

        $item = new \Bitpay\Item();

        if (false === isset($item) || true === empty($item)) {
            $this->debugData('[ERROR] In Bitpay_Core_Model_Method_Bitcoin::addPriceInfo(): could not construct new BitPay item object.');
            throw new \Exception('In Bitpay_Core_Model_Method_Bitcoin::addPriceInfo(): could not construct new BitPay item object.');
        }

        $item->setPrice($amount);
        $invoice->setItem($item);

        return $invoice;
    }
}
