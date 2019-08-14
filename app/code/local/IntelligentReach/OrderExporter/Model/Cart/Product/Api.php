<?php
  //Shopping Cart Product API required for the Intelligent Reach platform marketplace integration (http://www.intelligentreach.com).
  //Copyright © 2016 Intuitive Search Technologies Ltd.
  //Release under OSL license (http://opensource.org/licenses/osl-3.0).
  //
class IntelligentReach_OrderExporter_Model_Cart_Product_Api extends Mage_Checkout_Model_Cart_Product_Api
{
	 /**
	 * This is the version number of the IR Custom Cart_Product_Api.
	 * It should only be incremented when this section of the Api changes.
	 *
	 * @return double
	 */
	public function irApiVersion()
	{
		return 1.0;
	}
	
    /**
     * @param  $quoteId
     * @param  $productsData
     * @param  $store
     * @return bool
     */
    public function addWithPrice($quoteId, $productsData, $store=null)
    {
        $quote = $this->_getQuote($quoteId, $store);
        if (empty($store)) {
            $store = $quote->getStoreId();
        }

        $productsData = $this->_prepareProductsData($productsData);
        if (empty($productsData)) {
            $this->_fault('invalid_product_data');
        }

        $errors = array();
        foreach ($productsData as $productItem) {
			$productItem = get_object_vars($productItem);	
            if (isset($productItem['product_id'])) {
                $productByItem = $this->_getProduct($productItem['product_id'], $store, "id");
            } else if (isset($productItem['sku'])) {
                $productByItem = $this->_getProduct($productItem['sku'], $store, "sku");
            } else {
                $errors[] = Mage::helper('checkout')->__("One item of products do not have identifier or sku");
                continue;
            }
            $productRequest = $this->_getProductRequest($productItem);
            try {
                $result = $quote->addProduct($productByItem, $productRequest);
				if(isset($productItem['price']) && $productItem['price'] != null) {
					$result->setOriginalCustomPrice($productItem['price']);
				}
				
                if (is_string($result)) {
                    Mage::throwException($result);
                }
            } catch (Mage_Core_Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $this->_fault("add_product_fault", implode(PHP_EOL, $errors));
        }

        try {
            $quote->collectTotals()->save();
        } catch(Exception $e) {
            $this->_fault("add_product_quote_save_fault", $e->getMessage());
        }

        return true;
    }
}
