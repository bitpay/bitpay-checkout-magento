<?php

require_once 'app/code/community/BitpayCheckout/BPCheckout/controllers/Paymentcontroller.php';

class BitpayCheckout_BPCheckout_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        #nothing to do
    }

    public function redirectAction($modal = null, $orderId = null)
    {
        #include our custom BP2 classes
        function BPC_autoloader($class)
        {
            if (strpos($class, 'BPC_') !== false):
                if (!class_exists('BitPayLib/' . $class, false)):
                    #doesnt exist so include it
                    require 'BitPayLib/' . $class . '.php';
                endif;
            endif;
        }

        spl_autoload_register('BPC_autoloader');

        $order = new Mage_Sales_Model_Order();

        if ($modal != null && $orderId != null):
            #modal popup, nothing special needed
        else:
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        endif;

        $order->loadByIncrementId($orderId);
        $shortOrderID = $order->getId();

        $env = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_endpoint');
        if ($env == 'test'):
            $bitpay_token = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_devtoken');

        else:
            $bitpay_token = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_prodtoken');
        endif;
        $config = new BPC_Configuration($bitpay_token, $env);
        //create an item, should be passed as an object'
        $params = new stdClass();
        $params->extension_version = $this->getExtensionVersion();
        $params->price = $order->base_grand_total;
        $params->currency = $order->base_currency_code; //set as needed

        #buyers email
        $current_user = Mage::getSingleton("customer/session");
        $buyerInfo = new stdClass();
        $guest_login = true;
        $params->orderId = trim($orderId);
        if ($current_user->isLoggedIn()) {
            $guest_login = false;
            $current_user = $current_user->getCustomer();
            $buyerInfo->name = $current_user->getName();
            $buyerInfo->email = $current_user->getEmail();
            $params->buyer = $buyerInfo;

        } else {
            #guest info
            $buyerInfo->name = $order->customer_firstname . ' ' . $order->customer_lastname;
            $buyerInfo->email = $order->customer_email;
            $params->buyer = $buyerInfo;
            #set some info for guest checkout
            setcookie('oar_order_id', $params->orderId, time() + (86400 * 30), "/"); // 86400 = 1 day
            setcookie('oar_billing_lastname', $order->customer_lastname, time() + (86400 * 30), "/"); // 86400 = 1 day
            setcookie('oar_email', $order->customer_email, time() + (86400 * 30), "/"); // 86400 = 1 day

        }

        if ($guest_login):
            $params->redirectURL = Mage::getBaseUrl() . 'sales/guest/form/?orderId=' . $params->orderId . '&lastname=' . $order->customer_lastname . '&email=' . $order->customer_email;
        else:
            $params->redirectURL = Mage::getBaseUrl() . 'sales/order/view/order_id/' . $shortOrderID . '/';
        endif;

        #ipn
        $hash_key = $config->BPC_generateHash($params->orderId);
        $params->notificationURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, true) . 'bitpayipn/index/bitpayipn?hash_key=' . $hash_key;
        $params->extendedNotifications = true;

        $cartFix = Mage::getBaseUrl() . 'cartfix/index/renewcart/orderid/' . $orderId;
        $item = new BPC_Item($config, $params);

        $invoice = new BPC_Invoice($item);

        //this creates the invoice with all of the config params from the item
        $invoice->BPC_createInvoice();
        $invoiceData = json_decode($invoice->BPC_getInvoiceData());
        //now we have to append the invoice transaction id for the callback verification
        $invoiceID = $invoiceData->data->id;
        $use_modal = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_ux');

        Mage::getSingleton('checkout/cart')->truncate();
        Mage::getSingleton('checkout/cart')->save();

        #insert into the lookup table
        $prefix = (string) Mage::getConfig()->getTablePrefix();
        $table_name = $prefix . 'bitpay_transactions';
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $sql = "INSERT INTO $table_name (order_id,transaction_id,transaction_status) VALUES ('" . $orderId . "','" . $invoiceID . "','new')";
        $write->query($sql);

        switch ($use_modal) {
            default:
            case 'redirect':
                Mage::app()->getResponse()->setRedirect($invoice->BPC_getInvoiceURL())->sendResponse();
                return;
                break;
            case 'modal':
                $modal_obj = new stdClass();
                $modal_obj->redirectURL = $params->redirectURL;
                $modal_obj->notificationURL = $params->notificationURL;
                $modal_obj->cartFix = $cartFix;
                $modal_obj->invoiceID = $invoiceID;
                setcookie('use_modal', $use_modal, time() + (86400 * 30), "/"); // 86400 = 1 day
                setcookie('env', $env, time() + (86400 * 30), "/"); // 86400 = 1 day
                return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($modal_obj));
                break;
        }

    }
    public function modalAction()
    {
        $orderId = Mage::getModel("sales/order")->getCollection()->getLastItem()->getIncrementId();
        BitpayCheckout_BPCheckout_IndexController::redirectAction(true, $orderId);

    }
    public function renewcartAction()
    {
        #clean the cart first so orders don't double
        Mage::getSingleton('checkout/cart')->truncate();
        #Mage::getSingleton('checkout/cart')->save();

        #get the order info
        Mage::app('default');
        Mage::register('isSecureArea', 1);
        $orderId = $this->getRequest()->getParam('orderid');
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

        $orderItems = $order->getItemsCollection()
            ->addAttributeToSelect('*')
            ->load();

        #repopulate the cart
        $cart = Mage::helper('checkout/cart')->getCart();
        foreach ($orderItems as $sItem) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId(
                    Mage::app()
                        ->getStore()
                        ->getId()
                )
                ->load($sItem->getProductId());

            $cart->addProduct($product, $sItem->getQtyOrdered());

        }

        $cart->save();
        //finally remove the order

        $invoices = $order->getInvoiceCollection();
        foreach ($invoices as $invoice) {
            $invoice->delete();
        }

        $creditnotes = $order->getCreditmemosCollection();
        foreach ($creditnotes as $creditnote) {
            $creditnote->delete();
        }

        $shipments = $order->getShipmentsCollection();
        foreach ($shipments as $shipment) {
            $shipment->delete();
        }

        $order->delete();

        Mage::unregister('isSecureArea');

        #now redirect back to the cart
        $cart_url = Mage::getBaseUrl() . 'checkout/cart/';
        Mage::app()->getResponse()->setRedirect($cart_url)->sendResponse();
        return;

    }

    public function updateBPCTransactions($table_name, $invoice_status, $orderid, $order_invoice)
    {
        #lets update the db
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $sql = "UPDATE $table_name SET transaction_status = '$invoice_status' WHERE order_id = '$orderid' AND transaction_id = '$order_invoice'";
        $write->query($sql);

    }
    //mg host + bitpayipn/index/bitpayipn
    public function bitpayipnAction()
    {

        #$hash_key = $_REQUEST['hash_key'];

        #include our custom BP2 classes
        #autoloader
        function BPC_autoloader($class)
        {
            if (strpos($class, 'BPC_') !== false):
                if (!class_exists('BitPayLib/' . $class, false)):
                    #doesnt exist so include it
                    require 'BitPayLib/' . $class . '.php';
                endif;
            endif;
        }

        spl_autoload_register('BPC_autoloader');

        $all_data = json_decode(file_get_contents("php://input"), true);
        #
        $data = $all_data['data'];
        $event = $all_data['event'];

        $orderid = $data['orderId'];

        $order_status = $data['status'];
        $order_invoice = $data['id'];

        #check and see if its in the lookup
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $prefix = (string) Mage::getConfig()->getTablePrefix();
        $table_name = $prefix . 'bitpay_transactions';
        $sql = "SELECT * FROM $table_name WHERE order_id = '$orderid' AND transaction_id = '$order_invoice' ";
        $result = $read->query($sql);
        $row = $result->fetch();

        if ($row): #there is a record
            $env = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_endpoint');
            if ($env == 'test'):
                $bitpay_token = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_devtoken');

            else:
                $bitpay_token = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_prodtoken');
            endif;
            $bitpay_ipn_mapping = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_ipn_mapping');
            $config = new BPC_Configuration($bitpay_token, $env);
            #check the hash
            #verify the hash before moving on

            #disable this for now so new installs can start creating
            #if(!$config->BPC_checkHash($orderid,$hash_key)):
            #    die();
            #endif;
            #double check to make sure this is value
            $params = new stdClass();
            $params->extension_version = $this->getExtensionVersion();
            $params->invoiceID = $order_invoice;

            $item = new BPC_Item($config, $params);

            $invoice = new BPC_Invoice($item); //this creates the invoice with all of the config params

            $orderStatus = json_decode($invoice->BPC_checkInvoiceStatus($order_invoice));
            $invoice_status = $orderStatus->data->status;

            switch ($event['name']) {
                case 'invoice_completed':
                    if ($invoice_status == 'complete'):
                        #load the order to update
                        $order = new Mage_Sales_Model_Order();
                        $order->loadByIncrementId($orderid);
                        $comment = 'BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> status has changed to Completed.';

                        $order->addStatusHistoryComment($comment,
                            Mage_Sales_Model_Order::STATE_PROCESSING);
                        $order->save();
                        $this->updateBPCTransactions($table_name, $invoice_status, $orderid, $order_invoice);
                        return true;
                    endif;
                    break;

                case 'invoice_confirmed':
                    if ($invoice_status == 'confirmed'):
                        #load the order to update
                        $order = new Mage_Sales_Model_Order();
                        $order->loadByIncrementId($orderid);
                        $comment = 'BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> processing has been completed.';

                        if ($bitpay_ipn_mapping != 'processing'):
                            $order->addStatusHistoryComment('BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> processing has been completed.',
                                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                        else:
                            $order->addStatusHistoryComment($comment,
                                Mage_Sales_Model_Order::STATE_PROCESSING);
                        endif;

                        $order->save();
                        $this->updateBPCTransactions($table_name, $invoice_status, $orderid, $order_invoice);
                        $this->addInvoice($order, $comment);
                        return true;
                    endif;
                    break;

                case 'invoice_paidInFull':
                    if ($invoice_status == 'paid'):
                        #load the order to update
                        $comment = 'BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> is processing.';
                        $order = new Mage_Sales_Model_Order();
                        $order->loadByIncrementId($orderid);
                        $order->addStatusHistoryComment($comment,
                            Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                        $this->updateBPCTransactions($table_name, $invoice_status, $orderid, $order_invoice);
                        return true;
                    endif;
                    break;

                case 'invoice_failedToConfirm':
                    if ($invoice_status == 'invalid'):
                        #load the order to update
                        $order = new Mage_Sales_Model_Order();

                        $order->loadByIncrementId($orderid);

                        $order->addStatusHistoryComment('BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> has become invalid because of network congestion.  Order will automatically update when the status changes.');
                        $order->save();
                        $this->updateBPCTransactions($table_name, $invoice_status, $orderid, $order_invoice);
                        return true;
                    endif;
                    break;

                case 'invoice_expired':
                    if ($invoice_status == 'expired'):
                        #load the order to update
                        $order = new Mage_Sales_Model_Order();

                        $order->loadByIncrementId($orderid);

                        $order->delete();
                        $this->updateBPCTransactions($table_name, $invoice_status, $orderid, $order_invoice);
                        return true;
                    endif;
                    break;

                case 'invoice_refundComplete':
                    #load the order to update

                    $order = new Mage_Sales_Model_Order();

                    $order->loadByIncrementId($orderid);

                    $order->addStatusHistoryComment('BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> has been refunded.',
                        Mage_Sales_Model_Order::STATE_CLOSED);

                    $order->save();
                    $this->updateBPCTransactions($table_name, $invoice_status, $orderid, $order_invoice);

                    return true;
                    break;
            }
        endif; #end of row checker
    }
    public function addInvoice($order, $note)
    {
        try {
            if (!$order->canInvoice()) {
                #couldnt make an invoice
                #Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
            }

            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

            if (!$invoice->getTotalQty()) {
                #couldnt make an invoice
                #Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
            }

            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->addComment($note, false/*notify customer*/, true/*visibleOnFront*/);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();
        } catch (Mage_Core_Exception $e) {

        }
    }
    public function getExtensionVersion()
    {
        return 'BitPay_Checkout_Magento1_3.1.1';
    }
}
