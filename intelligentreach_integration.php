<?php

/** Version 1.0.41 Last updated by Kire on 10/08/2016 **/
ini_set('display_errors', 1);
ini_set('max_execution_time', 1800);
include_once 'app/Mage.php';
umask(0);
Mage::app();

$ir = new IntelligentReach();
$ir->run();

class IntelligentReach
{
	private $_splitby = 100;
	private $_amountOfProductsPerPage = 100;
	private $_lastPageNumber = 0;
	private $_versionNumber = "1.0.41";
	private $_lastUpdated = "10/08/2016";

	public function run() 
	{
		// If a store id was provided then print the products to the output.
		if ($this->storeIsSelected()) 
		{
			if (isset($_GET["splitby"]))
				$this->_splitby = $_GET["splitby"];
			if (isset($_GET["amountofproducts"]))
				$this->_amountOfProductsPerPage = $_GET["amountofproducts"];
			$this->_lastPageNumber = ceil($this->getProductCollection()->getSize() / $this->_amountOfProductsPerPage);

			if ((isset($_GET["startingpage"]) && isset($_GET["endpage"])) || isset($_GET["getall"])) 
			{
				header("Content-Type: text/xml; charset=UTF-8");
				header("Cache-Control: no-cache, must-revalidate");
				echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>
					  <products version=\"$this->_versionNumber\" type=\"web\">";
						$this->runTheTask(isset($_GET["getall"]) ? 1 : $_GET["startingpage"], isset($_GET["getall"]) ? $this->_lastPageNumber : $_GET["endpage"]);
				echo '</products>';
			}
			else
				$this->printSections();
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
			echo "<tr><td>" . $store->getId() . "</td><td><a href='?storeid=" . $store->getId() . "&splitby=100&amountofproducts=100'>" . $store->getName() . "</a></td></tr>";
		echo "</table>";
	}

	public function printStores() 
	{
		echo "<p>Sorry a Store Id was not provided, please choose a store from the options below.</p>";
		$this->getStores();
		echo "<p>If you want to skip this step in the future, you can manually enter the Store Id in the URL.<br />";
		echo "e.g. http://www.exampledomain.com/intelligentreach_integration.php?storeid=1</p>";
		echo "<p><strong>NB:</strong> The Store Id parameter name is case sensitive. Only use \"storeid=\" not another variation.</p>";
		echo "<h5>Version $this->_versionNumber <br />Last updated on $this->_lastUpdated</h5></div>";
	}

	public function getSections($sections)
	{
		$convertNumberToWord = (isset($_GET["convertNumberToWord"])) ? "&convertNumberToWord=1" : "";
		$stripInvalidChars = (isset($_GET["stripInvalidChars"])) ? "&stripInvalidChars=1" : "";
		$includeAllParentFields = (isset($_GET["includeAllParentFields"])) ? "&includeAllParentFields=1" : "";
		$includeDisabled = (isset($_GET["includeDisabled"])) ? "&includeDisabled=1" : "";
		$includeNonSimpleProducts = (isset($_GET["includeNonSimpleProducts"])) ? "&includeNonSimpleProducts=1" : "";

		$pages = $this->_lastPageNumber;
		echo "<table cellspacing='2px;' border='1px;' cellpadding='8px;'>";
		echo "<tr><th>Section</th><th>Pages</th></tr>";
		for ($i = $sections; $i > 0; $i--) 
		{
			$startingPage = $pages - $this->_splitby + 1;
			if ($startingPage < 1)
				$startingPage = 1;

			echo "<tr><td><a href='?storeid=" . $_GET["storeid"] . "&startingpage=" . $startingPage . "&endpage=" . $pages . "&splitby=".$this->_splitby."&amountofproducts=".$this->_amountOfProductsPerPage.$convertNumberToWord.$stripInvalidChars.$includeAllParentFields.$includeDisabled.$includeNonSimpleProducts."'>" . $i . "</a></td><td>" . $startingPage . "-" . $pages . "</td></tr>";
			$pages = $startingPage - 1;
		}
		echo "</table>";
	}

	public function printSections() 
	{
		$sections = ceil($this->_lastPageNumber / $this->_splitby);
		echo "<h2>Please select a section to return the products.</h2>";
		echo "<div class='sections' style='float:left; padding-left:50px;'>";
		$this->getSections($sections);
		echo "</div>";
		echo "<div class='instructions' style='float:left; padding-left:50px;'>";
		echo "<h3>Instructions</h3>";
		echo "<p>The parameter <strong>'splitby'</strong> in the URL splits pages into sections, each page contains (unless specified otherwise) the default amount of 100 products.</p>";
		echo "<p>So setting <strong>'splitby'</strong> to equal 100 will bring back 1,000 products per page and 10,000 products per section, if there are 40,000 products in the store then this will return 4 sections. </p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&<strong>splitby=100</strong></p>";
		echo "<p>You can also set the value of the number of products per page that is returned, by setting the parameter <strong>'amountofproducts'</strong> in the URL</p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&splitby=100&<strong>amountofproducts=100</strong></p>";
		echo "<p><strong>NB:</strong> The default value for <strong>'splitby'</strong> is 100 and for <strong>'amountofproducts'</strong> is 100.</p>";
		echo "<h3>Other options</h3>";
		echo "<p>You can retrieve all products by using the <strong>'getall'</strong> parameter</p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&<strong>getall=1</strong></p>";
		echo "<p>To enable the stripping of invalid XML characters add the <strong>'stripInvalidChars'</strong> parameter</p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&<strong>stripInvalidChars=1</strong></p>";
		echo "<p>To enable the converting of the first character in the XML tag from a number to a word, use the <strong>'convertNumberToWord'</strong> parameter.</p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&<strong>convertNumberToWord=1</strong></p>";
		echo "<p>To return all the parent product fields, use the <strong>'includeAllParentFields'</strong> parameter.</p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&<strong>includeAllParentFields=1</strong></p>";
		echo "<p>To include disabled products in the feed, use the <strong>'includeDisabled'</strong> parameter.</p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&<strong>includeDisabled=1</strong></p>";
		echo "<p>To include products of all types in the feed, use the <strong>'includeNonSimpleProducts'</strong> parameter.</p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&<strong>includeNonSimpleProducts=1</strong></p>";
		echo "</div>";
		echo "<div style='float:left; padding-left:50px;'><h5>Version $this->_versionNumber <br />Last updated on $this->_lastUpdated</h5></div>";
	}

	// Gets all the products in the catalog in the specific store view,
	// returns an array of products and their details.
	public function getProducts($page) 
	{
		if(isset($_GET["storeid"]))
			Mage::app()->setCurrentStore($_GET["storeid"]);

		$products = $this->getProductCollection();

		$products->getSelect()
			->limit($this->_amountOfProductsPerPage,($page - 1) * $this->_amountOfProductsPerPage);

		return $products;
	}

	public function getProductCollection()
	{
		$products = Mage::getModel('catalog/product')->getCollection()
				->addStoreFilter($_GET["storeid"]);
		return $this->addAdditionalAttributeFilters($products);
	}
	
	public function addAdditionalAttributeFilters($products)
	{		
		if(isset($_GET["includeDisabled"]))
			$products->addAttributeToFilter('status', array('gt' => 0));
		else
			$products->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));
		
