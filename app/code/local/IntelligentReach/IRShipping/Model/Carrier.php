<?php
  //Shipping Method required for the Intelligent Reach platform marketplace integration (http://www.intelligentreach.com).
  //Copyright © 2016 Intuitive Search Technologies Ltd.
  //Release under OSL license (http://opensource.org/licenses/osl-3.0).
  //
class IntelligentReach_IRShipping_Model_Carrier
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'intelligentreach_irshipping';
				
		public function collectRates(Mage_Shipping_Model_Rate_Request $request)
		{
			if(Mage::getSingleton('api/server')->getAdapter() != null)
			{
				$result = Mage::getModel('shipping/rate_result');
				$result->append($this->_getIRShippingRate());
				return $result;
			}
		}
		
		protected function _getIRShippingRate()
    {
			$rate = Mage::getModel('shipping/rate_result_method');

			$rate->setCarrier($this->_code);
			$rate->setCarrierTitle('IR Standard Shipping');
			
			$rate->setMethod('standard');
			$rate->setMethodTitle('An error has occurred, please contact IntelligentReach for support.');
			$rate->setPrice('0.00');
			$rate->setCost('0.00');

			return $rate;
    }
		
		public function getAllowedMethods()
    {
			return array(
					'standard' => 'IR Standard Shipping',
			);
    }
}
?>
