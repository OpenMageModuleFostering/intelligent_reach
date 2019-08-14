<?php

/** Version 1.0.41 Last updated by Kire on 10/08/2016 **/
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
	private $_versionNumber = "1.0.41";
	private $_lastUpdated = "10/08/2016";
	private $_outputDirectory = "output";
	private $_fileName = "Feed_Quantity_And_Price";
	private $_fileNameTemp = "";
	private $_amountOfProductsPerPage = 1000;
	
	private $_gzipFile = true;
	private $_includeDisabled = false;
	private $_includeNonSimpleProducts = false;

	public function run()
	{
		$storeId = (isset($_GET["storeid"]))? $_GET["storeid"] : false;

		// If a store id was provided then print the products to the output.
		if ($storeId !== false)
		{
			$startTime = microtime(true);
			$this->_fileName = $this->_outputDirectory."/".$this->_fileName."_".$storeId.".xml"; // added store id to feed file name for multi store support.
			$this->_fileNameTemp = tempnam("", $this->_fileName);
			echo "Temp File created: ". $this->_fileNameTemp."<br />";

			file_put_contents($this->_fileNameTemp, "<?xml version=\"1.0\" encoding=\"utf-8\"?><products version=\"$this->_versionNumber\" type=\"cron\">", LOCK_EX);
			$this->runTheTask($storeId);      
			file_put_contents($this->_fileNameTemp, '</products>', FILE_APPEND | LOCK_EX);

			if (!file_exists($this->_outputDirectory))
				mkdir($this->_outputDirectory);

			file_put_contents($this->_fileName, file_get_contents($this->_fileNameTemp), LOCK_EX);
			unlink($this->_fileNameTemp);
			echo "Finished Generating Feed Quantity file: '".$this->_fileName."'";

			if($this->_gzipFile)
				$this->gzCompressFile($this->_fileName); // gzip as file is very large

			echo "<br />".((microtime(true) - $startTime))." secs";
			echo "<br />".(memory_get_usage(true))." bytes";
		}
		else
			$this->printStores();
	}

	// Gets all the stores on the current website,
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
		echo "<h5>Version $this->_versionNumber <br />Last updated on $this->_lastUpdated</h5></div>";
	}

	// Gets all the products in the catalog in the specific store view,
	// returns a collection of products and their details.
	public function getProducts($page,$storeId)
	{
		if($storeId)
			Mage::app()->setCurrentStore($storeId);

		$products = $this->getProductCollection($storeId);

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

	public function getProductCollection($storeId)
	{
		$products = Mage::getModel('catalog/product')
				->getCollection()
				->addStoreFilter($storeId)
				->addAttributeToSelect(array('price', 'sku', 'special_from_date', 'special_to_date', 'special_price'), 'left');
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
		$currentPage = 1;
		$lastPage = ceil($this->getProductCollection($storeId)->getSize() / $this->_amountOfProductsPerPage);

		do{
			$products = $this->getProducts($currentPage,$storeId);
			echo "Starting page $currentPage of $lastPage ..";
			Mage::getSingleton('core/resource_iterator') // seems this ignores the current page
				->walk($products->getSelect(), array(array($this, 'printProducts')));
			$currentPage++;

			$products->clear();
			unset($products);
			ob_flush();
			flush();
			echo " ".(memory_get_usage(true))." bytes ";
			echo ".. Finished (ok) <br />";
		}while($currentPage <= $lastPage);
	}

	public function printProducts($args)
	{
		$feedData = "";

		// We check for manage stock. This is implemented in the catalog inventory stock item model however we don't want to load the model.
		$manageStock = $args['row']['manage_stock'];
		$configManageStock = $args['row']['use_config_manage_stock'];
		$isInStock = $args['row']['is_in_stock'];

		if($configManageStock)
			$manageStock = Mage::getStoreConfig(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK);

		if(!$manageStock)
			$isInStock = true; // if we don't manage, "is in stock" is always true.

		$feedData .= '<product>';
		$feedData .= '<entity_id><![CDATA['.$args['row']['entity_id'].']]></entity_id>';
		$feedData .= '<sku><![CDATA['.$args['row']['sku'].']]></sku>';
		$feedData .= '<qty><![CDATA['.(int)$args['row']['qty'].']]></qty>';
		$feedData .= '<is_in_stock><![CDATA['.(int)$isInStock.']]></is_in_stock>';
		$feedData .= '<price><![CDATA['.$args['row']['price'].']]></price>';
		$feedData = $this->getSpecialPrice($args, $feedData);
		$feedData .= '</product>'.PHP_EOL;

		file_put_contents($this->_fileNameTemp, $feedData, FILE_APPEND | LOCK_EX);
	}
	
	public function getSpecialPrice($args, $feedData)
	{
		$value = $args['row']['special_price'];
		$specialPriceEnabledValue = is_null($value) ? 0 : 1;
		$fromDate = $args['row']['special_from_date'];
		$toDate = $args['row']['special_to_date'];

		if($fromDate != null)
			$specialPriceEnabledValue = (strtotime($fromDate) <= strtotime(date('Y-m-d'))) ? 1 : 0;
		if($toDate != null)
			$specialPriceEnabledValue = (strtotime(date('Y-m-d')) <= strtotime($toDate)) ? 1 : 0;

		$feedData .= "<special_price_enabled><![CDATA[".$specialPriceEnabledValue."]]></special_price_enabled>";
		$feedData .= "<special_price><![CDATA[".$value."]]></special_price>";
		return $feedData;
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