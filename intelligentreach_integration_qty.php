<?php

/** Version 1.0.40 Last updated by Kire on 27/07/2016 **/
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
	private	$_amountOfProductsPerPage = 100;
	private $_lastPageNumber = 0;
	private $_versionNumber = "1.0.40";
	private $_lastUpdated = "27/07/2016";

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

	// Gets all the stores on the current website,
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
			echo "<tr><td><a href='?storeid=" . $_GET["storeid"] . "&startingpage=" . $startingPage . "&endpage=" . $pages . "&splitby=".$this->_splitby ."&amountofproducts=".$this->_amountOfProductsPerPage.$includeDisabled.$includeNonSimpleProducts."'>" . $i . "</a></td><td>" . $startingPage . "-" . $pages . "</td></tr>";
			$pages = $startingPage - 1;
		}
		echo "</table>";
	}

	public function printSections() 
	{
		$sections = ceil($this->_lastPageNumber / $this->_splitby);
		echo "<h2>Please select a section to return the product quantities.</h2>";
		echo "<div class='sections' style='float:left;'>";
		$this->getSections($sections);
		echo "</div>";
		echo "<div class='instructions' style='float:left; padding-left:100px;'>";
		echo "<h3>Instructions</h3>";
		echo "<p>The parameter <strong>'splitby'</strong> in the URL splits pages into sections, each page contains (unless specified otherwise) the default amount of 100 products.</p>";
		echo "<p>So setting <strong>'splitby'</strong> to equal 100 will bring back 1,000 products per page and 10,000 products per section, if there are 40,000 products in the store then this will return 4 sections. </p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&<strong>splitby=100</strong></p>";
		echo "<p>You can also set the value of the number of products per page that is returned, by setting the parameter <strong>'amountofproducts'</strong> in the URL</p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&splitby=100&<strong>amountofproducts=100</strong></p>";
		echo "<p><strong>NB:</strong> The default value for <strong>'splitby'</strong> is 100 and for <strong>'amountofproducts'</strong> is 100.</p>";
		echo "<p>You can also retrieve all product quantities but using the 'getall' parameter</p>";
		echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&<strong>getall=1</strong></p>";
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

		// join stock item, saves having to load the stock item model.
		$products
			->getSelect()
			->limit($this->_amountOfProductsPerPage,($page - 1) * $this->_amountOfProductsPerPage)
			->joinLeft(array('si'=>'cataloginventory_stock_item'),'e.entity_id = si.product_id',
				array('use_config_manage_stock','manage_stock','qty','is_in_stock'))
					->group('e.entity_id') // sometimes there can be duplicate stock items.
					->order('e.entity_id');

		return $products;
	}

	public function getProductCollection()
	{
		$products = Mage::getModel('catalog/product')
				->getCollection()
				->addStoreFilter($_GET["storeid"])
				->addAttributeToSelect(array('price', 'sku'), 'left');
		return $this->addAdditionalAttributeFilters($products);
	}
	
	public function addAdditionalAttributeFilters($products)
	{
		if(Mage::app()->getStore()->getConfig('catalog/frontend/flat_catalog_product'))
			Mage::app()->getStore()->setConfig('catalog/frontend/flat_catalog_product', 0);
		
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
				Mage::log('File: intelligentreach_integration_qty.php, Error: There are no products to export at page '.$startPage.' when the amount of products per page is '. $this->_amountOfProductsPerPage);
			else 
			{
				Mage::getSingleton('core/resource_iterator')
					->walk($products->getSelect(), array(array($this, 'printProducts')));
			}
			$startPage++;
			unset($products);
			flush();
		}
	}

	public function printProducts($args)
	{
		// We check for manage stock. This is implemented in the catalog inventory stock item model however we don't want to load the model.
		$manageStock = $args['row']['manage_stock'];
		$configManageStock = $args['row']['use_config_manage_stock'];
		$isInStock = $args['row']['is_in_stock'];

		if($configManageStock)
			$manageStock = Mage::getStoreConfig(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK);

		if(!$manageStock)
			$isInStock = true; // if we don't manage, "is in stock" is always true.

		echo '<product>';
		echo '<entity_id><![CDATA['. $args['row']['entity_id'].']]></entity_id>';
		echo '<sku><![CDATA['. $args['row']['sku'].']]></sku>';
		echo '<qty><![CDATA['.(int)$args['row']['qty'].']]></qty>';
		echo '<is_in_stock><![CDATA['.(int)$isInStock.']]></is_in_stock>';
		echo '<price><![CDATA['.$args['row']['price'].']]></price>';
		echo '</product>';
	}
}