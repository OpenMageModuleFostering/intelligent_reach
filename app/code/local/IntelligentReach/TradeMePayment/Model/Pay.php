<?php
  //Payment Method required for the Intelligent Reach platform marketplace integration (http://www.intelligentreach.com).
  //Copyright © 2015 Intuitive Search Technologies Ltd.
  //Release under OSL license (http://opensource.org/licenses/osl-3.0).
  //
class IntelligentReach_TradeMePayment_Model_Pay extends Mage_Payment_Model_Method_Abstract
{

    protected $_code  = 'trademepayment';

	 /**
     * Payment Method features
     * @var bool
     */
	protected $_canUseInternal = true;
	protected $_canUseCheckout = false; 
	protected $_canUseForMultishipping = false;
}
?>
