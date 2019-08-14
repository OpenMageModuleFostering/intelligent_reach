<?php
  //Shopping Cart Shipping API required for the Intelligent Reach platform marketplace integration (http://www.intelligentreach.com).
  //Copyright © 2016 Intuitive Search Technologies Ltd.
  //Release under OSL license (http://opensource.org/licenses/osl-3.0).
  //
class IntelligentReach_IRShipping_Model_Cart_Shipping_Api extends Mage_Checkout_Model_Cart_Shipping_Api
{
    /**
     * Set a Shipping Method for Shopping Cart with custom price and description.
     *
     * @param  $quoteId
     * @param  $shippingMethod
		 * @param	 $price
		 * @param  $description
     * @param  $store
     * @return bool
     */
    public function setCustomShippingMethod($quoteId, $shippingMethod, $price, $description, $store = null)
    {
        $quote = $this->_getQuote($quoteId, $store);

        $quoteShippingAddress = $quote->getShippingAddress();
        if(is_null($quoteShippingAddress->getId()) ) {
            $this->_fault("shipping_address_is_not_set");
        }
								
				$this->writeShippingValuesToTempFile($price, $description);
								
        // force calling carrier collectRates() again
        $quote->getShippingAddress()->setCollectShippingRates(true);
				
        $rate = $quote->getShippingAddress()->collectShippingRates()->getShippingRateByCode($shippingMethod);
        if (!$rate) {
            $this->_fault('shipping_method_is_not_available');
        }

        try {
            $quote->getShippingAddress()->setShippingMethod($shippingMethod);
            $quote->collectTotals()->save();
        } catch(Mage_Core_Exception $e) {
            $this->_fault('shipping_method_is_not_set', $e->getMessage());
        }

        return true;
    }
				
		private function writeShippingValuesToTempFile($price, $description)
		{		
			$sessionId = $this->_getSession()->getSessionId();
			$customShippingInfo = '{"price":'.$price.',"description":"'.$description.'"}';
			if (!is_dir('var/intelligentreach_temp_files'))
				mkdir('var/intelligentreach_temp_files');
			file_put_contents("var/intelligentreach_temp_files/custom_shipping_info_".$sessionId.".txt", $customShippingInfo, FILE_APPEND | LOCK_EX);
		}
}
