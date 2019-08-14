<?php
  //Payment Method required for the Intelligent Reach platform marketplace integration (http://www.intelligentreach.com).
  //Copyright © 2014 Intuitive Search Technologies Ltd.
  //Release under OSL license (http://opensource.org/licenses/osl-3.0).
  //
class IntelligentReach_EbayPayment_Model_Pay extends Mage_Payment_Model_Method_Abstract
{

    protected $_code  = 'ebaypayment';
    //protected $_formBlockType = 'payment/form_checkmo';
    //protected $_infoBlockType = 'payment/info_checkmo';

	 /**
     * Payment Method features
     * @var bool
     */
	protected $_canUseInternal = true;
	protected $_canUseCheckout = false; 

}
?>
