<?php
/**
 * @package Stock by Attributes for Zen Cart German
 * @copyright Copyright 2003-2022 Zen Cart Development Team
 * Zen Cart German Version - www.zen-cart-pro.at
 * @copyright Portions Copyright 2003 osCommerce
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 * @version $Id: products_with_attributes_stock.php 2022-10-22 12:13:14Z webchills $
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class products_with_attributes_stock extends base
  {  
    function get_products_attributes($products_id, $languageId=1)
    {
      global $db;     

      $attributes_array = array();      
     
      if (PRODUCTS_OPTIONS_SORT_ORDER=='0') {
        $options_order_by= ' order by LPAD(popt.products_options_sort_order,11,"0"), popt.products_options_name';
      } else {
        $options_order_by= ' order by popt.products_options_name';
      }

      //get the option/attribute list
      $sql = "select distinct popt.products_options_id, popt.products_options_name, popt.products_options_sort_order,
                              popt.products_options_type
              from        " . TABLE_PRODUCTS_OPTIONS . " popt
              left join " . TABLE_PRODUCTS_ATTRIBUTES . " patrib ON (patrib.options_id = popt.products_options_id)
              where patrib.products_id= :products_id:
              and popt.language_id = :languages_id: " .
              $options_order_by;

      $sql = $db->bindVars($sql, ':products_id:', $products_id, 'integer');
      $sql = $db->bindVars($sql, ':languages_id:', $languageId, 'integer');

      $attributes = $db->Execute($sql);
      
      if($attributes->RecordCount() > 0)
      {
      
        if ( PRODUCTS_OPTIONS_SORT_BY_PRICE =='1' ) {
          $order_by= ' order by LPAD(pa.products_options_sort_order,11,"0"), pov.products_options_values_name';
        } else {
          $order_by= ' order by LPAD(pa.products_options_sort_order,11,"0"), pa.options_values_price';
        }
        $products_options_array = array();
      
        while(!$attributes->EOF)
        {
        
          $sql = "select    pov.products_options_values_id,
                        pov.products_options_values_name,
                        pa.*
              from      " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
              where     pa.products_id = '" . (int)$products_id . "'
              and       pa.options_id = '" . (int)$attributes->fields['products_options_id'] . "'
              and       pa.options_values_id = pov.products_options_values_id
              and       pov.language_id = '" . (int)$languageId . "' " .
                $order_by;

          $attributes_array_ans= $db->Execute($sql);

          //loop for each option/attribute listed

          while (!$attributes_array_ans->EOF) {
            $attributes_array[$attributes->fields['products_options_name']][] =
              array('id' => $attributes_array_ans->fields['products_attributes_id'],
                  'text' => $attributes_array_ans->fields['products_options_values_name']
                        . ' (' . $attributes_array_ans->fields['price_prefix']
                      . '$'.zen_round($attributes_array_ans->fields['options_values_price'],2) . ')',
                  'display_only' => $attributes_array_ans->fields['attributes_display_only'],
                  );
          
            $attributes_array_ans->MoveNext();
          }
          $attributes->MoveNext();
        }
  
        return $attributes_array;
  
      }
      else
      {
        return false;
      }
    }
  
    function update_parent_products_stock($products_id)
    {
      global $db;

      $query = 'select sum(quantity) as quantity, products_id from '.TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK.' where products_id = :products_id:';
      $query = $db->bindVars($query, ':products_id:', $products_id, 'integer');
      $quantity = $db->Execute($query);

      $query = 'update '.TABLE_PRODUCTS.' set products_quantity=:quantity: where products_id=:products_id:';
      $query = $db->bindVars($query, ':products_id:', zen_get_prid($products_id), 'integer');

      // Tests are this: If the the item was found in the SBA table then update with those results.
      // Else pull the value from the current stock quantity  and if the "switch" has not been
      //  turned off, the value will stay the same otherwise, it would be set to zero.
      if ($quantity->RecordCount() > 0 && $quantity->fields['products_id'] == zen_get_prid($products_id)) {
        $query = $db->bindVars($query, ':quantity:', $quantity->fields['quantity'], 'float');
      } else {
        // Should add a switch to allow not resetting the quantity to zero when synchronizing quantities... This doesn't entirely make sense that because the product is not listed in the SBA table, that it should be zero'd out...
        $query2 = "select p.products_quantity as quantity from :table: p where products_id=:products_id:";
        $query2 = $db->bindVars($query2, ':table:', TABLE_PRODUCTS, 'passthru');
        $query2 = $db->bindVars($query2, ':products_id:', zen_get_prid($products_id), 'integer');
        $quantity_orig = $db->Execute($query2);
        
        if ($quantity_orig->RecordCount() > 0 && true /* This is where a switch could be introduced to allow setting to 0 when synchronizing with the SBA table. But as long as true, and the item is not tracked by SBA, then there is no change in the quantity.  header message probably should also appear.. */) {
          $query = $db->bindVars($query, ':quantity:', $quantity_orig->fields['quantity'], 'float');
        } else {
          $query = $db->bindVars($query, ':quantity:', 0, 'float');
        }
      }

      $db->Execute($query);
    }
    
    // Technically the below update of all, could call the update of one... There doesn't
    //  seem to be a way to do the update in any more of a faster way than to address each product.
    function update_all_parent_products_stock() {
      global $db;
      $products_array = $this->get_products_with_attributes();
      foreach ($products_array as $products_id) {
        $query = 'select sum(quantity) as quantity, products_id from '.TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK.' where products_id = :products_id:';
        $query = $db->bindVars($query, ':products_id:', zen_get_prid($products_id), 'integer');
        $quantity = $db->Execute($query);

        $query = 'update '.TABLE_PRODUCTS.' set  products_quantity=:quantity: where products_id=:products_id:';
        $query = $db->bindVars($query, ':products_id:', zen_get_prid($products_id), 'integer');
        // Tests are this: If the the item was found in the SBA table then update with those results.
        // Else pull the value from the current stock quantity  and if the "switch" has not been
        //  turned off, the value will stay the same otherwise, it would be set to zero.
        if ($quantity->RecordCount() > 0 && $quantity->fields['products_id'] == zen_get_prid($products_id)) {
          $query = $db->bindVars($query, ':quantity:', $quantity->fields['quantity'], 'float');
        } else {
          // Should add a switch to allow not resetting the quantity to zero when synchronizing quantities... This doesn't entirely make sense that because the product is not listed in the SBA table, that it should be zero'd out...
          $query2 = "select p.products_quantity as quantity from :table: p where products_id=:products_id:";
          $query2 = $db->bindVars($query2, ':table:', TABLE_PRODUCTS, 'passthru');
          $query2 = $db->bindVars($query2, ':products_id:', zen_get_prid($products_id), 'integer');
          $quantity_orig = $db->Execute($query2);
          if ($quantity_orig->RecordCount() > 0 && true /* This is where a switch could be introduced to allow setting to 0 when synchronizing with the SBA table. But as long as true, and the item is not tracked by SBA, then there is no change in the quantity.  header message probably should also appear.. */) {
            $query = $db->bindVars($query, ':quantity:', $quantity_orig->fields['quantity'], 'float');
          } else {
            $query = $db->bindVars($query, ':quantity:', 0, 'float');
          }
        }
        
        $db->Execute($query);
      }
    }
    
    // returns an array of product ids which contain attributes
    function get_products_with_attributes() {
      global $db;
      if(isset($_SESSION['languages_id'])){ $language_id = (int)$_SESSION['languages_id'];} else { $language_id=1;}
      $query = 'SELECT DISTINCT pa.products_id, pd.products_name, p.products_quantity, p.products_model, p.products_image
                FROM '.TABLE_PRODUCTS_ATTRIBUTES.' pa
                left join '.TABLE_PRODUCTS_DESCRIPTION.' pd on (pa.products_id = pd.products_id)
                left join '.TABLE_PRODUCTS.' p on (pa.products_id = p.products_id)
                WHERE pd.language_id='.$language_id.' 
                ORDER BY pd.products_name ';
      $products = $db->Execute($query);
      while(!$products->EOF){
        $products_array[] = $products->fields['products_id'];
        $products->MoveNext();
      }
      return $products_array;
    }
  
  
    function get_attributes_name($attribute_id, $languageId=1)
    {
      global $db;

      $query = 'select pa.products_attributes_id, po.products_options_name, pov.products_options_values_name
             from '.TABLE_PRODUCTS_ATTRIBUTES.' pa
             left join '.TABLE_PRODUCTS_OPTIONS.' po on (pa.options_id = po.products_options_id)
             left join '.TABLE_PRODUCTS_OPTIONS_VALUES.' pov on (pa.options_values_id = pov.products_options_values_id)
             where pa.products_attributes_id = "'.$attribute_id.'"
              AND po.language_id = "'.$languageId.'"
              and po.language_id = pov.language_id';
              
      $attributes = $db->Execute($query);
      if(!$attributes->EOF)
      {    
        $attributes_output = array('option' => $attributes->fields['products_options_name'],
                       'value' => $attributes->fields['products_options_values_name']);
        return $attributes_output;
      }
      else
      {
        return false;
      }
    }
        
        
