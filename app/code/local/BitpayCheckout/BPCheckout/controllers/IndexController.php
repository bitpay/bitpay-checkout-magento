<?php

require_once 'app/code/local/BitpayCheckout/BPCheckout/controllers/Paymentcontroller.php';

class BitpayCheckout_BPCheckout_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        echo '<h2>You should not be here.</h2>';
        die();

    }

    public function redirectAction($modal = null,$orderId = null)
    {
        #include our custom BP2 classes
        require 'classes/Config.php';
        require 'classes/Client.php';
        require 'classes/Item.php';
        require 'classes/Invoice.php';
      
        $order = new Mage_Sales_Model_Order();

        if($modal != null && $orderId != null):
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
        $params = new stdClass();
        $params->price = $order->base_grand_total;
        $params->currency = $order->base_currency_code; //set as needed

        $bitpay_currency = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_currency');
        switch ($bitpay_currency) {
            default:
            case 1:
                break;
            case 'BTC':
                $params->buyerSelectedTransactionCurrency = 'BTC';
                break;
            case 'BCH':
                $params->buyerSelectedTransactionCurrency = 'BCH';
                break;
        }
        $params->orderId = trim($orderId);
        $params->redirectURL = Mage::getBaseUrl().'sales/order/view/order_id/'.$shortOrderID.'/';
        #ipn
        $params->notificationURL = Mage::getBaseUrl( Mage_Core_Model_Store::URL_TYPE_WEB, true ) . 'bitpayipn/index/bitpayipn';

        $cartFix = Mage::getBaseUrl().'cartfix/index/renewcart/orderid/'.$orderId;
        $item = new Item($config, $params);

        $invoice = new Invoice($item);

        //this creates the invoice with all of the config params from the item
        $invoice->createInvoice();
        $invoiceData = json_decode($invoice->getInvoiceData());
        //now we have to append the invoice transaction id for the callback verification
        $invoiceID = $invoiceData->data->id;
        $use_modal = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_ux');
       
        Mage::getSingleton('checkout/cart')->truncate();
        Mage::getSingleton('checkout/cart')->save();
       
        switch ($use_modal) {
            default:
            case 'redirect':
                Mage::app()->getResponse()->setRedirect($invoice->getInvoiceURL())->sendResponse();
                return;
                break;
            case 'modal':
            $modal_obj =  new stdClass();
            $modal_obj->redirectURL = $params->redirectURL;
            $modal_obj->notificationURL = $params->notificationURL;
            $modal_obj->cartFix = $cartFix;
            $modal_obj->invoiceID = $invoiceID;
            echo json_encode($modal_obj);
            

            return;
            break;
        }

       
    }
    public function modalAction()
    {
        $orderId = Mage::getModel("sales/order")->getCollection()->getLastItem()->getIncrementId();
        BitpayCheckout_BPCheckout_PaymentController::redirectAction(true, $orderId);

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
           
            $data = json_decode(file_get_contents("php://input"), true);
            $orderid = $data['orderId'];
            $order_status = $data['status'];
            $order_invoice = $data['id'];
            
            $env = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_endpoint');
            if ($env == 'test'):
                $bitpay_token = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_devtoken');

            else:
                $bitpay_token = Mage::getStoreConfig('payment/bitpaycheckout/bitpay_prodtoken');
            endif;
            $config = new Configuration($bitpay_token, $env);
            #test invoice
            #$order_invoice = 'VWtKRnryKGfb9JsMTcXhbF';
            #double check to make sure this is value
            $params = new stdClass();
            $params->invoiceID = $order_invoice;

            $item = new Item($config, $params);

            $invoice = new Invoice($item); //this creates the invoice with all of the config params
           
            
            $orderStatus = json_decode($invoice->checkInvoiceStatus($order_invoice));
            if ($orderStatus->data->status == 'complete'):
                #load the order to update
                $order = new Mage_Sales_Model_Order();

                #test orderid
                #$orderid = '100000319';
                $order->loadByIncrementId($orderid);
                

                #$order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
                $order->addStatusHistoryComment('BitPay Invoice <a href = "http://'.$item->endpoint.'/dashboard/payments/'.$order_invoice.'" target = "_blank">'.$order_invoice.'</a> processing has been completed.',
                Mage_Sales_Model_Order::STATE_COMPLETE);
                $order->save();
                return true;
                

            endif;

            if ($orderStatus->data->status == 'confirmed'):
                #load the order to update
                $order = new Mage_Sales_Model_Order();

                #test orderid
                #$orderid = '100000319';
                $order->loadByIncrementId($orderid);
                

                #$order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
                $order->addStatusHistoryComment('BitPay Invoice <a href = "http://'.$item->endpoint.'/dashboard/payments/'.$order_invoice.'" target = "_blank">'.$order_invoice.'</a> is now processing.',
                Mage_Sales_Model_Order::STATE_PROCESSING);
                $order->save();
                return true;
                

            endif;

            if ($orderStatus->data->status == 'paid'):
                #load the order to update
                $order = new Mage_Sales_Model_Order();

                #test orderid
                #$orderid = '100000319';
                $order->loadByIncrementId($orderid);
                $order->addStatusHistoryComment('BitPay Invoice <a href = "http://'.$item->endpoint.'/dashboard/payments/'.$order_invoice.'" target = "_blank">'.$order_invoice.'</a> is now processing.', 
                Mage_Sales_Model_Order::STATE_PROCESSING);

                return true;

            endif;

        else:
            return false;
        endif;
    }
}
