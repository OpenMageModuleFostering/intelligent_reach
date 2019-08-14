<?php

/** Version 1.0.29 Last updated by Kire on 06/10/2015 **/
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
  private $_versionDisplay = "Version 1.0.29 <br />Last updated on 06/10/2015";

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
    echo "<p>You can also retrieve all products but using the 'getall' parameter</p>";
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
    $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);

    $product = Mage::getModel('catalog/product')->load($args['row']['entity_id']);
    echo'<product>';
    if ($product->getTypeId() == 'simple') 
    {
      $parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId());
      if (!$parentIds)
        $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
      if (isset($parentIds[0]))
        $parentProduct = Mage::getModel('catalog/product')->load($parentIds[0]);
    }
    foreach ($product->getdata() as $key => $value) 
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
 
		$value = htmlentities($value, ENT_COMPAT | ENT_SUBSTITUTE, "UTF-8");
        $value = "<![CDATA[$value]]>";

        $key = str_replace('"', '', $key);

        echo '<' . $key . '>' . $value . '</' . $key . '>';
      }
    }

    $categories = $product->getCategoryIds();
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
	$intelligent_reach_category_exclusions = Mage::getModel('core/variable')->setStoreId($store_id)->loadByCode('intelligent_reach_category_exclusions')->getValue();
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
	
	if(isset($parentProduct))
	{
		 echo '<ir_parent_entity_id><![CDATA['.$parentProduct->getId().']]></ir_parent_entity_id>';
		 echo '<ir_parent_sku><![CDATA['.$parentProduct->getSku().']]></ir_parent_sku>';
		 echo '<ir_parent_url><![CDATA[' . trim(str_replace('/intelligentreach_integration.php', '', $parentProduct->getProductUrl())) . ']]></ir_parent_url>'; 
		 echo '<ir_parent_image_url><![CDATA['. $baseUrl . 'media/catalog/product' .$parentProduct->getImage().']]></ir_parent_image_url>';
	}

    echo '</product>';
    if (is_object($parentIds))
      $parentIds->clearInstance();
    
    $product->clearInstance();
  }
}