/**
 * @desc displays the filtered product-rows
 * 
 * Passed Options
 * $SearchBoxOnly
 * $ReturnedPage
 * $NumberRecordsShown
 */
function displayFilteredRows($SearchBoxOnly = null, $NumberRecordsShown = null, $ReturnedProductID = null){
        global $db;
      
        if(isset($_SESSION['languages_id'])){ $language_id = $_SESSION['languages_id'];} else { $language_id=1;}
        if( isset($_GET['search']) && $_GET['search']){ // mc12345678 Why was $_GET['search'] omitted?
            $s = zen_db_input($_GET['search']);
          
            $w = " AND ( p.products_id = '$s' OR d.products_name LIKE '%$s%' OR p.products_model LIKE '$s%' ) " ;//changed search to products_model 'startes with'.
    } else {
        $w = ''; 
      $s = '';
    }

        //Show last edited record or Limit number of records displayed on page
        $SearchRange = null;
        if( $ReturnedProductID != null && !isset($_GET['search']) ){
          $ReturnedProductID = zen_db_input($ReturnedProductID);
          
          $w = " AND ( p.products_id = '$ReturnedProductID' ) " ;//sets returned record to display
          if (!isset($_GET['products_filter']) || (isset($_GET['products_filter']) && $_GET['products_filter'] != '' && $_GET['products_filter'] <= 0)) {
            $SearchRange = "limit 1";//show only selected record
          }
      } 
      elseif( $NumberRecordsShown > 0 && $SearchBoxOnly == 'false' ){
        $NumberRecordsShown = zen_db_input($NumberRecordsShown);
      $SearchRange = " limit $NumberRecordsShown";//sets start record and total number of records to display
    }
    elseif( $SearchBoxOnly == 'true' && !isset($_GET['search']) ){
         $SearchRange = "limit 0";//hides all records
    }

        $retArr = array();

        if (isset($_GET['page']) && ($_GET['page'] > 1)) $rows = $_GET['page'] * 500000 - 500000;

        if (isset($_GET['search_order_by'])) {
          $search_order_by = $_GET['search_order_by'];
        } else {
          $search_order_by = 'p.products_model';
        }

        $query_products =    "select distinct pa.products_id, pd.products_name, p.products_quantity, p.products_model, p.products_image, p.products_type, p.master_categories_id FROM ".TABLE_PRODUCTS_ATTRIBUTES." pa, ".TABLE_PRODUCTS_DESCRIPTION." pd, ".TABLE_PRODUCTS." p WHERE pd.language_id='".$language_id."' and pa.products_id = pd.products_id and pa.products_id = p.products_id " . $w . " order by " . $search_order_by . " " /*d.products_name "*/.$SearchRange."";

        if (!isset($_GET['seachPID']) && !isset($_GET['pwas-search-button']) && !isset($_GET['updateReturnedPID'])) {
          $products_split = new splitPageResults($_GET['page'], 500000, $query_products, $products_query_numrows);
        } 
        $products = $db->Execute($query_products);

        $html = '';
        if (!isset($_GET['seachPID']) && !isset($_GET['pwas-search-button']) && !isset($_GET['updateReturnedPID'])) {
        $html .= '<table border="0" width="100%" cellspacing="0" cellpadding="2" class="pageResults">';
        $html .= '<tr>';
        $html .= '<td class="smallText" valign="top">'; 
        $html .= $products_split->display_count($products_query_numrows, 500000, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_PRODUCTS); 
        $html .= '</td>';
        $html .= '<td class="smallText" align="right">';
        $html .= $products_split->display_links($products_query_numrows, 500000, 500000, $_GET['page']);
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        }
    $html .= zen_draw_form('stock_update', FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK . '_ajax', 'save=1&amp;pid='.$ReturnedProductID.(!empty($_GET['page']) ? '&amp;page='.$_GET['page'] : ''), 'post');
    $html .= zen_draw_hidden_field('save', '1');
    $html .= zen_draw_hidden_field('pid', $ReturnedProductID);
    $html .= zen_image_submit('button_save.gif', IMAGE_SAVE) . '';
       $html .= '<br/>';
    $html .= '
    <table id="mainProductTable"> 
    <tr>
      <th class="thProdId">'.PWA_PRODUCT_ID.'</th>
      <th class="thProdName">'.PWA_PRODUCT_NAME.'</th>';
    
    if (STOCK_SHOW_IMAGE == 'true') {$html .= '<th class="thProdImage">'.PWA_PRODUCT_IMAGE.'</th>';}   

        $html .= '<th class="thProdModel">'.PWA_PRODUCT_MODEL.'</th>            
              <th class="thProdQty">'.PWA_QUANTITY_FOR_ALL_VARIANTS.'</th>
              <th class="thProdAdd">'.PWA_ADD_OR_DELETE.'</th> 
              <th class="thProdSync">'.PWA_SYNC_QUANTITY.'</th>
              </tr>';
        
        while(!$products->EOF){ 

          // SUB
          $query = 'select * from '.TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK.' where products_id="'.(int)$products->fields['products_id'].'"
                    order by sort ASC;';

          $attribute_products = $db->Execute($query);

          $query = 'SELECT SUM(quantity) as total_quantity FROM '.TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK.' where products_id='.(int)$products->fields['products_id'];

          $attribute_quantity = $db->Execute($query);

          $synchronized = null;

          if ($_SESSION['pwas_class2']->zen_product_is_sba($products->fields['products_id'])) {
            if ($products->fields['products_quantity'] > $attribute_quantity->fields['total_quantity']) {
              $synchronized = '<br/><span class="warning">!!! Artikelmenge > Attributmenge !!!</span><br/><span class="smallinfo">Mengen abgleichen durchführen</span>';
            } else if ($products->fields['products_quantity'] != $attribute_quantity->fields['total_quantity']) {
              $synchronized = '<br/><span class="warning">!!! Artikelmenge < Attributmenge !!!</span><br/><span class="smallinfo">Mengen abgleichen durchführen</span>';
            }
          }

          $html .= '<tr>'."\n";
          $html .= '<td colspan="7">'."\n";
          $html .= '<div class="productGroup">'."\n";
          $html .= '<table>'. "\n";
            $html .= '<tr class="productRow">'."\n";
            $html .= '<td class="tdProdId">'.$products->fields['products_id'].'</td>';
            $html .= '<td class="tdProdName"><a href="'.zen_href_link(FILENAME_PRODUCT, "page=1&amp;product_type=".$products->fields['products_type']."&amp;cPath=".$products->fields['master_categories_id']."&amp;pID=".$products->fields['products_id']."&amp;action=new_product", 'NONSSL').'">'.$products->fields['products_name'].'</a></td>';
            
            if (STOCK_SHOW_IMAGE == 'true') {$html .= '<td class="tdProdImage">'.zen_info_image(zen_output_string($products->fields['products_image']), zen_output_string($products->fields['products_name']), "60", "60").'</td>';}
            
      
            $html .= '<td class="tdProdModel">'.$products->fields['products_model'] . '<br /><br /><a href="'.zen_href_link(FILENAME_ATTRIBUTES_CONTROLLER, "products_filter=&amp;products_filter=".$products->fields['products_id']."&amp;current_category_id=".$products->fields['master_categories_id'], 'NONSSL').'">' . BOX_CATALOG_CATEGORIES_ATTRIBUTES_CONTROLLER . '</a></td>';
            $html .= '<td class="tdProdQty">'.$products->fields['products_quantity'].$synchronized.'</td>';
            $html .= '<td class="tdProdAdd"><a href="'.zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, "action=add&amp;products_id=".$products->fields['products_id'] . '&amp;search_order_by=' . $search_order_by, 'NONSSL').'">' . PWA_ADD_QUANTITY . '</a></td>';
            $html .= '<td class="tdProdSync"><a href="'.zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, "action=resync&amp;products_id=".$products->fields['products_id'] . '&amp;search_order_by=' . $search_order_by, 'NONSSL').'">' . PWA_SYNC_QUANTITY . '</a></td>';
            $html .= '</tr>'."\n";
            $html .= '</table>'."\n";
            

          if($attribute_products->RecordCount() > 0){

              $html .= '<table class="stockAttributesTable">';
              $html .= '<tr>';
              $html .= '<th class="stockAttributesHeadingStockId">'.PWA_STOCK_ID.'</th>
                    
                    <th class="stockAttributesHeadingVariant">'.PWA_VARIANT.'</th>
                    <th class="stockAttributesHeadingQuantity">'.PWA_QUANTITY_IN_STOCK.'</th>
                    <th class="stockAttributesHeadingSort">'.PWA_SORT_ORDER.'</th>
                   
                    <th class="stockAttributesHeadingEdit">'.PWA_EDIT.'</th>
                    <th class="stockAttributesHeadingDelete">'.PWA_DELETE.'</th>';
              $html .= '</tr>';

              while(!$attribute_products->EOF){
                
                  $html .= '<tr id="sid-'. $attribute_products->fields['stock_id'] .'">';
                  $html .= '<td class="stockAttributesCellStockId">'."\n";
                  $html .= $attribute_products->fields['stock_id'];
                  $html .= '</td>'."\n";
                  
                  $html .= '<td class="stockAttributesCellVariant">'."\n";
                 
                  if (PRODUCTS_OPTIONS_SORT_ORDER == '0') {
                    $options_order_by= ' order by LPAD(po.products_options_sort_order,11,"0"), po.products_options_name';
                  } else {
                    $options_order_by= ' order by po.products_options_name';
                  }

                  $sort2_query = "SELECT DISTINCT pa.products_attributes_id, po.products_options_sort_order, po.products_options_name
                         FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                   LEFT JOIN " . TABLE_PRODUCTS_OPTIONS . " po on (po.products_options_id = pa.options_id) 
                         WHERE pa.products_attributes_id in (" . $attribute_products->fields['stock_attributes'] . ")
                         " . $options_order_by; 
                  $sort_class = $db->Execute($sort2_query);
                  $array_temp_sorted_array = array();
                  $attributes_of_stock = array();
                  while (!$sort_class->EOF) {
                    $attributes_of_stock[] = $sort_class->fields['products_attributes_id'];
                    $sort_class->MoveNext();
                  }

                  $attributes_output = array();
                  foreach($attributes_of_stock as $attri_id)
                  {
                      $stock_attribute = $this->get_attributes_name($attri_id, $_SESSION['languages_id']);
                      if ($stock_attribute['option'] == '' && $stock_attribute['value'] == '') {
                        // delete stock attribute
                        $db->Execute("DELETE FROM " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " WHERE stock_id = " . $attribute_products->fields['stock_id'] . " LIMIT 1;");
                      } else { 
                        $attributes_output[] = '<strong>'.$stock_attribute['option'].':</strong> '.$stock_attribute['value'].'<br />';
                      }
                  }

                  $html .= implode("\n",$attributes_output);

                  $html .= '</td>'."\n";
                  $html .= '<td class="stockAttributesCellQuantity editthis" id="stockid-quantity-'. $attribute_products->fields['stock_id'] .'">'.$attribute_products->fields['quantity'].'</td>'."\n";
                  $html .= '<td class="stockAttributesCellSort editthis" id="stockid-sort-'. $attribute_products->fields['stock_id'] .'">'.$attribute_products->fields['sort'].'</td>'."\n";
                
                  
                  $html .= '<td class="stockAttributesCellEdit">'."\n";
                  $html .= '<a href="'.zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, "action=edit&amp;products_id=".$products->fields['products_id'].'&amp;attributes='.$attribute_products->fields['stock_attributes'].'&amp;q='.$attribute_products->fields['quantity'] . '&amp;search_order_by=' . $search_order_by, 'NONSSL').'">'.PWA_EDIT_QUANTITY.'</a>'; //s_mack:prefill_quantity
                  $html .= '</td>'."\n";
                  $html .= '<td class="stockAttributesCellDelete">'."\n";
                  $html .= '<a href="'.zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, "action=delete&amp;products_id=".$products->fields['products_id'].'&amp;attributes='.$attribute_products->fields['stock_attributes'] . '&amp;search_order_by=' . $search_order_by, 'NONSSL').'">'.PWA_DELETE_VARIANT.'</a>';
                  $html .= '</td>'."\n";
                  $html .= '</tr>'."\n";
                 

                  $attribute_products->MoveNext();
              }
              $html .= '</table>';
          }
          $html .= '</div>'."\n";
          $products->MoveNext();   
      }
      $html .= '</table>' . "\n";
      $html .= zen_image_submit('button_save.gif', IMAGE_SAVE);
      $html .= '</form>'."\n";
        if (!isset($_GET['seachPID']) && !isset($_GET['pwas-search-button']) && !isset($_GET['updateReturnedPID'])) {
      $html .= '<table border="0" width="100%" cellspacing="0" cellpadding="2" class="pageResults">';
      $html .= '<tr>';
      $html .= '<td class="smallText" valign="top">';
      $html .= $products_split->display_count($products_query_numrows, 500000, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_PRODUCTS); 
      $html .= '</td>';
      $html .= '<td class="smallText" align="right">';
      $html .= $products_split->display_links($products_query_numrows, 500000, 500000, $_GET['page']);
      $html .= '</td>';
      $html .= '</tr>';
      $html .= '</table>';
        }

      return $html;
    }

