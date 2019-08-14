<?php

/** Version 1.0.42 Last updated by Kire on 24/08/2016 **/
ini_set('display_errors', 1);
ini_set('max_execution_time', 1800);
ini_set('memory_limit', '2G');
include_once '../app/Mage.php';
umask(0);
Mage::app();

$ir = new IntelligentReach();
$ir->run();

class IntelligentReach
{
	private $_versionNumber = "1.0.42";
	private $_lastUpdated = "24/08/2016";
	private $_outputDirectory = "output";
	private $_fileName = "Feed";
	private $_fileNameTemp = "";
	private $_amountOfProductsPerPage = 1000;
	private $_categories = null;
	private $_intelligentReachCategoryExclusions = null;
	private $_parentProducts = array();

	private $_gzipFile = true;
	private $_includeAllParentFields = false;
	private $_stripInvalidChars = false;
	private $_convertNumberToWord = false;
	private $_includeDisabled = false;
	private $_includeNonSimpleProducts = false;
	private $_maxParentProductCacheSize = 100;

	public function run()
	{
		$storeId = (isset($_GET["storeid"]))? $_GET["storeid"] : false;

		// If a store id was provided then print the products to the output.
		if ($this->storeIsSelected())
		{
			$this->_fileName = $this->_outputDirectory."/".$this->_fileName."_".$storeId.".xml"; // added store id to feed file name for multi store support.
			$this->_fileNameTemp = tempnam("", $this->_fileName);
			echo "Temp File created: ". $this->_fileNameTemp."<br />";
			$time = microtime(true);
			file_put_contents($this->_fileNameTemp, "<?xml version=\"1.0\" encoding=\"utf-8\"?>
					<products version=\"$this->_versionNumber\" type=\"cron\">", LOCK_EX);
			$this->runTheTask($storeId);
			file_put_contents($this->_fileNameTemp, '</products>', FILE_APPEND | LOCK_EX);

			if (!file_exists($this->_outputDirectory))
				mkdir($this->_outputDirectory);

			file_put_contents($this->_fileName, file_get_contents($this->_fileNameTemp), LOCK_EX);
			unlink($this->_fileNameTemp);
			echo "Finished Generating Feed file: '".$this->_fileName."'";

			if($this->_gzipFile)
				$this->gzCompressFile($this->_fileName);

			echo "<br />".((microtime(true) - $time))." secs";
			echo "<br />".(memory_get_usage(true))." bytes";
		}
		else
			$this->printStores();
	}

	// Check if a storeid parameter has been set, returns a boolean.
	public function storeIsSelected()
	{
		if (isset($_GET["storeid"]))
			return true;
		else
			return false;
	}

	// Gets all the stores on all websites,
	// returns a table containing Store Ids and Store Names.
	public function getStores()
	{
		$websiteStores = Mage::app()->getStores();
		echo "<table cellspacing='2px;' border='1px;' cellpadding='8px;'>";
		echo "<tr><th>Store Id</th><th>Store Name</th></tr>";
		foreach ($websiteStores as $store)
			echo "<tr><td>" . $store->getId() . "</td><td><a href='?storeid=" . $store->getId() . "'>" . $store->getName() . "</a></td></tr>";
		echo "</table>";
	}

	public function printStores()
	{
		echo "<p>Sorry a Store Id was not provided, please choose a store from the options below.</p>";
		$this->getStores();
		echo "<p>If you want to skip this step in the future, you can manually enter the Store Id in the URL.<br />";
		echo "e.g. http://www.exampledomain.com/intelligentreach_integration.php?storeid=1</p>";
		echo "<p><strong>NB:</strong> The Store Id parameter name is case sensitive. Only use \"storeid=\" not another variation.</p>";
		echo "<h3>Other options</h3>";
		echo "<p>To enable the stripping of invalid XML characters set the <strong>'_stripInvalidChars'</strong> property to true</p>";
		echo "<p>To enable the converting of the first character in the XML tag from a number to a word, set the <strong>'_convertNumberToWord'</strong> property to true.</p>";
		echo "<p>To return all the parent product fields, set the <strong>'_includeAllParentFields'</strong> property to true.</p>";
		echo "<p>To include disabled products in the feed, set the <strong>'_includeDisabled'</strong> property to true.</p>";
		echo "<p>To include products of all types in the feed, set the <strong>'_includeNonSimpleProducts'</strong> property to true.</p>";
		echo "<h5>Version $this->_versionNumber <br />Last updated on $this->_lastUpdated</h5></div>";
	}

