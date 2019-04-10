<?php
class BitPayCheckout_BPCheckout_Model_Paymentmethod extends Mage_Payment_Model_Method_Abstract {
  protected $_code  = 'bitpaycheckout';
  #protected $_formBlockType = 'bpcheckout/form_bpcheckout';
 
  public function assignData($data)
  {
   
    $info = $this->getInfoInstance();
    if ($data->getBitpayTransactionId())
    {
      $info->setBitpayTransactionId($data->setBitpayTransactionId());
    }
    
    return $this;
  }
 
  public function validate()
  {
    parent::validate();
    $info = $this->getInfoInstance();
    if (!$info->getInfoInstance())

    return $this;
  }
 
  public function getOrderPlaceRedirectUrl()
  {
    if(Mage::getStoreConfig('payment/bitpaycheckout/bitpay_ux') == 'modal'):
    
    else:
      return Mage::getUrl('bitpaypaymentredirect/index/redirect', array('_secure' => false));

    endif;
  }
}