//Used with jquery to edit qty on stock page and to save
function saveAttrib(){

  global $db;

    $i = 0;

    foreach ($_POST as $key => $value) {
      $matches = array();
      
      if(preg_match('/stockid-(.*?)-(.*)/', $key, $matches)) {  

        $tabledata = '';
        $stock_id = null;
        
        $tabledata = $matches[1];
        $stock_id = $matches[2];
        
        switch ($tabledata) {
          case 'quantity':
          case 'sort':

            $value = $db->getBindVarValue($value, 'float');
            break;
         
            
          default:
            next;
            break;
        }

        if (isset($stock_id) && (int)$stock_id > 0) {
          $sql = "UPDATE ".TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK." SET :field: = $value WHERE stock_id = :stock_id: LIMIT 1";
          $sql = $db->bindVars($sql, ':field:', $tabledata, 'noquotestring');
          $sql = $db->bindVars($sql, ':stock_id:', $stock_id, 'integer');
          $db->execute($sql);
          $i++;
        }
      }
      
    }
    unset ($key, $value);
    $html = print_r($_POST, true);
    $html = "$i DS SAVED";
    return $html;  
}

//Update attribute qty
function updateAttribQty($stock_id = null, $quantity = null){
  global $db;

  if(empty($quantity) || is_null($quantity)){$quantity = 0;}
  if( is_numeric($stock_id) && is_numeric($quantity) ){
      $query = 'update `'.TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK.'` set quantity=:quantity: where stock_id=:stock_id: limit 1';
      $query = $db->bindVars($query, ':quantity:', $quantity, 'passthru');
      $query = $db->bindVars($query, ':stock_id:', $stock_id, 'integer');
      $result = $db->execute($query);
  }

  return $result;
}

