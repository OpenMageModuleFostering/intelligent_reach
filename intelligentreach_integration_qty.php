<?php

/** Version 1.0.33 Last updated by Kire on 24/09/2015 **/
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
	private $_versionDisplay = "Version 1.0.33 <br />Last updated on 24/09/2015";

  public function run() 
  {
    $prodcoll = $this->getProducts(1);
    $this->_lastPageNumber = $prodcoll->getLastPageNumber();
    if (isset($_GET["splitby"]))
      $this->_splitby = $_GET["splitby"];
		if (isset($_GET["amountofproducts"]))
      $this->_amountOfProductsPerPage = $_GET["amountofproducts"];

    // If a store id was provided then print the products to the output.
    if ($this->storeIsSelected()) 
    {
      if ((isset($_GET["startingpage"]) && isset($_GET["endpage"])) || isset($_GET["getall"])) 
      {
        header("Content-Type: text/xml; charset=UTF-8");
				header("Cache-Control: no-cache, must-revalidate");
        echo '<?xml version="1.0" encoding="utf-8"?>
            <products>';
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
		echo "<h5>".$this->_versionDisplay."</h5></div>";
  }

  public function getSections($sections) 
  {
    $pages = $this->_lastPageNumber;
    echo "<table cellspacing='2px;' border='1px;' cellpadding='8px;'>";
    echo "<tr><th>Section</th><th>Pages</th></tr>";
    for ($i = $sections; $i > 0; $i--) 
    {
      $startingPage = $pages - $this->_splitby + 1;
      if ($startingPage < 1)
        $startingPage = 1;
     
      echo "<tr><td><a href='?storeid=" . $_GET["storeid"] . "&startingpage=" . $startingPage . "&endpage=" . $pages . "&splitby=".$this->_splitby ."&amountofproducts=".$this->_amountOfProductsPerPage."'>" . $i . "</a></td><td>" . $startingPage . "-" . $pages . "</td></tr>";
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
    echo "</div>";
		echo "<div style='float:left; padding-left:50px;'><h5>";
		echo $this->_versionDisplay;
		echo "</h5></div>";
  }

  // Gets all the products in the catalog in the specific store view,
  // returns an array of products and their details.
  public function getProducts($page) 
  {
		if(isset($_GET["storeid"]))
			Mage::app()->setCurrentStore($_GET["storeid"]);
	  
    $products = Mage::getModel('catalog/product')->getCollection()->addStoreFilter($_GET["storeid"]);
		$products->setPage($page, $this->_amountOfProductsPerPage);
    $products->addAttributeToSelect('*');
    $products->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));
    return $products;
  }

  // Run the task
  public function runTheTask($startPage, $endPage) 
  {
    while ($startPage <= $endPage) 
    {
      $products = $this->getProducts($startPage);
      if ($products->count() == 0)
        $this->_log('There are no products to export', true);
      else 
      {
        Mage::getSingleton('core/resource_iterator')
                ->walk($products->getSelect(), array(array($this, 'printProducts')));
      }
      $startPage = $startPage + 1;
      unset($products);
      flush();
    }
  }

  public function printProducts($args)
  {
    $product =  Mage::getModel('catalog/product')->load($args['row']['entity_id']);
    
    $inventoryProduct = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
    echo'<product>';
    echo '<entity_id><![CDATA['.$inventoryProduct->getProductId().']]></entity_id>';
    echo '<qty><![CDATA['.(int)$inventoryProduct->getQty().']]></qty>';
    echo '<is_in_stock><![CDATA['.(int)$inventoryProduct->getIsInStock().']]></is_in_stock>';            
    echo '</product>';
        
    $product->clearInstance();
  }
}