<?php
class BitpayCheckout_BPCheckout_Block_Form_BPCheckout extends Mage_Payment_Block_Form
{
  protected function _construct()
  {
    parent::_construct();
    $this->setTemplate('bpcheckout/form/bpcheckout.phtml');
  }
}