//New attribute qty insert
//The on duplicate updates an existing record instead of adding a new one
function insertNewAttribQty($products_id = null, $strAttributes = null, $quantity = 0){
  global $db;
 
  $strAttributes = $this->nullDataEntry($strAttributes);//sets proper quoting for input
  $result = null;
  
  //Set quantity to 0 if not valid input
  if( !(isset($quantity) && is_numeric($quantity)) ){
    $quantity = 0;
  }  
 
  if( isset($products_id) && is_numeric($products_id) && isset($strAttributes) && is_numeric($quantity) ){
      // Evaluate entry as compared to the desired uniqueness of data in the table.    
      

      // query for any duplicate records based on input where the input has a match to a non-empty key.
      $query = "SELECT count(*) AS total FROM " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " WHERE (products_id = :products_id: AND stock_attributes = :stock_attributes:)";
      $query = $db->bindVars($query, ':products_id:', $products_id, 'integer');
      $query = $db->bindVars($query, ':stock_attributes:', $strAttributes, 'string');
      
      
      
      $result_keys = $db->Execute($query);
      $insert_result = false;
      $update_result = false;
      
      // No duplication of desired key(s) of table. @TODO: May want to move this down after other reviews so that insertion
      //   code is written one time and use some sort of flag to skip the code that follows the if.
      if ($result_keys->fields['total'] == 0) {
          $insert_result = true;
          
          // Because no duplicates, information is considered new and is to be inserted.
          $query = "INSERT INTO " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " (`products_id`, `stock_attributes`, `quantity`) 
           values (:products_id:, :stock_attributes:, :quantity:)";
          $query = $db->bindVars($query, ':products_id:', $products_id, 'integer');
          
          $query = $db->bindVars($query, ':stock_attributes:', $strAttributes, 'string');
          $query = $db->bindVars($query, ':quantity:', $quantity, 'float');
          
          
      
          $result_final = $db->Execute($query);
          
      } else if($insert_result === false) {
          // A record has been found to match the provided data, now to identify how to proceed with the given data.
          //$result_multiple = null; // Establish base/known value for comparison/review.
          
          
          
              // Some level of duplicate exists, not sure if just one record or multiple records.  If one record then
              //   easy, just update that one record, if there are multiple records then the database already has some
              //   level of duplicate key that needs to be addressed as it has not been prevented previously.
              if ($result_keys->fields['total'] == 1) {
                  $update_result = true;
              } else {
                  // Need to handle the duplicate keys issue.
              }
          
          
         
          if ($update_result) {
              $query = "UPDATE " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " set `quantity` = :quantity: WHERE `products_id` = :products_id: AND `stock_attributes` = :stock_attributes:";
              $query = $db->bindVars($query, ':quantity:', $quantity, 'float');
              $query = $db->bindVars($query, ':products_id:', $products_id, 'integer');
              $query = $db->bindVars($query, ':stock_attributes:', $strAttributes, 'string');

              $result = $db->Execute($query);
          } else {
             
          }

      }
  }
  
  return $result;
}