	/**
	 * @param $page
	 * @param $storeId
	 * @return Mage_Catalog_Model_Resource_Product_Collection
	 */
	public function getProducts($page,$storeId)
	{
		if($storeId)
			Mage::app()->setCurrentStore($storeId);

		$products = $this->getProductCollection($storeId);

		// use limit otherwise resource iterator will ignore the page unless we load the collection first.
		// previously this was loaded when count was called.
		$products->getSelect()
			->limit($this->_amountOfProductsPerPage,($page - 1) * $this->_amountOfProductsPerPage);

		return $products;
	}

	public function getProductCollection($storeId)
	{
		$products = Mage::getModel('catalog/product')
					->getCollection()
					->addStoreFilter($storeId);
		return $this->addAdditionalAttributeFilters($products);
	}
	
	public function addAdditionalAttributeFilters($products)
	{
		if($this->_includeDisabled)
			$products->addAttributeToFilter('status', array('gt' => 0));
		else
			$products->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));
		
		if(!$this->_includeNonSimpleProducts)
			$products->addAttributeToFilter('type_id', array('eq' => 'simple'));
				
		return $products;
	}

	// Run the task
	public function runTheTask($storeId)
	{
		// build category map
		$this->_buildCategoryMap();
		$currentPage = 1;
		$lastPage = ceil($this->getProductCollection($storeId)->getSize() / $this->_amountOfProductsPerPage);

		echo $lastPage. " pages <br />";
		do{
			$products = $this->getProducts($currentPage,$storeId);

			echo "Starting page $currentPage of $lastPage ..";
			Mage::getSingleton('core/resource_iterator')
				->walk($products->getSelect(), array(array($this, 'printProducts')),array('store_id' => $storeId));
			$currentPage++;

			$products->clear();
			unset($products);

			$this->clearParentProductCache();

			ob_flush();
			flush();
			echo " ".(memory_get_usage(true))." bytes ";
			echo ".. Finished (ok) <br />";

		}while($currentPage <= $lastPage);
	}

	public function printProducts($args)
	{
		$parentIds = null;
		$baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
		$feedData = "";

		$product = Mage::getModel('catalog/product')->load($args['row']['entity_id']);
		if ($product->getTypeId() == 'simple')
		{
			// use singleton saves re instantiating new model
			$parentIds = Mage::getSingleton('catalog/product_type_grouped')->getParentIdsByChild($product->getId());
			if (!$parentIds)
				$parentIds = Mage::getSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
			if (isset($parentIds[0]))
				$parentProduct = $this->getParentProduct($parentIds[0]);// use already loaded parent if available
		}
		
		if((($product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED) 
			|| ((isset($parentProduct)) && ($parentProduct->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED)))
				&& !$this->_includeDisabled)
		{
			return;
		}
		
		$feedData .= '<product>';
		foreach ($product->getData() as $key => $value)
		{
			if ($key !== 'stock_item')
			{
				if ($product->getResource()->getAttribute($key) != null)
					$value = $product->getResource()->getAttribute($key)->getFrontend()->getValue($product);

				if (($key == 'url_path') || ($key == 'url_key'))
					$value = trim(str_replace('/intelligentreach_integration.php', '', $product->getProductUrl()));

				if ($key == 'image')
					$value = $baseUrl . "media/catalog/product" . $value;

				if ($key == 'thumbnail')
					$value = $baseUrl . "media/catalog/product" . $value;

				if (($value == '') && (isset($parentProduct)))
				{
					$attr = $parentProduct->getResource()->getAttribute($key);
					if (!is_object($attr))
						continue;
					$value = $attr->getFrontend()->getValue($parentProduct);
				}
				// Print out all media gallery images.
				if($key == 'media_gallery')
				{
					for($i = 0; $i < count($value['images']); $i++)
						$feedData .=  " <image_".($i + 1)."><![CDATA[". $baseUrl . "media/catalog/product" . $value['images'][$i]['file']."]]></image_".($i + 1).">";
					continue;
				}
				if($key == 'status')
				{
					if((isset($parentProduct)) && ($parentProduct->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED))
						$value = "Disabled";
				}
				if($key == 'special_price')
				{
					$specialPriceEnabledValue = is_null($value) ? 0 : 1;
					$fromDate = $product->getResource()->getAttribute('special_from_date')->getFrontend()->getValue($product);
					$toDate = $product->getResource()->getAttribute('special_to_date')->getFrontend()->getValue($product);

					if($fromDate != null)
						$specialPriceEnabledValue = (strtotime($fromDate) <= strtotime(date('Y-m-d'))) ? 1 : 0;
					if($toDate != null)
						$specialPriceEnabledValue = (strtotime(date('Y-m-d')) <= strtotime($toDate)) ? 1 : 0;

					$feedData .= "<special_price_enabled><![CDATA[".$specialPriceEnabledValue."]]></special_price_enabled>";
				}
				if(is_array($value))
				{
					foreach($value as $vkey => $vvalue)
					{
						foreach($vvalue as $pkey => $pvalue)
							$feedData .=  "<".$key."_".$vkey."_".$pkey."><![CDATA[".$pvalue."]]></".$key."_".$vkey."_".$pkey.">";
					}
					continue;
				}

				$value = $this->encodeValue($value);
				$value = $this->stripInvalidXMLCharacters($value);
				$value = "<![CDATA[$value]]>";

				$key = str_replace('"', '', $key);
				if(is_numeric($key[0]))
					$key = $this->convertNumberToWord($key[0]).substr($key, 1);
				$feedData .=  '<' . $key . '>' . $value . '</' . $key . '>';
			}
		}

		if(isset($parentProduct))
		{
			if($this->_includeAllParentFields)
			{
				file_put_contents($this->_fileNameTemp, $feedData, FILE_APPEND | LOCK_EX);
				$feedData = "";
				$this->printAllParentFields($parentProduct);
			}
			else
			{
				$feedData .=  '<ir_parent_entity_id><![CDATA['.$this->stripInvalidXMLCharacters($parentProduct->getId()).']]></ir_parent_entity_id>';
				$feedData .=  '<ir_parent_sku><![CDATA['.$this->stripInvalidXMLCharacters($parentProduct->getSku()).']]></ir_parent_sku>';
				$feedData .=  '<ir_parent_url><![CDATA[' . $this->stripInvalidXMLCharacters(trim(str_replace('/intelligentreach_integration.php', '', $parentProduct->getProductUrl()))) . ']]></ir_parent_url>';
				$feedData .=  '<ir_parent_image><![CDATA['.$this->stripInvalidXMLCharacters($baseUrl . 'media/catalog/product' . $parentProduct->getImage()).']]></ir_parent_image>';
				$feedData .=  '<ir_parent_description><![CDATA['.$this->stripInvalidXMLCharacters($this->encodeValue($parentProduct->getDescription())).']]></ir_parent_description>';
			}
			$gallery = $parentProduct->getMediaGallery();
			if(count($gallery['images']) != 0)
			{
				for($i = 0; $i < count($gallery['images']); $i++)
					$feedData .=  " <ir_parent_image_".($i + 1)."><![CDATA[". $baseUrl . "media/catalog/product" . $gallery['images'][$i]['file']."]]></ir_parent_image_".($i + 1).">";
			}
			$feedData .= $this->getCategoryData($product,$args['store_id'],$parentProduct);
		}

		$feedData .=  '</product>'.PHP_EOL;
		if (is_object($parentIds))
			unset($parentIds);

		$product->clearInstance(); // unset by itself doesn't clear some variables you need to let the model handle this itself
		unset($product);
		file_put_contents($this->_fileNameTemp, $feedData, FILE_APPEND | LOCK_EX);
	}

	private function _buildCategoryMap()
	{
		$categories = Mage::getModel('catalog/category')
						->getCollection()
						->addAttributeToSelect("level", "path", "entity_id")
						->addNameToResult()
						->addOrder("level");

		$categories->setPageSize(100);

		$lastPageNumber = $categories->getLastPageNumber();
		$currentPage = 1;
		do{
			$categories->setCurPage($currentPage);
			foreach($categories as $category)
			{
				$this->_categories[$category->getId()] =
					array(
						"name" => $category->getName(),
						"path_in_store" => $category->getPathInStore()
					);
				$category->getName();
			}

			$categories->clear();
			$currentPage++;

		}while($currentPage <= $lastPageNumber);
		return $this->_categories;
	}

	public function getCategoryData($product, $storeId, $parentProduct)
	{
		$feedData = '';
		$categories = $product->getCategoryIds();

		if((count($categories) == 0) && isset($parentProduct))
			$categories = $parentProduct->getCategoryIds();
		if((count($categories) == 0) || !isset($this->_categories[$categories[0]]))
			return;

		$output = $this->getCategoryPath($categories[0]);

		if($output != "") 
		{
			/** Old Category Path code: will be deleted in the future. **/
			$feedData .= '<category_path><![CDATA['.$output.']]></category_path>';
			/** End of Old Category path code **/
			/** New Category Path code **/
			$feedData .= '<ir_category_path><![CDATA['.$output.']]></ir_category_path>';
			/** End of New Category Path code **/
		}

		/** New longest Category Path code **/
		$validCategoryPaths = array();
		foreach($categories as $cat)
		{
			$catPath = '';
			if(isset($this->_categories[$cat]))
				$catPath = $this->getCategoryPath($cat);

			if($catPath != "")
			{
				if($this->getIntelligentReachCategoryExclusions($storeId) != "")
				{
					if(preg_match('/('.$this->getIntelligentReachCategoryExclusions($storeId).')/i', $catPath) != true)
						array_push($validCategoryPaths, $catPath);
				}
				else
					array_push($validCategoryPaths, $catPath);
			}
		}

		if(count($validCategoryPaths) != 0)
		{
			if(count($validCategoryPaths) > 1)
				usort($validCategoryPaths, function ($a, $b) { return (strlen($a) < strlen($b)); });
			$feedData .=  "<ir_longest_category_path><![CDATA[".$validCategoryPaths[0]."]]></ir_longest_category_path>";
		}
		else if($output != "")
			$feedData .=  '<ir_longest_category_path><![CDATA['.$output.']]></ir_longest_category_path>';

		/** End of New longest Category Path code **/

		return $feedData;
	}

	public function getIntelligentReachCategoryExclusions($storeId)
	{
		if($this->_intelligentReachCategoryExclusions == null)
		{
			$this->_intelligentReachCategoryExclusions = Mage::getModel('core/variable')
															->setStoreId($storeId)
															->loadByCode('intelligent_reach_category_exclusions')
															->getValue();
		}
		return $this->_intelligentReachCategoryExclusions;
	}

	public function printAllParentFields($parentProduct)
	{
		$baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
		$parentFeedData = "";
		foreach ($parentProduct->getData() as $key => $value)
		{
			if ($parentProduct->getResource()->getAttribute($key) != null)
				$value = $parentProduct->getResource()->getAttribute($key)->getFrontend()->getValue($parentProduct);

			if (($key == 'url_path') || ($key == 'url_key'))
				$value = trim(str_replace('/intelligentreach_integration.php', '', $parentProduct->getProductUrl()));

			if ($key == 'image')
				$value = $baseUrl . "media/catalog/product" . $value;

			if ($key == 'thumbnail')
				$value = $baseUrl . "media/catalog/product" . $value;

			if(is_array($value))
			{
				foreach($value as $vkey => $vvalue)
				{
					foreach($vvalue as $pkey => $pvalue)
						$parentFeedData .=  "<".$key."_".$vkey."_".$pkey."><![CDATA[".$pvalue."]]></".$key."_".$vkey."_".$pkey.">";
				}
				continue;
			}
			$value = $this->encodeValue($value);
			$value = $this->stripInvalidXMLCharacters($value);

			$value = "<![CDATA[$value]]>";

			$key = str_replace('"', '', $key);
			if(is_numeric($key[0]))
				$key = $this->convertNumberToWord($key[0]).substr($key, 1);
			$parentFeedData .=  '<ir_parent_' . $key . '>' . $value . '</ir_parent_' . $key . '>';
		}
		file_put_contents($this->_fileNameTemp, $parentFeedData, FILE_APPEND | LOCK_EX);
	}

	public function stripInvalidXMLCharacters($value)
	{
		if(!$this->_stripInvalidChars)
			return $value;
		return preg_replace("/[^A-Za-z0-9\d\!\"\$%^&*:;?'@~#{}|`()\\/.,_+=\-<>\s]/u", '', $value);
	}

	public function getParentProduct($parentId)
	{
		if(count($this->_parentProducts) >= $this->_maxParentProductCacheSize)
			$this->clearParentProductCache();
		if(!isset($this->_parentProducts[$parentId]))
			$this->_parentProducts[$parentId] = Mage::getModel('catalog/product')->load($parentId);
		return $this->_parentProducts[$parentId];
	}

	public function convertNumberToWord($number)
	{
		if(!$this->_convertNumberToWord)
			return $number;
		$dictionary = array( 0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine');
		return $dictionary[$number];
	}
	
	public function encodeValue($value)
	{
		if(version_compare(PHP_VERSION, '5.4.0', '>='))
			return htmlentities($value, ENT_COMPAT | ENT_SUBSTITUTE, "UTF-8");
		else
			return htmlentities($value, ENT_COMPAT, "UTF-8");
	}

	public function clearParentProductCache()
	{
		foreach($this->_parentProducts as $parentProduct)
			$parentProduct->clearInstance();
		$this->_parentProducts = array(); // clear parent products		
	}

	public function getCategoryPath($categoryPath)
	{
		$categoryData = $this->_categories[$categoryPath];
		$categoryList = array_reverse(explode(',',$categoryData['path_in_store']));

		$catPath = "";
		foreach($categoryList as $cat)
		{
			if(isset($this->_categories[$cat]))
			{
				$catPath .= $this->_categories[$cat]['name'];
				if ($cat !== end($categoryList))
					$catPath .= ' > ';
			}
		}
		return $catPath;
	}

	/**
	* GZIPs a file on disk (appending .gz to the name)
	*
	* From http://stackoverflow.com/questions/6073397/how-do-you-create-a-gz-file-using-php
	* Based on function by Kioob at:
	* http://www.php.net/manual/en/function.gzwrite.php#34955
	*
	* @param string $source Path to file that should be compressed
	* @param integer $level GZIP compression level (default: 9)
	* @return string New filename (with .gz appended) if success, or false if operation fails
	*/
	public function gzCompressFile($source, $level = 9)
	{
		$dest = $source . '.gz';
		$mode = 'wb' . $level;
		$error = false;
		if ($fp_out = gzopen($dest, $mode)) {
			if ($fp_in = fopen($source,'rb')) {
				while (!feof($fp_in))
					gzwrite($fp_out, fread($fp_in, 1024 * 512));
				fclose($fp_in);
			} else {
				$error = true;
			}
			gzclose($fp_out);
		} else {
			$error = true;
		}
		if ($error)
			return false;
		else
			return $dest;
	}
}

