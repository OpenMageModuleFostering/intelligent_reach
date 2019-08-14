<?php
  //Quote Address Total Shipping Model required for the Intelligent Reach platform marketplace integration (http://www.intelligentreach.com).
  //Copyright © 2016 Intuitive Search Technologies Ltd.
  //Release under OSL license (http://opensource.org/licenses/osl-3.0).
  //
class IntelligentReach_OrderExporter_Model_Quote_Address_Total_Shipping extends Mage_Sales_Model_Quote_Address_Total_Shipping
{
    /**
     * Collect totals information about shipping
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  Mage_Sales_Model_Quote_Address_Total_Shipping
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        parent::collect($address);

        $oldWeight = $address->getWeight();
        $address->setWeight(0);
        $address->setFreeMethodWeight(0);
        $this->_setAmount(0)
            ->_setBaseAmount(0);

        $items = $this->_getAddressItems($address);
        if (!count($items)) {
            return $this;
        }

        $method     = $address->getShippingMethod();
        $freeAddress= $address->getFreeShipping();

        $addressWeight      = $address->getWeight();
        $freeMethodWeight   = $address->getFreeMethodWeight();

        $addressQty = 0;

        foreach ($items as $item) {
            /**
             * Skip if this item is virtual
             */
            if ($item->getProduct()->isVirtual()) {
                continue;
            }

            /**
             * Children weight we calculate for parent
             */
            if ($item->getParentItem()) {
                continue;
            }

            if ($item->getHasChildren() && $item->isShipSeparately()) {
                foreach ($item->getChildren() as $child) {
                    if ($child->getProduct()->isVirtual()) {
                        continue;
                    }
                    $addressQty += $child->getTotalQty();

                    if (!$item->getProduct()->getWeightType()) {
                        $itemWeight = $child->getWeight();
                        $itemQty    = $child->getTotalQty();
                        $rowWeight  = $itemWeight*$itemQty;
                        $addressWeight += $rowWeight;
                        if ($freeAddress || $child->getFreeShipping()===true) {
                            $rowWeight = 0;
                        } elseif (is_numeric($child->getFreeShipping())) {
                            $freeQty = $child->getFreeShipping();
                            if ($itemQty>$freeQty) {
                                $rowWeight = $itemWeight*($itemQty-$freeQty);
                            }
                            else {
                                $rowWeight = 0;
                            }
                        }
                        $freeMethodWeight += $rowWeight;
                        $item->setRowWeight($rowWeight);
                    }
                }
                if ($item->getProduct()->getWeightType()) {
                    $itemWeight = $item->getWeight();
                    $rowWeight  = $itemWeight*$item->getQty();
                    $addressWeight+= $rowWeight;
                    if ($freeAddress || $item->getFreeShipping()===true) {
                        $rowWeight = 0;
                    } elseif (is_numeric($item->getFreeShipping())) {
                        $freeQty = $item->getFreeShipping();
                        if ($item->getQty()>$freeQty) {
                            $rowWeight = $itemWeight*($item->getQty()-$freeQty);
                        }
                        else {
                            $rowWeight = 0;
                        }
                    }
                    $freeMethodWeight+= $rowWeight;
                    $item->setRowWeight($rowWeight);
                }
            }
            else {
                if (!$item->getProduct()->isVirtual()) {
                    $addressQty += $item->getQty();
                }
                $itemWeight = $item->getWeight();
                $rowWeight  = $itemWeight*$item->getQty();
                $addressWeight+= $rowWeight;
                if ($freeAddress || $item->getFreeShipping()===true) {
                    $rowWeight = 0;
                } elseif (is_numeric($item->getFreeShipping())) {
                    $freeQty = $item->getFreeShipping();
                    if ($item->getQty()>$freeQty) {
                        $rowWeight = $itemWeight*($item->getQty()-$freeQty);
                    }
                    else {
                        $rowWeight = 0;
                    }
                }
                $freeMethodWeight+= $rowWeight;
                $item->setRowWeight($rowWeight);
            }
        }

        if (isset($addressQty)) {
            $address->setItemQty($addressQty);
        }

        $address->setWeight($addressWeight);
        $address->setFreeMethodWeight($freeMethodWeight);

        $address->collectShippingRates();

        $this->_setAmount(0)
            ->_setBaseAmount(0);

        $method = $address->getShippingMethod();

        if ($method) {
						if(Mage::getSingleton('api/server')->getAdapter() != null)
						{
							$session = Mage::getSingleton('api/session');
							$sessionId = $session->getSessionId();
							$directory = Mage::getBaseDir('var');
							$directory = str_replace(DIRECTORY_SEPARATOR, '/', $directory);
							$filepath = $directory.'/intelligentreach_temp_files/custom_shipping_info_'.$sessionId.'.txt';
							if(file_exists($filepath))
								$customShippingInfo = json_decode(file_get_contents($filepath));
							else
							{
								if(Mage::getSingleton('api/session')->getUser()->getUsername() == 'IR')
									throw new Mage_Api_Exception('Custom shipping information does not exist');
							}
						}
										
            foreach ($address->getAllShippingRates() as $rate) {
                if ($rate->getCode()==$method) {
										if (isset($customShippingInfo)) 
										{
											$customPrice = $customShippingInfo->{'price'};
											$amountPrice = $address->getQuote()->getStore()->convertPrice($customPrice, false);
											$this->_setAmount($amountPrice);
											$this->_setBaseAmount($customPrice);
											$shippingDescription = $customShippingInfo->{'description'};
										}		
										else
										{
											$amountPrice = $address->getQuote()->getStore()->convertPrice($rate->getPrice(), false);
											$this->_setAmount($amountPrice);
											$this->_setBaseAmount($rate->getPrice());
											$shippingDescription = $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle();
										}
										
                    $address->setShippingDescription(trim($shippingDescription, ' -'));
                    break;
                }
            }
        }

        return $this;
    }
}