//IN-WORK New attribute qty insert NEEDS MORE THOUGHT
//NEED one qty for multiple attributes, this does not accomplish this.
//The on duplicate updates an existing record instead of adding a new one
function insertTablePASR($products_id = null, $strAttributes = null, $quantity = null){
  
  global $db;
  
  $strAttributes = $this->nullDataEntry($strAttributes);//sets proper quoting for input


  if( is_numeric($products_id) && isset($strAttributes) ){
    
    //Get the last records ID
    $query = "select pas.products_attributes_stock_id
          from  ". TABLE_PRODUCTS_ATTRIBUTES_STOCK ."  pas
          order by pas.products_attributes_stock_id desc
          limit 1;";
    $result = $db->execute($query);
    $pasid = $result->fields['products_attributes_stock_id'];
    $pasid = ($pasid +1);//increment to next value
    $pasid = $this->nullDataEntry($pasid);//sets proper quoting for input
    
    $query = "insert into ". TABLE_PRODUCTS_ATTRIBUTES_STOCK_RELATIONSHIP ." (`products_id`,`products_attributes_id`, `products_attributes_stock_id`)
          values ($products_id, $strAttributes, $pasid)
          ON DUPLICATE KEY UPDATE
          `products_id` = $products_id,
          `products_attributes_id` =  $strAttributes;";
    $result = $db->execute($query);
  
    if( $result == 'true' ){
      //Get the last records ID
      $query = "select pasr.products_attributes_stock_relationship_id
            from ". TABLE_PRODUCTS_ATTRIBUTES_STOCK_RELATIONSHIP ." pasr
            order by pasr.products_attributes_stock_relationship_id desc
            limit 1;";
      $result = $db->execute($query);
      $pasrid = $result->fields['products_attributes_stock_relationship_id'];
    }
  }
  
  //Table PAS
  if( is_numeric($quantity) && is_numeric($pasrid) ){
    
    $query = "insert into ". TABLE_PRODUCTS_ATTRIBUTES_STOCK ." (`quantity`)
          values ($quantity)
          ON DUPLICATE KEY UPDATE
          `quantity` = $quantity;";
    $result = $db->execute($query);
    
 $db->execute($query);
    
  }
  else {
    //PANIC we had an error!!!
    exit;
  }

   return $result;
}

//IN-WORK New attribute qty insert NEEDS MORE THOUGHT
//products_attributes_stock INWORK New attribute qty insert NEEDS MORE THOUGHT
//NEED one qty for multiple attributes, this does not accomplish this.
//The on duplicate updates an existing record instead of adding a new one
function insertTablePAS($products_id = null, $quantity = null){

  global $db;

  
  //Table PASR (Inset and get $pasrid for next query)
  if( is_numeric($products_id) ){

    $query = "insert into ". TABLE_PRODUCTS_ATTRIBUTES_STOCK ." (`products_id`,`quantity`)
          values ($products_id, $quantity)
          ON DUPLICATE KEY UPDATE
          `products_id` = $products_id,
          `quantity` = $quantity;";
    $result = $db->execute($query);

  }
  else {
    //PANIC we had an error!!!
    exit('PANIC we had an error!!!');
  }

  return $result;
}
//ABOVE IN-WORK New attribute qty insert NEEDS MORE THOUGHT

//************************* Select list Function *************************//
//need to update to allow passing the data for $Item "$Item = 'ID:&nbsp;&nbsp;' . $result->fields["products_id"];"
//used to get a rows id number based on the table and column
//$Table = the table in the database to use.
//$Field = the field name from the table to use that has primary key or other uniqueness.
//The $Field is used as the default 'name' for the post event.
//$current = the current value in the database for this item.
//$providedQuery = This is a provided query that overrides the default $Table and $Field,
//note the $Field input (field name) is required to get returned data if name is not set or if there is not a $providedQuery.
function selectItemID($Table, $Field, $current = null, $providedQuery = null, $name= null, $id = null, $class = null, $style = null, $onChange = null){

  global $db;
  
  if(!$name){
    //use the $Field as the select NAME if no $name is provided
    $name = zen_db_input($Field);
  }
  if(!$id){
    //use the $Field as the select ID if no $id is provided
    $id = zen_db_input($Field);
  }

  if($providedQuery){
    $query = $providedQuery;//provided from calling object
  }
  else{
    $Table = zen_db_input($Table);
    $Field = zen_db_input($Field);
     $query = "SELECT * FROM $Table ORDER BY $Field ASC";
  }

  if($onChange){
    $onChange = "onchange=\"selectItem()\"";
  }
    
  $class = zen_db_input($class);
  
  $Output = "<SELECT class='".$class."' id='".$id."' name='".$name."' $onChange >";//create selection list
    $Output .= '<option value="" ' . $style . '>Wählen Sie einen Artikel aus der Liste...</option>';//adds blank entry as first item in list

  
    $i = 1;
  $result = $db->Execute($query);
     while(!$result->EOF){

       //set each row background color
       if($i == 1){
         $style = 'style="background-color:silver;"';
         $i = 0;
       }
       else{
         $style = null;//'style="background-color:blue;"';
         $i = 1;
       }
       
        $rowID = $result->fields["products_id"];
        $Item = 'ID:&nbsp;&nbsp;' . $result->fields["products_id"];
        $Item .= '&nbsp;&nbsp;Model:&nbsp;&nbsp;' . $result->fields["products_model"];
        $Item .= '&nbsp;&nbsp;Name:&nbsp;&nbsp;' . $result->fields["products_name"];
            
    if ( ($Item == $current AND $current != NULL) || ($rowID == $current AND $current != NULL) ){
        $Output .= '<option selected="selected" $style value="' . $rowID . '">' . $Item . '</option>';
      }
      else{
        $Output .= '<option ' . $style . ' value="' . $rowID . '">' . $Item . '</option>';
      }

    $result->MoveNext();
  }

  $Output .= "</select>";

  return $Output;
}

