<?php 
/** Version 1.0.16 Last updated by Kire on 24/02/2014 **/

ini_set('display_errors', 1);
ini_set('max_execution_time', 1800);
include_once 'app/Mage.php';
umask(0);
Mage::app();

// Check if a storeid parameter has been set, returns a boolean.
function storeIsSelected()
{
	if(isset($_GET["storeid"]))
	  return true;
	else
	  return false;
}

// Gets all the stores on the current website, 
// returns a table containing Store Ids and Store Names.
function getStores()
{
  $websiteStores =  Mage::app()->getWebsite()->getStores(); 
  echo "<table cellspacing='2px;' border='1px;' cellpadding='8px;'>";
  echo "<tr><th>Store Id</th><th>Store Name</th></tr>";
  foreach ($websiteStores as $store) 
	{
	  echo "<tr><td>".$store->getId()."</td><td><a href='?storeid=".$store->getId()."&splitby=100&amountofproducts=100'>".$store->getName()."</a></td></tr>";
	} 
  echo "</table>";
}

function getSections($lastPageNumber, $sections, $splitBy)
{
	$pages = $lastPageNumber;
	echo "<table cellspacing='2px;' border='1px;' cellpadding='8px;'>";
	echo "<tr><th>Section</th><th>Pages</th></tr>";
	for($i=$sections; $i>0; $i--)
	{
	  $startingPage = $pages-$splitBy + 1;
	  if($startingPage <= 0){
	    $startingPage = 0;
	}
	  echo "<tr><td><a href='?storeid=".$_GET["storeid"]."&startingpage=".$startingPage."&endpage=".$pages."'>".$i."</a></td><td>".$startingPage."-".$pages."</td></tr>";
	  $pages = $startingPage - 1;
	}
	echo "</table>";
}

// Gets all the products in the catalog in the specific store view, 
// returns an array of products and their details.
function getProducts($page)
{
    $amountOfProductsPerPage = 100;
    if(isset($_GET["amountofproducts"]))
	  $amountOfProductsPerPage = $_GET["amountofproducts"];
	  
	$products = Mage::getModel('catalog/product')->getCollection()->addStoreFilter($_GET["storeid"]);
	$products->setPage($page, $amountOfProductsPerPage);
	$products->addAttributeToSelect('*');
	$products->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));
			
	return $products;
}

// Run the task
function runTheTask($products, $page, $lastPageNumber)
{
	header ("Content-Type: text/xml; charset=UTF-8");
	echo '<?xml version="1.0" encoding="utf-8"?>
	<products>';
	//echo '<currentPages>'.$page." - ".$lastPageNumber.'</currentPages>';
	while ($page <= $lastPageNumber)
	{	
		$products = getProducts($page);
		printProducts($products);
		$page = $page + 1;
		unset($products);
		flush();
	}
	echo '</products>';
}

