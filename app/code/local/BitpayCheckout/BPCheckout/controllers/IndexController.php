<?php

require_once 'app/code/local/BitpayCheckout/BPCheckout/controllers/Paymentcontroller.php';

class BitpayCheckout_BPCheckout_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        echo '<h2>You should not be here.</h2>';
        die();

    }

    public function redirectAction($modal = null, $orderId = null)
    {
        #include our custom BP2 classes
        require 'classes/Config.php';
        require 'classes/Client.php';
        require 'classes/Item.php';
        require 'classes/Invoice.php';

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
        $config = new Configuration($bitpay_token, $env);

        //create an item, should be passed as an object'
        $params                    = new stdClass();
        $params->extension_version = $this->getExtensionVersion();
        $params->price             = $order->base_grand_total;
        $params->currency          = $order->base_currency_code; //set as needed

       
        #buyers email
        $bitpay_capture_email = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_capture_email');
        if ($bitpay_capture_email == 1):
            $current_user         = Mage::getSingleton('customer/session')->getCustomer();
            $buyerInfo        = new stdClass();
            $buyerInfo->name  = $current_user->getName();
            $buyerInfo->email = $current_user->getEmail();
            $params->buyer    = $buyerInfo;
        endif;
        $params->orderId     = trim($orderId);
        $params->redirectURL = Mage::getBaseUrl() . 'sales/order/view/order_id/' . $shortOrderID . '/';
        #ipn
        $params->notificationURL       = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, true) . 'bitpayipn/index/bitpayipn';
        $params->extendedNotifications = true;

        $cartFix = Mage::getBaseUrl() . 'cartfix/index/renewcart/orderid/' . $orderId;
        $item    = new Item($config, $params);

        $invoice = new Invoice($item);

        //this creates the invoice with all of the config params from the item
        $invoice->createInvoice();
        $invoiceData = json_decode($invoice->getInvoiceData());
        //now we have to append the invoice transaction id for the callback verification
        $invoiceID = $invoiceData->data->id;
        $use_modal = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_ux');

        Mage::getSingleton('checkout/cart')->truncate();
        Mage::getSingleton('checkout/cart')->save();

        #insert into the lookup table
        $prefix     = (string) Mage::getConfig()->getTablePrefix();
        $table_name = $prefix . 'bitpay_transactions';
        $write      = Mage::getSingleton('core/resource')->getConnection('core_write');
        $sql        = "INSERT INTO $table_name (order_id,transaction_id,transaction_status) VALUES ('" . $orderId . "','" . $invoiceID . "','new')";
        $write->query($sql);

        switch ($use_modal) {
            default:
            case 'redirect':
                Mage::app()->getResponse()->setRedirect($invoice->getInvoiceURL())->sendResponse();
                return;
                break;
            case 'modal':
                $modal_obj                  = new stdClass();
                $modal_obj->redirectURL     = $params->redirectURL;
                $modal_obj->notificationURL = $params->notificationURL;
                $modal_obj->cartFix         = $cartFix;
                $modal_obj->invoiceID       = $invoiceID;
                echo json_encode($modal_obj);

                return;
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
        $order   = Mage::getModel('sales/order')->loadByIncrementId($orderId);

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
        die();

    }
    //mg host + bitpayipn/index/bitpayipn
    public function bitpayipnAction()
    {
        if (isset($_POST)):

            #include our custom BP2 classes
            require 'classes/Config.php';
            require 'classes/Client.php';
            require 'classes/Item.php';
            require 'classes/Invoice.php';

            $all_data = json_decode(file_get_contents("php://input"), true);
            #
            $data  = $all_data['data'];
            $event = $all_data['event'];

            $orderid = $data['orderId'];

            $order_status  = $data['status'];
            $order_invoice = $data['id'];

            # print_r($event['name']);die();

            #check and see if its in the lookup
            $read       = Mage::getSingleton('core/resource')->getConnection('core_read');
            $prefix     = (string) Mage::getConfig()->getTablePrefix();
            $table_name = $prefix . 'bitpay_transactions';
            $sql        = "SELECT * FROM $table_name WHERE order_id = '$orderid' AND transaction_id = '$order_invoice' ";

            $result = $read->query($sql);
            $row    = $result->fetch();

            if ($row): #there is a record

                $env = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_endpoint');
                if ($env == 'test'):
                    $bitpay_token = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_devtoken');

                else:
                    $bitpay_token = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_prodtoken');
                endif;
                $config = new Configuration($bitpay_token, $env);
                #double check to make sure this is value
                $params                    = new stdClass();
                $params->extension_version = $this->getExtensionVersion();
                $params->invoiceID         = $order_invoice;
                $params->extension_version = $this->getExtensionVersion();

                $item = new Item($config, $params);

                $invoice = new Invoice($item); //this creates the invoice with all of the config params

                $orderStatus    = json_decode($invoice->checkInvoiceStatus($order_invoice));
                $invoice_status = $orderStatus->data->status;
                #lets update the db
                $write = Mage::getSingleton('core/resource')->getConnection('core_write');
                $sql   = "UPDATE $table_name SET transaction_status = '$invoice_status' WHERE order_id = '$orderid' AND transaction_id = '$order_invoice'";
                $write->query($sql);

                switch ($event['name']) {
                    case 'invoice_completed':
                        #load the order to update
                        $order = new Mage_Sales_Model_Order();
                        $order->loadByIncrementId($orderid);

                        $order->addStatusHistoryComment('BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> processing has been completed.',
                            Mage_Sales_Model_Order::STATE_COMPLETE);
                        $order->save();
                        return true;
                        break;

                    case 'invoice_confirmed':
                        #load the order to update
                        $order = new Mage_Sales_Model_Order();
                        $order->loadByIncrementId($orderid);

                        #$order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
                        $order->addStatusHistoryComment('BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> is now processing.',
                            Mage_Sales_Model_Order::STATE_PROCESSING);
                        $order->save();
                        return true;
                        break;

                    case 'invoice_paidInFull':
                    default:
                        #load the order to update
                        $order = new Mage_Sales_Model_Order();

                        $order->loadByIncrementId($orderid);
                        $order->addStatusHistoryComment('BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> is now processing.',
                            Mage_Sales_Model_Order::STATE_PROCESSING);

                        return true;

                        break;

                    case 'invoice_failedToConfirm':
                        #load the order to update
                        $order = new Mage_Sales_Model_Order();

                        $order->loadByIncrementId($orderid);

                        #$order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
                        $order->addStatusHistoryComment('BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> has become invalid because of network congestion.  Order will automatically update when the status changes.',
                            Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                        $order->save();
                        return true;
                        break;

                    case 'invoice_expired':
                        #load the order to update
                        $order = new Mage_Sales_Model_Order();

                        $order->loadByIncrementId($orderid);

                        $order->addStatusHistoryComment('BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> has expired, order has been canceled.',
                            Mage_Sales_Model_Order::STATE_CANCELED);
                        $order->save();
                        return true;
                        break;

                    case 'invoice_refundComplete':
                        #load the order to update

                        $order = new Mage_Sales_Model_Order();

                        $order->loadByIncrementId($orderid);

                        $order->addStatusHistoryComment('BitPay Invoice <a href = "http://' . $item->endpoint . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> has been refunded.',
                            Mage_Sales_Model_Order::STATE_CLOSED);

                        $order->save();

                        return true;
                        break;
                }
            endif; #end of row checker
        endif;
    }

    public function getExtensionVersion()
    {
        #return 'Magento1_2.0';
        return 'Magento1_' . (string) Mage::getConfig()->getNode()->modules->BitpayCheckout_BPCheckout->version;
    }
}