//NULL entry for database
function nullDataEntry($fieldtoNULL){

  //Need to test for absolute 0 (===), else compare will convert $fieldtoNULL to a number (null) and evauluate as a null 
  //This is due to PHP string to number compare "feature"
  if(!empty($fieldtoNULL) || $fieldtoNULL === 0){
    if((is_numeric($fieldtoNULL) && ($fieldtoNULL > 0 && strpos($fieldtoNULL, '0') !== 0 || $fieldtoNULL < 0 && strpos($fieldtoNULL, '0') !== 1)) || $fieldtoNULL === 0){
      $output = $fieldtoNULL;//returns number without quotes
    }
    else{
      $output = "'".$fieldtoNULL."'";//encases the string in quotes
    }
  }
  else{
    $output = 'null';
  }

  return $output;
}
  
  function zen_copy_sba_products_attributes($products_id_from, $products_id_to) {

    global $db;
    global $messageStack;
    global $copy_attributes_delete_first, $copy_attributes_duplicates_skipped, $copy_attributes_duplicates_overwrite, $copy_attributes_include_downloads, $copy_attributes_include_filename;
    global $zco_notifier;
    global $products_with_attributes_stock_admin_observe;
  
    if (file_exists(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/product_sba.php')) {
      include_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/product_sba.php'); 
    }


// Check for errors in copy request
    if ( (!zen_has_product_attributes($products_id_from, 'false') or !zen_products_id_valid($products_id_to)) or $products_id_to == $products_id_from or !$this->zen_product_is_sba($products_id_from) ) {
      if ($products_id_to == $products_id_from) {
        // same products_id
        $messageStack->add_session('<b>WARNING: Cannot copy from Product ID #' . $products_id_from . ' to Product ID # ' . $products_id_to . ' ... No copy was made' . '</b>', 'caution');
      } else {
        if (!zen_has_product_attributes($products_id_from, 'false')) {
          // no attributes found to copy
          $messageStack->add_session('<b>WARNING: No Attributes to copy from Product ID #' . $products_id_from . ' for: ' . zen_get_products_name($products_id_from) . ' ... No copy was made' . '</b>', 'caution');
        } else {
          if (!$this->zen_product_is_sba($products_id_from)) {
            $messageStack->add_session('<b>WARNING: No Stock By Attributes to copy from Product ID #' . $products_id_from . ' for: ' . zen_get_products_name($products_id_from) . ' ... No SBA attributes copy was made' . '</b>', 'caution');
          } else {
            // invalid products_id
            $messageStack->add_session('<b>WARNING: There is no Product ID #' . $products_id_to . ' ... No copy was made' . '</b>', 'caution');
          }
        }
      }
    } else {
// FIX HERE - remove once working

// check if product already has attributes
      $check_attributes = zen_has_product_attributes($products_id_to, 'false');

      if ($copy_attributes_delete_first=='1' and $check_attributes == true) {

        $products_with_attributes_stock_admin_observe->updateNotifyAttributeControllerDeleteAll($this, 'NOTIFY_ATTRIBUTE_CONTROLLER_DELETE_ALL', array('pID' => $products_id_to));



      }

// get attributes to copy from
      $products_copy_from = $db->Execute("select * from " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " where products_id='" . (int)$products_id_from . "'" . " order by stock_id");

      while ( !$products_copy_from->EOF ) {
// This must match the structure of your products_attributes table

        $update_attribute = false;
        $add_attribute = true;

        $attributes_copy_from_ids = array_map('trim', explode(',', $products_copy_from->fields['stock_attributes']));
        $attributes_copy_from_ids_data = array();
        
        // Generate array of attributes from the SBA data record
        foreach ($attributes_copy_from_ids as $key => $value) {
          $attributes_copy_from_ids_data_query = $db->Execute("SELECT options_id, options_values_id FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_attributes_id = " . (int)$value, false, false, 0, true);

          if (array_key_exists($attributes_copy_from_ids_data_query->fields['options_id'], $attributes_copy_from_ids_data)) {
            // Only handles a depth of one for the array, it does not handle an array of arrays of arrays or a third level of arrays.  Not currently
            //  as of ZC 1.5.5 considered a core feature of ZC, although some plugins might work that deep or deeper.
            if (is_array($attributes_copy_from_ids_data[$attributes_copy_from_ids_data_query->fields['options_id']]) 
                && !empty($attributes_copy_from_ids_data[$attributes_copy_from_ids_data_query->fields['options_id']])) {
                  // Array of arrays already exists and has at least one element associated with it.
              $attributes_copy_from_ids_data[$attributes_copy_from_ids_data_query->fields['options_id']][] = $attributes_copy_from_ids_data_query->fields['options_values_id'];
            } else {
              // Value was previously found/identified; however, now this should be identified as an array of arrays, so transition to that setup.
              $attributes_copy_from_ids_data[$attributes_copy_from_ids_data_query->fields['options_id']] = array($attributes_copy_from_ids_data[$attributes_copy_from_ids_data_query->fields['options_id']], $attributes_copy_from_ids_data_query->fields['options_values_id']);
            }  
          } else {
            $attributes_copy_from_ids_data[$attributes_copy_from_ids_data_query->fields['options_id']] = $attributes_copy_from_ids_data_query->fields['options_values_id'];
          }
        }
        unset($attributes_copy_from_ids_data_query);
        unset($attributes_copy_from_ids);
        unset($key);
        unset($value);

        $check_duplicate = $_SESSION['pwas_class2']->zen_get_sba_stock_attribute_id($products_id_to, $attributes_copy_from_ids_data, 'products');     
        
        
        if ($check_attributes == true) {
          if (!isset($check_duplicate) || $check_duplicate === false) {
            $update_attribute = false;
            $add_attribute = true;
          } else {
            if (!isset($check_duplicate) || $check_duplicate === false) {
              $update_attribute = false;
              $add_attribute = true;
            } else {
              $update_attribute = true;
              $add_attribute = false;
            }
          }
        } else {
          $update_attribute = false;
          $add_attribute = true;
        }

// die('UPDATE/IGNORE - Checking Copying from ' . $products_id_from . ' to ' . $products_id_to . ' Do I delete first? ' . ($copy_attributes_delete_first == '1' ? TEXT_YES : TEXT_NO) . ' Do I add? ' . ($add_attribute == true ? TEXT_YES : TEXT_NO) . ' Do I Update? ' . ($update_attribute == true ? TEXT_YES : TEXT_NO) . ' Do I skip it? ' . ($copy_attributes_duplicates_skipped=='1' ? TEXT_YES : TEXT_NO) . ' Found attributes in From: ' . $check_duplicate);

       if ($copy_attributes_duplicates_skipped == '1' and $check_duplicate !== false and $check_duplicate !== NULL) {
          // skip it
            $messageStack->add_session(TEXT_ATTRIBUTE_COPY_SKIPPING . $products_copy_from->fields['products_attributes_id'] . ' for Products ID#' . $products_id_to, 'caution');
        } else {
          $products_attributes_id_to = $_SESSION['pwas_class2']->zen_get_sba_stock_attribute($products_id_to, $attributes_copy_from_ids_data, 'products', true);
          $products_attributes_combo = $_SESSION['pwas_class2']->zen_get_sba_attribute_ids($products_id_to, $attributes_copy_from_ids_data, 'products', true);

          if ($add_attribute == true) {
            // New attribute - insert it
            $db->Execute("insert into " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " (products_id, stock_attributes, quantity, sort)
                          values ('" . (int)$products_id_to . "',
           
            '" . $products_attributes_id_to . "',
            '" . (float)$products_copy_from->fields['quantity'] . "',
           
            '" . $products_copy_from->fields['sort'] . "')");

            $messageStack->add_session(TEXT_SBA_ATTRIBUTE_COPY_INSERTING . $products_copy_from->fields['stock_id'] . ' for Products ID#' . $products_id_to, 'caution');
          }
          if ($update_attribute == true) {
            // Update attribute - Just attribute settings not ids
            $db->Execute("update " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " set
           
            quantity='" . (float)$products_copy_from->fields['quantity'] . "',
            
            sort='" . $products_copy_from->fields['sort'] . "'"
             . " where products_id=" . (int)$products_id_to . " and stock_attributes= '" . $products_attributes_id_to . "'");
//             . " where products_id='" . $products_id_to . "'" . " and options_id= '" . $products_copy_from->fields['options_id'] . "' and options_values_id='" . $products_copy_from->fields['options_values_id'] . "' and attributes_image='" . $products_copy_from->fields['attributes_image'] . "' and attributes_price_base_included='" . $products_copy_from->fields['attributes_price_base_included'] .  "'");

            $messageStack->add_session(TEXT_SBA_ATTRIBUTE_COPY_UPDATING . $products_copy_from->fields['stock_id'] . ' für Artikel ID#' . $products_id_to, 'caution');
          }
        }

        $products_copy_from->MoveNext();
      } // end of products with sba attributes while loop

       // reset products_price_sorter for searches etc.
       zen_update_products_price_sorter($products_id_to);
    } // end of no attributes or other errors
  } // eof: zen_copy_sba_products_attributes function
  
////
// Return a product ID with attributes as provided on the store front
    function zen_sba_get_uprid($prid, $params) {
      if ( (is_array($params)) && (!strstr($prid, ':')) ) {
        $uprid = $prid;
        foreach($params as $option => $value) {
          if (is_array($value)) {
            foreach($value as $opt => $val) {
              $uprid = $uprid . '{' . $option . '}' . trim($opt);
            }
          } else {
          //CLR 030714 Add processing around $value. This is needed for text attributes.
              $uprid = $uprid . '{' . $option . '}' . trim($value);
          }
        }      //CLR 030228 Add else stmt to process product ids passed in by other routines.
        $md_uprid = '';
  
        $md_uprid = md5($uprid);
        return $prid . ':' . $md_uprid;
      } else {
        return $prid;
      }
      
    }

function convertDropdownsToSBA()
{
  global $db, $resultMmessage, $failed;

  if (defined('PRODUCTS_OPTIONS_TYPE_SELECT_SBA')) {
    $sql = "UPDATE " . TABLE_PRODUCTS_OPTIONS . " SET `products_options_type` = :products_options_type_select_sba:
            WHERE `products_options_type` = :products_options_type_select:";

    $sql = $db->bindVars($sql, ':products_options_type_select_sba:', PRODUCTS_OPTIONS_TYPE_SELECT_SBA, 'integer');
    $sql = $db->bindVars($sql, ':products_options_type_select:', PRODUCTS_OPTIONS_TYPE_SELECT, 'integer');

    $db->Execute($sql);
    if($db->error){
      $msg = ' Error Message: ' . $db->error;
      $failed = true;
    }
  } else {
    $msg = ' Error Message: PRODUCTS_OPTIONS_TYPE_SELECT_SBA nicht definiert.';
    $failed = true;
  }

  if (isset($resultMmessage)) {
    array_push($resultMmessage, 'product_attribute_combo field updated ' . $msg);
  }

}

function convertSBAToSBA()
{
  global $db, $resultMmessage, $failed;

  if (defined('PRODUCTS_OPTIONS_TYPE_SELECT_SBA')) {

    $results_track = array(); // Array to track what has been identified.

    // Need to identify which option values are listed in the SBA table and then update them if they are a dropdown select.
    $sql = 'SELECT stock_attributes FROM ' . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . ' WHERE stock_attributes != \'\'';

    $results = $db->Execute($sql);

    while (!$results->EOF)
    {
      $results_array = explode(',', $results->fields['stock_attributes']);

      // Need one or more checks before using the results_array
      foreach ($results_array as $key=> $value)
      {
        $products_options_id_sql = 'SELECT options_id FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' WHERE products_attributes_id = :products_attributes_id:';
        $products_options_id_sql = $db->bindVars($products_options_id_sql, ':products_attributes_id:', $value, 'integer');

        if (method_exists($db, 'ExecuteNoCache')) {
          $products_options_id = $db->ExecuteNoCache($products_options_id_sql);
        } else {
          $products_options_id = $db->Execute($products_options_id_sql, false, false, 0, true);
        }

        $product_type_sql = 'SELECT products_options_type FROM ' . TABLE_PRODUCTS_OPTIONS . ' WHERE products_options_id = :products_options_id:';
        $product_type_sql = $db->bindVars($product_type_sql, ':products_options_id:', $products_options_id->fields['options_id'], 'integer');

        if (method_exists($db, 'ExecuteNoCache')) {
          $product_type = $db->ExecuteNoCache($product_type_sql);
        } else {
          $product_type = $db->Execute($product_type_sql, false, false, 0, true);
        }

        // Since converting select type to SBA select, don't do anything to the list unless it is a select.
        if ($product_type->fields['products_options_type'] != PRODUCTS_OPTIONS_TYPE_SELECT) {
          continue;
        }

        if (!isset($results_track[$products_options_id->fields['options_id']])) {
          $results_track[$products_options_id->fields['options_id']] = $products_options_id->fields['options_id'];
          // Do update here? or wait till later?
        }
      }
      unset($results_array);

      $results->MoveNext();
    }

    unset($results);

    sort($results_track); // This will sequence the option_ids so that the "completion" point is better understood.

    foreach ($results_track as $result_key => $result)
    {
      $sql = "UPDATE " . TABLE_PRODUCTS_OPTIONS . " po SET po.products_options_type = :products_options_type:
              WHERE `products_options_id` = :products_options_id:";

      $sql = $db->bindVars($sql, ':products_options_type:', PRODUCTS_OPTIONS_TYPE_SELECT_SBA, 'integer');
      $sql = $db->bindVars($sql, ':products_options_id:', $result, 'integer');

      $db->Execute($sql);

      if($db->error){
        $msg = ' Error Message: ' . $db->error;
        $failed = true;

        break;
      }
    }
    unset($results_track);

  } else {
    $msg = ' Error Message: PRODUCTS_OPTIONS_TYPE_SELECT_SBA not defined.';
    $failed = true;
  }

  if (isset($resultMmessage)) {
    array_push($resultMmessage, 'product_attribute_combo field updated ' . $msg);
  }
}

function convertNonSBAToDropdown()
{
  global $db, $resultMmessage, $failed;

  if (defined('PRODUCTS_OPTIONS_TYPE_SELECT_SBA')) {

    $results_track = array(); // Array to track what has been identified.

    // Need to identify which option values are listed in the SBA table and then update them if they are a dropdown select.
    $sql = 'SELECT stock_attributes FROM ' . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . ' WHERE stock_attributes != \'\'';

    if (method_exists($db, 'ExecuteNoCache')) {
      $results = $db->ExecuteNoCache($sql);
    } else {
      $results = $db->Execute($sql, false, false, 0, true);
    }

    while (!$results->EOF)
    {
      $results_array = explode(',', $results->fields['stock_attributes']);

      // Need one or more checks before using the results_array
      foreach ($results_array as $key=> $value)
      {
        $products_options_id_sql = 'SELECT options_id FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' WHERE products_attributes_id = :products_attributes_id:';
        $products_options_id_sql = $db->bindVars($products_options_id_sql, ':products_attributes_id:', $value, 'integer');

        if (method_exists($db, 'ExecuteNoCache')) {
          $products_options_id = $db->ExecuteNoCache($products_options_id_sql);
        } else {
          $products_options_id = $db->Execute($products_options_id_sql, false, false, 0, true);
        }

        $product_type_sql = 'SELECT products_options_type FROM ' . TABLE_PRODUCTS_OPTIONS . ' WHERE products_options_id = :products_options_id:';
        $product_type_sql = $db->bindVars($product_type_sql, ':products_options_id:', $products_options_id->fields['options_id'], 'integer');

        if (method_exists($db, 'ExecuteNoCache')) {
          $product_type = $db->ExecuteNoCache($product_type_sql);
        } else {
          $product_type = $db->Execute($product_type_sql, false, false, 0, true);
        }

        // If the option type isn't the SBA Select item, then no work could need to be done so continue searching.
        if ($product_type->fields['products_options_type'] != PRODUCTS_OPTIONS_TYPE_SELECT_SBA) {
          continue;
        }

        if (empty($results_track) || !isset($results_track[$products_options_id->fields['options_id']])) {
          $results_track[$products_options_id->fields['options_id']] = $products_options_id->fields['options_id']; // This value holds all of the SBA product that have an options_id assigned to the SBA Select
          // Do update here? or wait till later?
        }
      }
      unset($results_array);

      $results->MoveNext();
    }

    unset($results);

    // Need to pull all of the option_ids that are assigned to the SBA select type to be able to cross them off of the previously discovered list.

    $sql = 'SELECT products_options_id FROM ' . TABLE_PRODUCTS_OPTIONS . ' WHERE products_options_type = :products_options_type:';
    $sql = $db->bindVars($sql, ':products_options_type:', PRODUCTS_OPTIONS_TYPE_SELECT_SBA, 'integer');

    if (method_exists($db, 'ExecuteNoCache')) {
      $sba_select_options = $db->ExecuteNoCache($sql);
    } else {
      $sba_select_options = $db->Execute($sql, false, false, 0, true);
    }

    // Remove from the list of SBA identified SBA select options and add to the list those identified but not associated with an SBA product.
    while (!$sba_select_options->EOF) {
      if (array_key_exists($sba_select_options->fields['products_options_id'], $results_track)) {
        unset($results_track[$sba_select_options->fields['products_options_id']]);
      } else {
        $results_track[$sba_select_options->fields['products_options_id']] = $sba_select_options->fields['products_options_id'];
      }

      $sba_select_options->MoveNext();
    }

    //sort($results_track); // This will sequence the option_ids so that the "completion" point is better understood.

    foreach ($results_track as $result_key => $result)
    {
      $sql = "UPDATE " . TABLE_PRODUCTS_OPTIONS . " po SET po.products_options_type = :products_options_type:
              WHERE `products_options_id` = :products_options_id:";

      $sql = $db->bindVars($sql, ':products_options_type:', PRODUCTS_OPTIONS_TYPE_SELECT, 'integer');
      $sql = $db->bindVars($sql, ':products_options_id:', $result, 'integer');

      $db->Execute($sql);

      if($db->error){
        $msg = ' Error Message: ' . $db->error;
        $failed = true;

        break;
      }
    }
    unset($results_track);

  } else {
    $msg = ' Error Message: PRODUCTS_OPTIONS_TYPE_SELECT_SBA not defined.';
    $failed = true;
  }

  if (isset($resultMmessage)) {
    array_push($resultMmessage, 'product_attribute_combo field updated ' . $msg);
  }
}

    
  function zen_product_is_sba($product_id) {
    global $db;
    
    if (!isset($product_id) && !is_numeric(zen_get_prid($product_id))) {
      return null;
    }
    
    $inSBA_query = 'SELECT * 
                    FROM information_schema.tables
                    WHERE table_schema = :your_db:
                    AND table_name = :table_name:
                    LIMIT 1;';
    $inSBA_query = $db->bindVars($inSBA_query, ':your_db:', DB_DATABASE, 'string');
    $inSBA_query = $db->bindVars($inSBA_query, ':table_name:', TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'string');
    $SBA_installed = $db->Execute($inSBA_query, false, false, 0, true);
    
    if (!$SBA_installed->EOF && $SBA_installed->RecordCount() > 0) {
      $isSBA_query = 'SELECT COUNT(stock_id) as total 
                      FROM ' . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . ' 
                      WHERE products_id = :products_id: 
                      LIMIT 1;';
      $isSBA_query = $db->bindVars($isSBA_query, ':products_id:', $product_id, 'integer');
      $isSBA = $db->Execute($isSBA_query);
      
      if ($isSBA->fields['total'] > 0) {
        return true;
      } else {
        return false;
      }
    }
    
    return false;
  }
  
}//end of class
