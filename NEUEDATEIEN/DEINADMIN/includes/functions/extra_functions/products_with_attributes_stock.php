<?php
/**
 * @package Stock by Attributes for Zen Cart German
 * @copyright Copyright 2003-2022 Zen Cart Development Team
 * Zen Cart German Version - www.zen-cart-pro.at
 * @copyright Portions Copyright 2003 osCommerce
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 * @version $Id: products_with_attributes_stock.php 2024-04-13 13:10:14Z webchills $
 */
 

function return_attribute_combinations($arrMain, $intVars, $currentLoop = array(), $currentIntVar = 0) {
  $arrNew = array();

  for ($currentLoop[$currentIntVar] = 0, $n = count($arrMain[$currentIntVar]); $currentLoop[$currentIntVar] < $n; $currentLoop[$currentIntVar]++) {
    if ($intVars <> ($currentIntVar + 1)) {
      $arrNew = array_merge($arrNew, return_attribute_combinations($arrMain, $intVars, $currentLoop, $currentIntVar + 1));
      continue;
    }
    $arrNew2 = array();

    for ($i = 0; $i < $intVars; $i++) {
      $arrNew2[] = $arrMain[$i][$currentLoop[$i]];  // This is a place where an evaluation could be made to do something unique with a single attribute that is to be assigned to a sba variant as this assigment is for one of the attributes to be combined for one record. If the goal would be not to add anything having this one attribute, then could call continue 2 to escape this for loop and move on to the next outer for loop.  If just want to not add the one attribute to the combination then place the above assignment so that it is bypassed when not to be added. 
    }
    if (empty($arrNew2)) {
      continue;
    }
    // This is a place where an evaluation could be made to do something unique with a sba variant as this assigment is one of all attributes combined for one record.  //Still something about this test doesn't seem quite right, but it's the concept that is important, as long as there is something to evaluate/assign that is not nothing, then do the assignment.
    $arrNew[] = $arrNew2;
  }

  return $arrNew;
}
 
  function zen_get_sba_ids_from_attribute($products_attributes_id = array()){
    global $db;
    
    if (!is_array($products_attributes_id)){
      $products_attributes_id = array($products_attributes_id);
    }
    $products_stock_attributes = $db->Execute("select stock_id, stock_attributes from " . 
                                              TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK);

    $stock_id_list = array();
    
    while (!$products_stock_attributes->EOF) {
     
      $stock_attrib_list = explode(',', $products_stock_attributes->fields['stock_attributes']);
      $stock_attrib_list = array_map('trim', $stock_attrib_list);

      foreach($stock_attrib_list as $stock_attrib){
        if (!in_array($stock_attrib, $products_attributes_id)) {
          continue;
        }
        $stock_id_list[] = $products_stock_attributes->fields['stock_id'];
      }
      
      $products_stock_attributes->MoveNext();
    }
    return $stock_id_list;
  }  
  
  function sba_get_eo_products($oID = 0) {
    global $db;
    
    $index = 0;    
    $orders_products_query = "select orders_products_id, products_id, products_name,
                                 products_model, products_price, products_tax,
                                 products_quantity, final_price,
                                 onetime_charges,
                                 products_priced_by_attribute, product_is_free, products_discount_type,
                                 products_discount_type_from
                                  from " . TABLE_ORDERS_PRODUCTS . "
                                  where orders_id = " . (int)$oID . "
                                  order by orders_products_id";

    $orders_products = $db->Execute($orders_products_query, false, false, 0, true);
        
    $order = array();
    while (!$orders_products->EOF) {
      $order[$index] = array('id' => $orders_products->fields['products_id']);
      $index++;
      $orders_products->MoveNext();
    }
   
    unset($orders_products);
    unset($index);
    
    return $order;
  }
  
  function sba_string_to_int($string) {
    return (int)$string;
  }