// Prints the products to the output as XML nodes, takes an array as a parameter.
function printProducts($products)
{
  $stack = array();
  $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
	foreach ($products as $product)
	{
     // Get associated products for configurable don't print out parent
	 if($product->getTypeId() == 'configurable')
	 {
		$associatedProducts = $product->getTypeInstance()->getUsedProducts();
		array_push($stack, $associatedProducts);			
	 }
	 else
	 {
		  echo'<product>';
		  //echo '<memory>'.memory_get_usage().'</memory>';
		  if($product->getTypeId() == 'simple'){
				$parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId());
				if(!$parentIds)
					$parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
				if(isset($parentIds[0])){
					$parentProduct = Mage::getModel('catalog/product')->load($parentIds[0]);
				}
			}
		  foreach ($product->getdata() as $key=>$value) 
		  { 
		 
			if ($key!=='stock_item') 
			{
				if ($product->getResource()->getAttribute($key) != null)
				{ 
					$value = $product->getResource()->getAttribute($key)->getFrontend()->getValue($product);
				}
			
				$url = $product->getProductUrl();
				
				if (($key == 'url_path') || ($key =='url_key'))
				{ 
					$value = $url;
					$value = str_replace('/intelligentreach_integration.php','',$value);
					$value = trim ($value);
				} 
				
				if ($key == 'image')
				{ 
					$value = $baseUrl."media/catalog/product".$value;
				}
				 
				if ($key == 'thumbnail')
				{ 
					$value = $baseUrl."media/catalog/product".$value;
				}
				
				if(($value == '') && (isset($parentProduct)))
				{
					$attr = $parentProduct->getResource()->getAttribute($key);
					if(!is_object($attr)){
						 continue;
					}
					 $value = $attr->getFrontend()->getValue($parentProduct);
				}
				 
				 $value = "<![CDATA[$value]]>";
				 
				 $key = str_replace('"','',$key);

				 echo '<'.$key.'>'.$value.'</'.$key.'>';	
			}
			
		  }
		 
		  $categories = $product->getCategoryIds();

		 // echo '<categories>';

		  $output = "";
		  $firstCategoryPath = true;	
		  
			foreach($categories as $k => $_category_id)
			{
				if($firstCategoryPath)
				{
					 $_category = Mage::getModel('catalog/category')->setStoreId($_GET["storeid"])->load($_category_id);
					 $cat_parentCategories = array_reverse($_category->getParentCategories(), true);
					 $output = '<category_path><![CDATA[';
					 
					 foreach($cat_parentCategories as $parent)
					 {
						 if($parent->getName() == end($cat_parentCategories)->getName())
						 {
							$output .= $parent->getName();
						 }
						 else
						 {
							$output .= $parent->getName().' > ';
						 }
					 }
					 
					 $output .= ']]></category_path>';
					 $firstCategoryPath = false;
					 echo $output;
				}
			}

		 //echo '</categories>';
		 //echo '<memory>'.memory_get_usage().'</memory>';
		 echo '</product>'; 
		 
		 $url = $product->getProductUrl();
		 // Get associated products for grouped
		 if($product->getTypeId() == 'grouped')
		 {	
			$associatedProductsPush  = $product->getTypeInstance(true)->getAssociatedProducts($product);
			array_push($stack, $associatedProductsPush);
		 }
	 }
    }
	//if the stack isn't empty recursively print the products out
	if(!empty($stack))
	{
	  $uniqueStack = array_unique($stack);
	  for($i=0; $i<=count($uniqueStack);$i++)
	  {
		$associatedProductsPop = array_pop($uniqueStack);
		printProducts($associatedProductsPop);
	  }
	}
}

// If a store id was provided then print the products to the output.
if(storeIsSelected())
{
	$page = 1;
	$products = getProducts($page);
	$lastPageNumber = $products->getLastPageNumber();
	if(isset($_GET["splitby"]))
	  $splitBy = $_GET["splitby"];
	else
	  $splitBy = 100;
  if($lastPageNumber < 10)
  {
    runTheTask($products, $page, $lastPageNumber);
  }
  else
  {
    if(isset($_GET["startingpage"]) && isset($_GET["endpage"]))
	{
	  runTheTask($products, $_GET["startingpage"], $_GET["endpage"]);
	}
	else
	{
	   $sections = ceil($lastPageNumber / $splitBy);
	   echo "<h2>This store has a lot of products so they have been split into ".$sections." sections.</h2>";
	   echo "<div class='sections' style='float:left;'>";
	   getSections($lastPageNumber, $sections, $splitBy);
	   echo "</div>";
	   echo "<div class='instructions' style='float:left; padding-left:100px;'>";
	   echo "<h3>Instructions</h3>";
	   echo "<p>The parameter <strong>'splitby'</strong> in the URL splits pages into sections, each page contains unless specified otherwise the default amount of 100 products.</p>";
	   echo "<p>So setting <strong>'splitby'</strong> to equal 100 will bring back 1,000 products per page and 10,000 products per section, if there are 40,000 products in the store then this will return 4 sections. </p>";
	   echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&<strong>splitby=100</strong></p>";
	   echo "<p>You can also set the value of the number of products per page that is returned, by setting the parameter <strong>'amountofproducts'</strong> in the URL</p>";
	   echo "<strong>e.g.</strong> http://www.exampledomain.com/intelligentreach_integration.php?storeid=1&splitby=100&<strong>amountofproducts=100</strong></p>";
	   echo "<p><strong>NB:</strong> The default value for <strong>'splitby'</strong> is 100 and for <strong>'amountofproducts'</strong> is 100.</p>";
	   echo "</div>";
	}
  }
}
else // Give the option to choose the specific store the data will be extracted from.
{
	 echo "<p>Sorry a Store Id was not provided, please choose a store from the options below.</p>";
	 getStores();
	 echo "<p>If you want to skip this step in the future, you can manually enter the Store Id in the URL.<br />";
	 echo "e.g. http://www.exampledomain.com/intelligentreach_integration.php?storeid=1</p>";
	 echo "<p><strong>NB:</strong> The Store Id parameter name is case sensitive. Only use \"storeid=\" not another variation.</p>";
}
?> 