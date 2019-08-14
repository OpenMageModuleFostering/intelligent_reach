<?php
  //Shopping Cart API required for the Intelligent Reach platform marketplace integration (http://www.intelligentreach.com).
  //Copyright © 2016 Intuitive Search Technologies Ltd.
  //Release under OSL license (http://opensource.org/licenses/osl-3.0).
  //
class IntelligentReach_IRShipping_Model_Cart_Api extends Mage_Checkout_Model_Cart_Api
{
    /**
     * Create an order from the shopping cart (quote)
     *
     * @param  $quoteId
     * @param  $store
     * @param  $agreements array
     * @return string
     */
    public function createOrder($quoteId, $store = null, $agreements = null)
    {
        $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
        if (!empty($requiredAgreements)) {
            $diff = array_diff($agreements, $requiredAgreements);
            if (!empty($diff)) {
                $this->_fault('required_agreements_are_not_all');
            }
        }

        $quote = $this->_getQuote($quoteId, $store);
        if ($quote->getIsMultiShipping()) {
            $this->_fault('invalid_checkout_type');
        }
        if ($quote->getCheckoutMethod() == Mage_Checkout_Model_Api_Resource_Customer::MODE_GUEST
                && !Mage::helper('checkout')->isAllowedGuestCheckout($quote, $quote->getStoreId())) {
            $this->_fault('guest_checkout_is_not_enabled');
        }

        /** @var $customerResource Mage_Checkout_Model_Api_Resource_Customer */
        $customerResource = Mage::getModel("checkout/api_resource_customer");
        $isNewCustomer = $customerResource->prepareCustomerForQuote($quote);
								
        try {
            $quote->collectTotals();
            /** @var $service Mage_Sales_Model_Service_Quote */
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();

            if ($isNewCustomer) {
                try {
                    $customerResource->involveNewCustomer($quote);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            $order = $service->getOrder();
            if ($order) {
                Mage::dispatchEvent('checkout_type_onepage_save_order_after',
                    array('order' => $order, 'quote' => $quote));

                try {
                    $order->sendNewOrderEmail();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            Mage::dispatchEvent(
                'checkout_submit_all_after',
                array('order' => $order, 'quote' => $quote)
            );
						
						$sessionId = $this->_getSession()->getSessionId();
						$directory = Mage::getBaseDir('var');
						$directory = str_replace(DIRECTORY_SEPARATOR, '/', $directory);
						$directory = $directory.'/intelligentreach_temp_files';
						$filepath = $directory.'/custom_shipping_info_'.$sessionId.'.txt';
						if(file_exists($filepath))
							unlink($filepath);							
						
        } catch (Mage_Core_Exception $e) {
            $this->_fault('create_order_fault', $e->getMessage());
        }

        return $order->getIncrementId();
    }
}