		if(!isset($_GET["includeNonSimpleProducts"]))
			$products->addAttributeToFilter('type_id', array('eq' => 'simple'));
				
		return $products;
	}

	// Run the task
	public function runTheTask($startPage, $endPage) 
	{
		while ($startPage <= $endPage) 
		{
			$products = $this->getProducts($startPage);
			if ($products->count() == 0)
				Mage::log('File: intelligentreach_integration.php, Error: There are no products to export at page '.$startPage.' when the amount of products per page is '. $this->_amountOfProductsPerPage);
			else 
			{
				Mage::getSingleton('core/resource_iterator')
					->walk($products->getSelect(), array(array($this, 'printProducts')),array('store_id' => $_GET["storeid"]));
			}
			$startPage = $startPage + 1;
			unset($products);
			flush();
		}
	}
    
	public function printProducts($args) 
	{
		$parentIds = null;
		$baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
		$product = Mage::getModel('catalog/product')->load($args['row']['entity_id']);
		if($product->getTypeId() == 'simple') 
		{
			$parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId());
			if(!$parentIds)
				$parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
			if(isset($parentIds[0]))
				$parentProduct = Mage::getModel('catalog/product')->load($parentIds[0]);
		}
		
		if((($product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED) 
			|| ((isset($parentProduct)) && ($parentProduct->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED)))
				&& !isset($_GET['includeDisabled']))
		{
			return;
		}
		
		echo'<product>';
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
						echo " <image_".($i + 1)."><![CDATA[". $baseUrl . "media/catalog/product" . $value['images'][$i]['file']."]]></image_".($i + 1).">";
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
				
					echo "<special_price_enabled><![CDATA[".$specialPriceEnabledValue."]]></special_price_enabled>";
				}

				if(is_array($value))
				{
					foreach($value as $vkey => $vvalue)
					{
						foreach($vvalue as $pkey => $pvalue)
							echo "<".$key."_".$vkey."_".$pkey."><![CDATA[".$pvalue."]]></".$key."_".$vkey."_".$pkey.">";
					}
					continue;
				}

				$value = $this->encodeValue($value);
				$value = $this->stripInvalidXMLCharacters($value);

				$value = "<![CDATA[$value]]>";

				$key = str_replace('"', '', $key);
				if(is_numeric($key[0]))
					$key = $this->convertNumberToWord($key[0]).substr($key, 1);
				echo '<' . $key . '>' . $value . '</' . $key . '>';
			}
		}

		if(isset($parentProduct))
		{
			if(isset($_GET["includeAllParentFields"]))
				$this->printAllParentFields($parentProduct);
			else
			{
				echo '<ir_parent_entity_id><![CDATA['.$this->stripInvalidXMLCharacters($parentProduct->getId()).']]></ir_parent_entity_id>';
				echo '<ir_parent_sku><![CDATA['.$this->stripInvalidXMLCharacters($parentProduct->getSku()).']]></ir_parent_sku>';
				echo '<ir_parent_url><![CDATA[' . $this->stripInvalidXMLCharacters(trim(str_replace('/intelligentreach_integration.php', '', $parentProduct->getProductUrl()))) . ']]></ir_parent_url>';
				echo '<ir_parent_image><![CDATA['.$this->stripInvalidXMLCharacters($baseUrl . 'media/catalog/product' . $parentProduct->getImage()).']]></ir_parent_image>';
				echo '<ir_parent_description><![CDATA['.$this->stripInvalidXMLCharacters($this->encodeValue($parentProduct->getDescription())).']]></ir_parent_description>';
			}
			$gallery = $parentProduct->getMediaGallery();
			if(count($gallery['images']) != 0)
			{
				for($i = 0; $i < count($gallery['images']); $i++)
				echo " <ir_parent_image_".($i + 1)."><![CDATA[". $baseUrl . "media/catalog/product" . $gallery['images'][$i]['file']."]]></ir_parent_image_".($i + 1).">";
			}
		}

		$categories = $product->getCategoryIds();
		if((count($categories) == 0) && isset($parentProduct))
			$categories = $parentProduct->getCategoryIds();
		if(count($categories) == 0)
		{
			echo '</product>';
			if (is_object($parentIds))
				unset($parentIds);
			unset($product);
			return;
		}
		$category = Mage::getModel('catalog/category')->setStoreId($_GET["storeid"])->load($categories[0]);

		/** Old Category Path code: will be deleted in the future. **/
		$cat_parentCategories = array_reverse($category->getParentCategories(), true);
		$output = "";

		foreach ($cat_parentCategories as $parent) 
		{
			$output .= $parent->getName();
			if ($parent !== end($cat_parentCategories))
				$output .= ' > ';
		}
		if($output != "")
			echo '<category_path><![CDATA['.$output.']]></category_path>';
		/** End of Old Category path code **/

		/** New Category Path code **/
		$pathIds =  array_reverse(explode(",", $category->getPathInStore()), true);
		$path = "";
		foreach($pathIds as $pathId)
		{
			$path .= Mage::getModel('catalog/category')->setStoreId($_GET["storeid"])->load($pathId)->getName();
			if($pathId != end($pathIds))
				$path .= ' > ';
		}
		if($path != "")
			echo '<ir_category_path><![CDATA['.$path.']]></ir_category_path>';
		/** End of New Category Path code **/
		
		/** New longest Category Path code **/
		$validCategoryPaths = array();
		$intelligent_reach_category_exclusions = Mage::getModel('core/variable')->setStoreId($_GET["storeid"])->loadByCode('intelligent_reach_category_exclusions')->getValue();
		foreach($categories as $cat)
		{
			$category = Mage::getModel('catalog/category')->setStoreId($_GET["storeid"])->load($cat);
				$pathIds =  array_reverse(explode(",", $category->getPathInStore()), true);
			$catpath = "";
			foreach($pathIds as $pathId)
			{
				$catpath .= Mage::getModel('catalog/category')->setStoreId($_GET["storeid"])->load($pathId)->getName();
				if($pathId != end($pathIds))
					$catpath .= ' > ';
			}
			if($catpath != "")
			{
				if($intelligent_reach_category_exclusions != "")
				{
					if(preg_match('/('.$intelligent_reach_category_exclusions.')/i', $catpath) != true)
						array_push($validCategoryPaths, $catpath);
				}
				else 
					array_push($validCategoryPaths, $catpath);
			}
		}
		if(count($validCategoryPaths) != 0)
		{
			if(count($validCategoryPaths) > 1)
				usort($validCategoryPaths, function ($a, $b) { return (strlen($a) < strlen($b)); });  
			echo "<ir_longest_category_path><![CDATA[".$validCategoryPaths[0]."]]></ir_longest_category_path>";
		}
		else if($path != "")
			echo '<ir_longest_category_path><![CDATA['.$path.']]></ir_longest_category_path>';
		/** End of New longest Category Path code **/

		echo '</product>';
		if (is_object($parentIds))
			unset($parentIds);
		unset($product);
	}

	public function printAllParentFields($parentProduct)
	{
		$baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
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
						echo "<".$key."_".$vkey."_".$pkey."><![CDATA[".$pvalue."]]></".$key."_".$vkey."_".$pkey.">";
				}
				continue;
			}
			
			$value = $this->encodeValue($value);
			$value = $this->stripInvalidXMLCharacters($value);

			$value = "<![CDATA[$value]]>";

			$key = str_replace('"', '', $key);
			if(is_numeric($key[0]))
				$key = $this->convertNumberToWord($key[0]).substr($key, 1);
			echo '<ir_parent_' . $key . '>' . $value . '</ir_parent_' . $key . '>';
		
		}
	}

	public function stripInvalidXMLCharacters($value) 
	{
		if(!isset($_GET["stripInvalidChars"]))
			return $value;
		return preg_replace("/[^A-Za-z0-9\d\!\"\$%^&*:;?'@~#{}|`()\\/.,_+=\-<>\s]/u", '', $value);
	} 
  
	public function convertNumberToWord($number)
	{
		if(!isset($_GET["convertNumberToWord"]))
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
}

