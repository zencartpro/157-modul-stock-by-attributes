<?php
/**
 * @package Stock by Attributes for Zen Cart German
 * @copyright Copyright 2003-2022 Zen Cart Development Team
 * Zen Cart German Version - www.zen-cart-pro.at
 * @copyright Portions Copyright 2003 osCommerce
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 * @version $Id: products_with_attributes_stock.php 2022-03-05 17:00:14Z webchills $
 */

$SBAversion = 'Version 2.1.0';
//add required referenced files
require('includes/application_top.php');
require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();
$msg = '';
//new object from class
$stock = $products_with_attributes_stock_class;

//set language
if (isset($_SESSION['languages_id'])) {
  $language_id = $_SESSION['languages_id'];
} else {

  $languages = zen_get_languages();
  $languages_array = array();
  $languages_selected = DEFAULT_LANGUAGE;
  for ($i = 0, $n = count($languages); $i < $n; $i++) {
    $languages_array[] = array('id' => $languages[$i]['code'],
      'text' => $languages[$i]['name']);
    if ($languages[$i]['directory'] == $_SESSION['language']) {
      $languages_selected = $languages[$i]['code'];
    }
  }
  $language_id = $languages_selected;
}

//action
if (isset($_GET['action']) && zen_not_null($_GET['action'])) {
  $action = zen_db_input(trim($_GET['action']));
} else {
  $action = null;
}

if (zen_not_null($action)) {
  if (!isset($products_filter)) $products_filter = 0;

  $_GET['products_filter'] = $products_filter = (isset($_GET['products_filter']) ? (int)$_GET['products_filter'] : (int)$products_filter);
  $_GET['attributes_id'] = (isset($_GET['attributes_id']) ? (int)$_GET['attributes_id'] : 0);

  $_GET['current_category_id'] = $current_category_id = (isset($_GET['current_category_id']) ? (int)$_GET['current_category_id'] : (int)$current_category_id);

  if (isset($_POST['products_filter'])) $_POST['products_filter'] = (int)$_POST['products_filter'];
  if (isset($_POST['current_category_id'])) $_POST['current_category_id'] = (int)$_POST['current_category_id'];
  if (isset($_POST['products_options_id_all'])) $_POST['products_options_id_all'] = (int)$_POST['products_options_id_all'];
  if (isset($_POST['current_category_id'])) $_POST['current_category_id'] = (int)$_POST['current_category_id'];
  if (isset($_POST['categories_update_id'])) $_POST['categories_update_id'] = (int)$_POST['categories_update_id'];

// set categories and products if not set
  if ($products_filter == '' and $current_category_id != '') {
    $sql =     "select ptc.*
    from " . TABLE_PRODUCTS_TO_CATEGORIES . " ptc
    left join " . TABLE_PRODUCTS_DESCRIPTION . " pd
    on ptc.products_id = pd.products_id
    and pd.language_id = '" . (int)$_SESSION['languages_id'] . "'
    where ptc.categories_id='" . $current_category_id . "'
    order by pd.products_name";
    $new_product_query = $db->Execute($sql);
    $products_filter = $new_product_query->fields['products_id'];
    if ($products_filter != '') {
      zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
    }
  } else {
    if ($products_filter == '' and $current_category_id == '') {
      $reset_categories_id = zen_get_category_tree('', '', '0', '', '', true);
      $current_category_id = $reset_categories_id[0]['id'];
      $sql = "select ptc.*
      from " . TABLE_PRODUCTS_TO_CATEGORIES . " ptc
      left join " . TABLE_PRODUCTS_DESCRIPTION . " pd
      on ptc.products_id = pd.products_id and pd.language_id = '" . (int)$_SESSION['languages_id'] . "'
      where ptc.categories_id='" . $current_category_id . "'
      order by pd.products_name";
      $new_product_query = $db->Execute($sql);
      $products_filter = isset($new_product_query->fields['products_id']) ? $new_product_query->fields['products_id'] : 0;
      $_GET['products_filter'] = $products_filter;
    }
  }

//case selection 'add', 'edit', 'confirm', 'execute', 'delete_all', 'delete', 'resync', 'resync_all', 'auto_sort'
switch ($action) {
      case 'set_products_filter':
        $products_filter = $_GET['products_filter'] = (int)$_POST['products_filter'];
        $_GET['current_category_id'] = (int)$_POST['current_category_id'];
        $action='';
        zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK,  /*zen_get_all_get_params() . '&' .*/ (isset($_SESSION['page']) ? $_SESSION['page'] . '&' : '') . 'products_filter=' . $_GET['products_filter'] . '&current_category_id=' . $_GET['current_category_id']));
        break;

  case 'add':
    $hidden_form = '';

    if (isset($_GET['products_id']) && (int) $_GET['products_id'] > 0) {
      $products_id = (int) $_GET['products_id'];
    }
    if (isset($_POST['products_id']) && (int) $_POST['products_id'] > 0) {
      $products_id = (int) $_POST['products_id'];
    }

    if (isset($products_id)) {

      if (zen_products_id_valid($products_id)) {

        $product_name = zen_get_products_name($products_id);
        $product_attributes = $stock->get_products_attributes($products_id, $language_id);

        $hidden_form .= zen_draw_hidden_field('products_id', $products_id) . "\n";
      } else {
        zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, zen_get_all_get_params(array('action')), $request_type));
      }
    } else {

      $query = 'SELECT DISTINCT
                        pa.products_id, d.products_name
                      FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa
                          left join ' . TABLE_PRODUCTS_DESCRIPTION . ' d on (pa.products_id = d.products_id)
                      WHERE d.language_id= :language_id: 
                      order by d.products_name';
      $query = $db->bindVars($query, ':language_id:', $language_id, 'integer');
      
      $products = $db->execute($query);

      while (!$products->EOF) {
        $products_array_list[] = array(
          'id' => $products->fields['products_id'],
          'text' => $products->fields['products_name']
        );
        $products->MoveNext();
      }
    }
    break;

  case 'edit':
    $hidden_form = '';
    if (isset($_GET['products_id']) && (int) $_GET['products_id'] > 0) {
      $products_id = (int)$_GET['products_id'];
    }
    if (isset($_POST['products_id']) && (int) $_POST['products_id'] > 0) {
      $products_id = (int)$_POST['products_id'];
    }

    if (isset($_GET['attributes']) && $_GET['attributes'] != '') {
      $attributes = $_GET['attributes'];
    }

    if (isset($products_id) && isset($attributes)) {
      $attributes = explode(',', $attributes);
      foreach ($attributes as $attribute_id) {
        $hidden_form .= zen_draw_hidden_field('attributes[]', $attribute_id) . "\n";
        $attributes_list[] = $stock->get_attributes_name($attribute_id, $language_id);
      }
      $hidden_form .= zen_draw_hidden_field('products_id', $products_id) . "\n";
    } else {
      zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, zen_get_all_get_params(array('action')), $request_type));
    }
    break;

  case 'confirm':
    if (isset($_POST['products_id']) && (int) $_POST['products_id'] > 0) {

      if (!isset($_POST['quantity']) || !is_numeric($_POST['quantity'])) {
        $messageStack->add_session("Menge fehlt!", 'failure');
        zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . $_POST['products_id'], $request_type));
      }

      $hidden_form = '';
      $s_mack_noconfirm = '';

      $products_id = (int)$_POST['products_id'];
      $product_name = zen_get_products_name($products_id);

      

      if (is_numeric($_POST['quantity'])) {
        $quantity = (float) $_POST['quantity'];
      }

      $attributes = $_POST['attributes'];

      foreach ($attributes as $attribute_id) {
        $hidden_form .= zen_draw_hidden_field('attributes[]', $attribute_id) . "\n";
        $attributes_list[] = $stock->get_attributes_name($attribute_id, $_SESSION['languages_id']);
      }
      $hidden_form .= zen_draw_hidden_field('products_id', $products_id) . "\n";
      $hidden_form .= zen_draw_hidden_field('quantity', $quantity) . "\n";
     
      //These are used in the GET thus it must match the same name used in the $_GET[''] calls
      $s_mack_noconfirm .= "products_id=" . $products_id . "&amp;"; //s_mack:noconfirm
      $s_mack_noconfirm .= "quantity=" . $quantity . "&amp;"; //s_mack:noconfirm
     

      //sort($attributes); // Sort will rearrange the values that were passed to this function.
      $stock_attributes = implode(',', $attributes);

      $hidden_form .= zen_draw_hidden_field('attributes', $stock_attributes) . "\n";
      $s_mack_noconfirm .= 'attributes=' . $stock_attributes . '&amp;'; //kuroi: to pass string not array

      $query = 'select * 
            from ' . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . ' 
            where products_id = :products_id: 
            and stock_attributes = :stock_attributes:';
      $query = $db->bindVars($query, ':products_id:', $products_id, 'integer');
      $query = $db->bindVars($query, ':stock_attributes:', $stock_attributes, 'string');
      $stock_check = $db->Execute($query);

      if (!$stock_check->EOF) {
        $hidden_form .= zen_draw_hidden_field('add_edit', 'edit');
        $hidden_form .= zen_draw_hidden_field('stock_id', $stock_check->fields['stock_id']);
        $s_mack_noconfirm .= "stock_id=" . $stock_check->fields['stock_id'] . "&amp;"; //s_mack:noconfirm
        $s_mack_noconfirm .="add_edit=edit&amp;"; //s_mack:noconfirm
        $add_edit = 'edit';
      } else {
        $hidden_form .= zen_draw_hidden_field('add_edit', 'add') . "\n";
        $s_mack_noconfirm .="add_edit=add&amp;"; //s_mack:noconfirm
      }
    } else {
      zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, zen_get_all_get_params(array('action')), $request_type));
    }
    zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, $s_mack_noconfirm . "action=execute", $request_type));
    break;

  case 'execute':

    if ($_GET['attributes']) {
      $attributes = $_GET['attributes']; // Why is this overriding the POST version of the same? Shouldn't it be one or the other not both?
    } 
    if (isset($_POST['attributes'])) {
      $attributes = $_POST['attributes'];
    }

    if ($_GET['products_id']) {
      $products_id = doubleval($_GET['products_id']);  // Why is this overriding the POST version of the same? Shouldn't it be one or the other not both?
    } 
    if (isset($_POST['products_id'])) {
      $products_id = doubleval($_POST['products_id']);
    }   
    
    if (isset($_GET['quantity'])/* && $_GET['quantity']*/) {
      $quantity = doubleval($_GET['quantity']);
    } 
    //if invalid entry return to product
    if ((int) $products_id === 0 || is_null($products_id)) {
      $messageStack->add_session("fehlende oder falsche Artikel ID!", 'failure');
      zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . $products_id, $request_type));
    } elseif (!is_numeric($quantity) || is_null($quantity) && $quantity != 0) {
      $messageStack->add_session("fehlende oder falsche Menge!", 'failure');
      zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . $products_id, $request_type));
    } elseif (is_null($attributes) || str_replace(',', null, $attributes) == null) {
      $messageStack->add_session("kein Attribut ausgewählt!", 'failure');
      zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . $products_id, $request_type));
    }

    /*
      Updated by PotteryHouse
      originally by michael mcinally <mcinallym@picassofish.com>
      Allow inserting "ALL" attributes at once
     */
    if ((isset($_POST['add_edit']) && ($_POST['add_edit'] == 'add')) || (isset($_GET['add_edit']) && ($_GET['add_edit'] == 'add'))) { //s_mack:noconfirm
      $attributes = ltrim($attributes, ','); //remove extra comma separators

      if (preg_match("/\|/", $attributes) && preg_match("/\;/", $attributes)) {
        $saveResult = null;
        $messageStack->add_session("Do NOT mix 'All - Attributes' and 'All - Attributes - Combo'", 'failure');
      } elseif (preg_match("/\|/", $attributes)) {
        // All attributes individually added.
        //explode array on ,
        $attributes = preg_replace("/\,{2,}/i", ",", $attributes);
        $arrTemp = preg_split("/\,/", $attributes);
        $arrMain = array();
//        $intCount = 0;

        for ($i = 0, $arrTempCount = count($arrTemp); $i < $arrTempCount; $i++) {
          //explode array on |
          $arrTemp[$i] = preg_replace("/\|{2,}/i", "|", $arrTemp[$i]);
          $arrTemp1 = preg_split("/\|/", $arrTemp[$i]);
          $arrMain[] = $arrTemp1;

          foreach ($arrMain as $key => $value) {
            if (is_array($value)) {
              foreach ($value as $k2 => $v2) {
                if (!zen_not_null($v2)) {
                  unset($arrMain[$key][$k2]);
                }
              }
              if (empty($arrMain[$key])) {
                unset($arrMain[$key]);
              }
            } else {
              if (!zen_not_null($value)) {
                unset($arrMain[$key]);
              }
            }
            if (!empty($arrMain[$key])) {
              $arrMain[$key] = array_values($arrMain[$key]);
            }
          }

          $arrMain = array_values($arrMain);


        }
        $intVars = count($arrMain);
        $arrNew = array();

        if ($intVars >= 1) {
          $a = 0;
          while ($a < $intVars) {
            //adds each attribute (no combinations)
            for ($i = 0, $arrMainSize = count($arrMain[$a]); $i < $arrMainSize; $i++) {
              $arrNew[] = array($arrMain[$a][$i]);
            }
            $a++;
          }

          //loop through the list of variables / attributes
          //add each one to the database
          for ($i = 0, $arrNewSize = count($arrNew); $i < $arrNewSize; $i++) {
            //used to add multi attribute combinations at one time
            $strAttributes = implode(",", $arrNew[$i]);
            
            $saveResult = $stock->insertNewAttribQty($products_id, $strAttributes, $quantity); 
          }
        }
      } elseif (preg_match("/\;/", $attributes)) {
        // Attributes combined with others.
        //explode array on ,
        $attributes = preg_replace("/,{2,}/i", ",", $attributes);
        $arrTemp = preg_split("/\,/", $attributes);
        $arrMain = array();
//        $intCount = 0;

        for ($i = 0, $arrTempSize = count($arrTemp); $i < $arrTempSize; $i++) {
          //explode array on ;
          $arrTemp[$i] = preg_replace("/;{2,}/i", ";", $arrTemp[$i]);
          $arrTemp1 = preg_split("/\;/", $arrTemp[$i]);
          $arrMain[] = $arrTemp1;

          foreach ($arrMain as $key => $value) {
            if (is_array($value)) {
              foreach ($value as $k2 => $v2) {
                if (!zen_not_null($v2)) {
                  unset($arrMain[$key][$k2]);
                }
              }
              if (empty($arrMain[$key])) {
                unset($arrMain[$key]);
              }
            } else {
              if (!zen_not_null($value)) {
                unset($arrMain[$key]);
              }
            }
            if (count($arrMain[$key])) {
              $arrMain[$key] = array_values($arrMain[$key]);
            }
          }

          $arrMain = array_values($arrMain);          

        }
        $intVars = count($arrMain);
        $arrNew = array();

        $arrNew = return_attribute_combinations($arrMain, $intVars);     

        //loop through the list of variables / attributes
        //add each one to the database
        for ($i = 0, $arrNewSize = count($arrNew); $i < $arrNewSize; $i++) {
          //used to add multi attribute combinations at one time
          sort($arrNew[$i]); // Ensures that values are in order prior to imploding
          $strAttributes = implode(",", $arrNew[$i]);
          
          $saveResult = $stock->insertNewAttribQty($products_id, $strAttributes, $quantity);
        }
      } else {
        // Individual or N/A attributes
        //used for adding one attribute or attribute combination at a time
        $strAttributes = ltrim($attributes, ","); //remove extra , if present
        $strAttributes = rtrim($strAttributes, ","); //remove extra , if present
        $strAttributes = preg_replace("/,{2,}/i", ",", $strAttributes);
        $arrAttributes = array_map('zen_string_to_int', explode(",", $strAttributes));
        sort($arrAttributes);
        $strAttributes = implode(",", $arrAttributes);
        
        $saveResult = $stock->insertNewAttribQty($products_id, $strAttributes, $quantity);
      }
    } elseif (isset($_POST['add_edit']) &&  $_POST['add_edit'] == 'edit' || ($_GET['add_edit'] == 'edit')) { 
      if ($_GET['stock_id']) {
        $stock_id = (int)$_GET['stock_id'];
      } 
      if (isset($_POST['stock_id']) && $_POST['stock_id'] !== '') {
        $stock_id = (int)$_POST['stock_id']; 
      }
      if (!($stock_id > 0)) { 
        zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, zen_get_all_get_params(array('action')), $request_type));
      }
      //update existing records
      $saveResult = $stock->updateAttribQty($stock_id, $quantity);
      
    }    
    $messageStack->add_session("Artikel ID $products_id erfolgreich aktualisiert", 'success');
   
    zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . (int)$products_id, $request_type));

    break;

  case 'delete_all':
    if (isset($_POST['confirm'])) {
      // delete item
      if ($_POST['confirm'] == TEXT_YES) {
        $query = 'delete from ' . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . ' where products_id= :products_id:';
        $query = $db->bindVars($query, ':products_id:', $_POST['products_id'], 'integer');
        $db->Execute($query);
        $query_result = $db->Execute("SELECT ROW_COUNT() as rows;");
        //Use the button 'Sync Quantities' when needed, or uncomment the line below if you want it done automatically.
        //$stock->update_parent_products_stock((int)$_POST['products_id']);//keep this line as option, but I think this should not be done automatically.
        $messageStack->add_session(($query_result->fields['rows'] > 1 ? sprintf(PWA_DELETED_VARIANT_ALL, $query_result->fields['rows']) : PWA_DELETED_VARIANT), 'failure');
        zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . (int)$_POST['products_id'], $request_type));
      } else {
        zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . (int)$_POST['products_id'], $request_type));
      }
    }
    break;

  case 'delete':
    if (isset($_POST['confirm'])) {
      // delete item
      if ($_POST['confirm'] == TEXT_YES) {
        $query = 'delete from ' . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . ' where products_id= :products_id: and stock_attributes=:stock_attributes: limit 1';
        $query = $db->bindVars($query, ':products_id:', $_POST['products_id'], 'integer');
        $query = $db->bindVars($query, ':stock_attributes:', $_POST['attributes'], 'string');
        $db->Execute($query);
        //Use the button 'Sync Quantities' when needed, or uncomment the line below if you want it done automatically.
        //$stock->update_parent_products_stock((int)$_POST['products_id']);//keep this line as option, but I think this should not be done automatically.
        $messageStack->add_session(PWA_DELETED_VARIANT, 'failure');
        zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . (int)$_POST['products_id'], $request_type));
      } else {
        zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . (int)$_POST['products_id'], $request_type));
      }
    }
    break;

  case 'resync':
    if (isset($_GET['products_id']) && (int)$_GET['products_id'] > 0) {

      $stock->update_parent_products_stock((int) $_GET['products_id']);
      $messageStack->add_session('Menge des Artikels aktualisiert', 'success');
      zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . (int)$_GET['products_id'], $request_type));
    } else {
      zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, '', $request_type));
    }
    break;

  case 'resync_all':
    $stock->update_all_parent_products_stock();
    $messageStack->add_session('Mengen der Elternartikel erfolgreich aktualisiert', 'success');
    zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, '', $request_type));
    break;

  case 'auto_sort':
    // get all attributes
    $sql = $db->Execute("SELECT stock_id, stock_attributes FROM " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " ORDER BY stock_id ASC;");
    $count = $sql->RecordCount(); // mc12345678 why not use $sql->RecordCount()? If doesn't return correct value, then above SQL needs to be called to include a cache "reset".
    $array_sorted_array = array();
    $skip_update = false;
    
    while (!$sql->EOF) {
      // get the attributes for sort to get the sort order

      if (PRODUCTS_OPTIONS_SORT_ORDER == '0') {
        $options_order_by= ' order by LPAD(po.products_options_sort_order,11,"0"), po.products_options_name';
      } else {
        $options_order_by= ' order by po.products_options_name';
      }

      $sort_query = "SELECT DISTINCT pa.products_attributes_id, pov.products_options_values_sort_order as sort
             FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
             LEFT JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov on (pov.products_options_values_id = pa.options_values_id)
             LEFT JOIN " . TABLE_PRODUCTS_OPTIONS . " po on (po.products_options_id = pa.options_id) 
             WHERE pa.products_attributes_id in (:stock_attributes:)
             " . $options_order_by; // ORDER BY po.products_options_sort_order ASC, pov.products_options_values_sort_order ASC;"; // pov.products_options_values_sort_order ASC";
      $sort_query = $db->bindVars($sort_query, ':stock_attributes:', $sql->fields['stock_attributes'], 'noquotestring');
      $sort = $db->Execute($sort_query);
      if ($sort->RecordCount() > 1) {
        $skip_update = true;
        $array_temp_sorted_array = array();
        while (!$sort->EOF) {
          $array_temp_sorted_array[$sort->fields['products_attributes_id']] = $sort->fields['sort'];
          $sort->MoveNext();
        }
        $array_sorted_array[$sort->RecordCount()][] = array('stock_id' => $sql->fields['stock_id'], 'sort_order' => $array_temp_sorted_array);
      } else {
        $sort_val = $sort->fields['sort'];
        // update sort in db
        $db->Execute("UPDATE " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " set sort = '" . $sort_val . "' WHERE stock_id = '" . (int)$sql->fields['stock_id'] . "' LIMIT 1;");
      }
      $sql->MoveNext();
    }
    if ($skip_update === true) {
      ksort($array_sorted_array); // Sort the array by size of sub-arrays.
      foreach ($array_sorted_array as &$part_array) {
        $t = array();
        $name = array();
        foreach ($part_array as &$sorter) {
          $num_elem = 0;
          foreach ($sorter['sort_order'] as $key => $val) {
            $t[$num_elem][] = $val;
            $num_elem++;
          }
          $name[] = $sorter['stock_id'];
        }
        unset($sorter);

        $param = array();
        for ($i=0; isset($t[$i]); $i++) {
          $param[] = &$t[$i];
          $param[] = SORT_ASC;
          $param[] = SORT_NUMERIC;
        }
        if(!empty($param)) {
          $param[] = &$name;
          call_user_func_array('array_multisort', $param);
        }
        //array_multisort($t[0],$t[1],..$t[n],$name); // Need to figure out how to get these sub-arrays populated.
        // Do update to table using $sort_order variable, increment $sort_order after each update, keep on moving...
        $icount = 0;
        foreach ($name as $value) {
          $db->Execute("UPDATE " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " set sort = '" . $icount * 10 . "' WHERE stock_id = '" . $value . "' LIMIT 1;");
          $icount++;
        }
        unset($value);
      }
      unset($part_array);
    }
    $messageStack->add_session($count . ' Lagerbestandattribute aktualisiert mit Sortierung nach dem ersten Attribut', 'success');
    zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, '', $request_type));
    break;

  default:

    $products_filter = $_GET['products_filter'] = (int)$_POST['products_filter'];
    // Show a list of the products
    break;
}
} // EOF zen_not_null($_GET['action'])

  if (isset($_GET['search_order_by']) || isset($_POST['search_order_by'])) {
    $search_order_by = $_GET['search_order_by'];
    if (isset($_POST['search_order_by'])) {
      $search_order_by = $_POST['search_order_by'];
    }
  } else {
    $search_order_by = 'products_model';
  }

  // Add a level of sanitization to the process.
  // If field is to be pulled from a table other than TABLE_PRODUCTS, then will need to 
  //   check that table as well with the "default" being selected in the last/inner chosing.
  if (!$sniffer->field_exists(TABLE_PRODUCTS, $search_order_by)) {
    if (!$sniffer->field_exists(TABLE_PRODUCTS_DESCRIPTION, $search_order_by)) {
      $search_order_by = 'products_model';
    }
  }


  //global $template_dir; // Why does this variable need to be made global? Isn't it already in the global space?
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
    <title><?php echo TITLE; ?></title>
    <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
    <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
    <link rel="stylesheet" type="text/css" href="includes/products_with_attributes_stock_ajax.css">
    <script language="javascript" type="text/javascript" src="../ajax/jquery-2.0.2.min.js"></script>
<script language="javascript" type="text/javascript" src="../ajax/jquery.form.js"></script>
<script language="javascript" type="text/javascript" src="../ajax/products_with_attributes_stock_ajax.js"></script>
    <script type="text/javascript" src="includes/menu.js"></script>
    <script type="text/javascript" src="includes/general.js"></script>
    <script type="text/javascript">
   <!--
    function init()
   {
     cssjsmenu('navbar');
     if (document.getElementById)
     {
       var kill = document.getElementById('hoverJS');
       kill.disabled = true;
     }
   }
   // -->
    </script>
  </head>
  <body onLoad="init()" >
      <!-- header //-->
      <?php require(DIR_WS_INCLUDES . 'header_sba.php'); ?>
      <!-- header_eof //-->
      <div class="container-fluid">
        <!-- body //-->
    
    <script language="javascript"><!--
function go_search() {
  if (document.search_order_by.selected.options[document.search_order_by.selected.selectedIndex].value != "none") {
    location = "<?php echo zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'page=' . (isset($_GET['page']) && $_GET['page'] ? $_GET['page'] : 1)); ?>&search_order_by="+document.search_order_by.selected.options[document.search_order_by.selected.selectedIndex].value;
  }
}
//--></script>

<div class="pageHeading"><?php echo PWA_WELCOME ?></div>
<br class="clearBoth">
      <!-- body_text_eof //-->

    <?php
//case selection 'add', 'edit', 'delete_all', 'delete',  'confirm'
/*if (zen_not_null($action))*/ {
    switch ($action) {
      case 'add':
        if (isset($products_id)) {

          echo zen_draw_form('sba_post_form', FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'action=confirm' . '&search_order_by=' . $search_order_by, 'post', '', true) . "\n";
          echo $hidden_form;
          ?><p><strong><?php echo $product_name; ?></strong></p>
<?php 

          foreach ($product_attributes as $option_name => $options) {

            //get the option/attribute list
            $sql = "select distinct popt.products_options_type, popt.products_options_name, pot.products_options_types_name" . /*, 
                     pa.attributes_display_only, pa.products_attributes_id */ "
            from " . TABLE_PRODUCTS_OPTIONS . " popt
              left join " . TABLE_PRODUCTS_ATTRIBUTES . " pa ON (pa.options_id = popt.products_options_id)
              left join " . TABLE_PRODUCTS_OPTIONS_TYPES . " pot ON (popt.products_options_type = pot.products_options_types_id)
            where pa.products_id = :products_id:
              and pa.products_attributes_id = :products_attributes_id:
              and popt.language_id = :language_id:
              " /*. $order_by*/;

            $sql = $db->bindVars($sql, ':products_id:', $products_id, 'integer');
            $sql = $db->bindVars($sql, ':products_attributes_id:', $options[0]['id'], 'integer');
            $sql = $db->bindVars($sql, ':language_id:', $language_id, 'integer');
            $products_options_type = $db->Execute($sql);

            if ($products_options_type->fields['products_options_type'] != PRODUCTS_OPTIONS_TYPE_READONLY && $products_options_type->fields['products_options_type'] != PRODUCTS_OPTIONS_TYPE_CHECKBOX) {
              // MULTI
              $arrValues = array();
              if (is_array($options)) {
                if (!empty($options)) {
                  foreach ($options as $k => $a) {
                    if ($a['display_only']) {
                      unset($options[$k]);
                    } else {
                      $arrValues[] = $a['id'];
                    }
                  }
                }
              }

             
              array_unshift($options, array('id' => implode("|", $arrValues), 'text' => 'Alle'));
             
              ?><p><strong><?php echo $option_name; ?>: </strong><?php
              echo zen_draw_pull_down_menu('attributes[]', $options);
              ?></p>
<?php
            } elseif ($products_options_type->fields['products_options_type'] == PRODUCTS_OPTIONS_TYPE_READONLY && PRODINFO_ATTRIBUTE_DYNAMIC_STOCK_READ_ONLY == 'true') {
              // MULTI
              if (is_array($options)) {
                if (!empty($options)) {
                  ?><div class="READONLY" style="border: 1px solid black;
    line-height: normal;"
    ><p><strong><?php echo $products_options_type->fields['products_options_types_name'] . ': ' . $option_name; ?>: </strong></p><?php
                  foreach ($options as $k => $a) {
                    $arrValues = array();
                    $arrValues[] = array('id'=>$a['id'], 'text'=>$a['text']);
                    
                    array_unshift($arrValues, array('id' => $arrValues[count($arrValues) - 1]['id'] . "|", 'text' => 'Alle'));
                   
                    ?><p><strong><?php echo $a['text']; ?>: </strong><?php
                    echo zen_draw_pull_down_menu('attributes[]', $arrValues);
                    ?></p>
<?php
                  }
                  ?></div><?php
                }
              }
            } elseif ($products_options_type->fields['products_options_type'] == PRODUCTS_OPTIONS_TYPE_CHECKBOX) {
              if (is_array($options)) {
                if (!empty($options)) {
                  ?><div class="CHECKBOX" style="border: 1px solid black;
    line-height: normal;"
    ><p><strong><?php echo $products_options_type->fields['products_options_types_name'] . ': ' . $option_name; ?>: </strong></p><?php
                  foreach ($options as $k => $a) {
                    $arrValues = array();
                    $arrValues[] = array('id'=>$a['id'], 'text'=>$a['text']);
                    
                    array_unshift($arrValues, array('id' => $arrValues[count($arrValues) - 1]['id'] . "|", 'text' => 'Alle'));
                    
                    ?><p><strong><?php echo $a['text']; ?>: </strong><?php
                    echo zen_draw_pull_down_menu('attributes[]', $arrValues);
                    ?></p>
<?php
                  }
                  ?></div><?php
                }
              }
            }
          }

          ?>

<hr><?php

         
         

          
          echo $msg . '<p><strong>' . PWA_QUANTITY . '</strong>' . zen_draw_input_field('quantity') . '</p>' . "\n";
        } else {

          echo zen_draw_form('sba_post_form', FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'action=add' . '&search_order_by=' . $search_order_by, 'post', '', true) . "\n";
          echo zen_draw_pull_down_menu('products_id', $products_array_list) . "\n";
        }
        ?>
          <p><?php echo zen_draw_input_field('PWA_SUBMIT', PWA_SUBMIT, '', true, 'submit', true); ?></p>
        </form>
          <?php
          break;

        case 'edit':
          echo zen_draw_form('sba_post_form', FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'action=confirm' . '&search_order_by=' . $search_order_by, 'post', '', true) . "\n";
          echo '<h3>' . zen_get_products_name($products_id) . '</h3>';

          foreach ($attributes_list as $attributes) {
            echo '<p><strong>' . $attributes['option'] . ': </strong>' . $attributes['value'] . '</p>';
          }

          echo $hidden_form;
          ?><p><strong>Menge: </strong><?php echo zen_draw_input_field('quantity', $_GET['q']) . '</p>' . "\n"; //s_mack:prefill_quantity
          ?>
        <p><?php echo zen_draw_input_field('PWA_SUBMIT', PWA_SUBMIT, '', true, 'submit', true); ?></p>
      </form>
          <?php
          break;

        case 'delete_all':
          if (!isset($_POST['confirm'])) {

            echo zen_draw_form('sba_post_form', FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'action=delete_all' . '&search_order_by=' . $search_order_by, 'post', '', true) . "\n";
            echo PWA_DELETE_VARIANTS_CONFIRMATION;
            foreach ($_GET as $key => $value) {
              echo zen_draw_hidden_field($key, $value);
            }
            ?>
        <p><?php echo zen_draw_input_field('confirm', TEXT_YES, '', true, 'submit', true); ?> * <?php echo zen_draw_input_field('confirm', TEXT_NO, '', true, 'submit', true); ?></p>
      </form>
      <?php
    }
    break;

          case 'delete':
          if (!isset($_POST['confirm'])) {

            echo zen_draw_form('sba_post_form', FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'action=delete' . '&search_order_by=' . $search_order_by, 'post', '', true) . "\n";
            echo PWA_DELETE_VARIANT_CONFIRMATION;
            foreach ($_GET as $key => $value) {
              echo zen_draw_hidden_field($key, $value);
            }
            ?>
        <p><?php echo zen_draw_input_field('confirm', TEXT_YES, '', true, 'submit', true); ?> * <?php echo zen_draw_input_field('confirm', TEXT_NO, '', true, 'submit', true); ?></p>
      </form>
      <?php
    }
    break;

  case 'confirm':

		echo '<h3>Bestätige '.$product_name.'</h3>';

		foreach($attributes_list as $attributes)
		{
			echo '<p><strong>'.$attributes['option'].': </strong>'.$attributes['value'].'</p>';
		}

		echo '<p><strong>Menge</strong>'.$quantity.'</p>';
    echo zen_draw_form('sba_post_form', FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'action=execute' . '&search_order_by=' . $search_order_by, 'post', '', true) . "\n";
		echo $hidden_form;
?>
    <p><?php echo zen_draw_input_field('PWA_SUBMIT', PWA_SUBMIT, '', true, 'submit', true); ?></p>
	</form>

<?php 	
break;
  default:
    $products_filter = '';
    if (isset($_POST['products_filter'])) {
      $products_filter = $_GET['products_filter'] = (int)$_POST['products_filter'];
    }
    //return to page (previous edit) data
    ?><?php

    $seachPID = null;
    $seachBox = null;
    if (isset($_GET['updateReturnedPID']) || isset($_POST['updateReturnedPID'])) {
      $seachPID = doubleval(trim($_GET['updateReturnedPID']));
      $seachBox = doubleval(trim($_GET['updateReturnedPID']));
      if (isset($_POST['updateReturnedPID'])) {
        $seachPID = doubleval(trim($_POST['updateReturnedPID']));
        $seachBox = doubleval(trim($_POST['updateReturnedPID']));
      }
      $seachBox = '' . $seachBox . '';
      $seachPID = '' . $seachPID . '';
    } elseif (isset($_GET['search']) || isset($_POST['search'])) {
      $SearchRange = '';
      $seachBox = trim($_GET['search']);
      if (isset($_POST['search'])) {
        $seachBox = trim($_POST['search']);
      }
      $s = zen_db_input($seachBox);
      $w = " AND ( p.products_id = '$s' OR d.products_name LIKE '%$s%' OR p.products_model LIKE '$s%' ) ";
      $query_products = "select distinct pa.products_id FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_DESCRIPTION . " d, " . TABLE_PRODUCTS . " p WHERE d.language_id='" . $language_id . "' and pa.products_id = d.products_id and pa.products_id = p.products_id " . $w . " order by d.products_name " . $SearchRange . "";
      $products_answer = $db->Execute($query_products);
      if (!$products_answer->EOF && $products_answer->RecordCount() == 1 ) {
        $seachPID = $products_answer->fields['products_id'];
      }
      $seachBox = '' . $seachBox . '';
      $seachPID = '' . $seachPID . '';
    } elseif (isset($_GET['seachPID']) || isset($_POST['seachPID'])) {
      $seachPID = doubleval(trim($_GET['seachPID']));
      $seachBox = doubleval(trim($_GET['seachPID']));
      if (isset($_POST['seachPID'])) {
       $seachPID = doubleval(trim($_POST['seachPID']));
       $seachBox = doubleval(trim($_POST['seachPID'])); 
      }
      $seachBox = '' . $seachBox . '';
      $seachPID = '' . $seachPID . '';
    } else if (isset($_GET['products_filter']) || isset($_POST['products_filter'])) {
      $products_filter = $seachBox = $seachPID = doubleval(trim($_GET['products_filter']));
      if (isset($_POST['products_filter'])) {
        $products_filter = $seachBox = $seachPID = doubleval(trim($_POST['products_filter']));
      }
      $seachBox = '' . $seachBox . '';
      $seachPID = '' . $seachPID . '';
    }

    //search box displayed only option
    $SBAsearchbox = null; //initialize
    $searchList = null;
    
   

    
      //Product Selection Listing at top of page
      $searchList = 'select distinct pa.products_id, pd.products_name,
                 p.products_model
                   FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa
                  left join ' . TABLE_PRODUCTS_DESCRIPTION . ' pd on (pa.products_id = pd.products_id)
                  left join ' . TABLE_PRODUCTS . ' p on (pa.products_id = p.products_id)
                WHERE pd.language_id = ' . $language_id . '
                order by :search_order_by:'; //order by may be changed to: products_id, products_model, products_name
      $searchList = $db->bindVars($searchList, ':search_order_by:', $search_order_by, 'noquotestring');

      echo zen_draw_form('pwas-search', FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'search_order_by=' . $search_order_by, 'get', '', true) . "";
      echo '</div">';
      echo '<div id="sbasearch">';
      echo $searchList = $stock->selectItemID(TABLE_PRODUCTS_ATTRIBUTES, 'pa.products_id', $seachPID, $searchList, 'seachPID', 'seachPID', 'selectSBAlist');
      echo zen_draw_input_field('pwas-search-button', 'Los!', '', true, 'submit', true);
      echo zen_draw_hidden_field('search_order_by', $search_order_by);
      echo '</div>';
?>
      </form>
      <div id="sbasortorder">Sortiere nach: 
        <form name="search_order_by" action="<?php echo zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'search_order_by=' . $search_order_by, 'SSL'); ?>">
          <select name="selected" onChange="go_search()">
            <option value="products_model"<?php if ($search_order_by == 'products_model') { echo ' SELECTED'; } ?>><?php echo PWA_PRODUCT_MODEL; ?></option>
            <option value="products_id"<?php if ($search_order_by == 'products_id') { echo ' SELECTED'; } ?>><?php echo PWA_PRODUCT_ID; ?></option>
            <option value="products_name"<?php if ($search_order_by == 'products_name') { echo ' SELECTED'; } ?>><?php echo PWA_PRODUCT_NAME; ?></option>
          </select>
        </form>
    </div>


    
    <?php echo '<div id="sbasearchgeneral">';
    echo zen_draw_form('pwas-search', FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'search_order_by=' . $search_order_by, 'get', 'id="pwas-search2"', true); ?>Suchen:  <?php 
    echo zen_draw_input_field('search', $seachBox, 'id="pwas-filter"', true, 'text', true);
    echo zen_draw_input_field('pwas-search-button', 'Suchen', 'id="pwas-search-button"', true, 'submit', true);
    echo zen_draw_hidden_field('search_order_by', $search_order_by);
    
    ?></form>
    <span style="margin-right:10px;">&nbsp;</span>
    
    <a href="<?php echo zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'search_order_by=' . $search_order_by, $request_type); ?>">Zurücksetzen</a>
   
    <div id="sbasortresults"><?php echo $SBAsearchbox; ?></div></div><?php 
//      require(DIR_WS_MODULES . FILENAME_PREV_NEXT);

/////
// BOF PREVIOUS NEXT

  if (!isset($current_category_id)) $current_category_id = 0;

  if (!isset($prev_next_list) || $prev_next_list == '') {
// calculate the previous and next

    $result = $db->Execute("select products_type from " . TABLE_PRODUCTS . " where products_id=" . (int)$products_filter);
    $check_type = ($result->EOF) ? 0 : $result->fields['products_type'];
    define('PRODUCT_INFO_PREVIOUS_NEXT_SORT', zen_get_configuration_key_value_layout('PRODUCT_INFO_PREVIOUS_NEXT_SORT', $check_type));

    // sort order
    switch(PRODUCT_INFO_PREVIOUS_NEXT_SORT) {
      case (0):
        $prev_next_order= ' order by LPAD(p.products_id,11,"0")';
        break;
      case (1):
        $prev_next_order= " order by pd.products_name";
        break;
      case (2):
        $prev_next_order= " order by p.products_model";
        break;
      case (3):
        $prev_next_order= " order by p.products_price, pd.products_name";
        break;
      case (4):
        $prev_next_order= " order by p.products_price, p.products_model";
        break;
      case (5):
        $prev_next_order= " order by pd.products_name, p.products_model";
        break;
      default:
        $prev_next_order= " order by pd.products_name";
        break;
      }


// set current category
    $current_category_id = (isset($_GET['current_category_id']) ? (int)$_GET['current_category_id'] : $current_category_id);

    if (!$current_category_id) {
      $sql = "SELECT categories_id
              from   " . TABLE_PRODUCTS_TO_CATEGORIES . "
              where  products_id =" .  (int)$products_filter;

      $cPath_row = $db->Execute($sql);
      $current_category_id = 0;
      if (!$cPath_row->EOF) {
        $current_category_id = (int)$cPath_row->fields['categories_id'];
      }
    }

    $sql = "select p.products_id, pd.products_name
            from   " . TABLE_PRODUCTS . " p, "
                     . TABLE_PRODUCTS_DESCRIPTION . " pd, "
                     . TABLE_PRODUCTS_TO_CATEGORIES . " ptc
            where  p.products_id = pd.products_id and pd.language_id= '" . (int)$_SESSION['languages_id'] . "' and p.products_id = ptc.products_id and ptc.categories_id = '" . (int)$current_category_id . "'" .
            $prev_next_order
            ;

    $products_ids = $db->Execute($sql);
  }

// reset if not already set for display
  (isset($_GET['products_filter']) && $_GET['products_filter'] == '' ? (int)$_GET['products_filter'] = $products_filter : '');
  (isset($_GET['current_category_id']) && $_GET['current_category_id'] == '' ? (int)$_GET['current_category_id'] = $current_category_id : '');

  $id_array = array();
  while (!$products_ids->EOF) {
    $id_array[] = $products_ids->fields['products_id'];
    $products_ids->MoveNext();
  }

  $position = $counter = 0;
// if invalid product id skip
  if (count($id_array)) {
    reset ($id_array);
    foreach ($id_array as $key => $value) {
      if ($value == $products_filter) {
        $position = $counter;
        if ($key == 0) {
          $previous = -1; // it was the first to be found
        } else {
          $previous = $id_array[$key - 1];
        }
        if (isset($id_array[$key + 1])) {
          $next_item = $id_array[$key + 1];
        } else {
          $next_item = $id_array[0];
        }
      }
      $last = $value;
      $counter++;
    }

    if ($previous == -1) $previous = $last;

    $sql = "select categories_name
            from   " . TABLE_CATEGORIES_DESCRIPTION . "
            where  categories_id = '" . (int)$current_category_id . "' AND language_id = '" . (int)$_SESSION['languages_id'] . "'";

    $category_name_row = $db->Execute($sql);
  } 



  switch(true) {
  case (strstr($PHP_SELF, FILENAME_ATTRIBUTES_CONTROLLER)):
    $curr_page = FILENAME_ATTRIBUTES_CONTROLLER;
    break;
  case (strstr($PHP_SELF, FILENAME_PRODUCTS_TO_CATEGORIES)):
    $curr_page = FILENAME_PRODUCTS_TO_CATEGORIES;
    break;
  default:
    $curr_page = FILENAME_PRODUCTS_PRICE_MANAGER;
    break;
  }
  $curr_page = FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK;
// to display use products_previous_next_display.php



      /* set an option in configuration table */ ?>
        <td colspan="2"><table>
        <!-- bof: products_previous_next_display -->
  <tr>
    <td><table border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td colspan="3" class="main" align="left"><strong>
          <?php 
          if (!defined('HEADING_TITLE')) {
            define('HEADING_TITLE', '');
          }
          if (!defined('HEADING_TITLE2')) {
            define('HEADING_TITLE2', '');
          }
          echo (defined('HEADING_TITLE') && HEADING_TITLE == '' ? HEADING_TITLE2 : HEADING_TITLE); ?>&nbsp;-&nbsp;<?php echo zen_output_generated_category_path($current_category_id); ?></strong>
          <?php echo '<br />' . TEXT_CATEGORIES_PRODUCTS; ?>
        </td>
      </tr>
      <tr>
        <td colspan="3" class="main" align="left"><?php echo (zen_get_categories_status($current_category_id) == '0' ? TEXT_CATEGORIES_STATUS_INFO_OFF : '') . (zen_get_products_status($products_filter) == '0' ? ' ' . TEXT_PRODUCTS_STATUS_INFO_OFF : ''); ?></td>
      </tr>
      <tr>
        <td colspan="3" class="main" align="center"><?php echo ($counter > 0 ? (PREV_NEXT_PRODUCT) . ($position+1 . "/" . $counter) : '&nbsp;'); ?></td>
      </tr>
      <tr>
        <td align="left" class="main"><?php echo zen_draw_form('new_category', $curr_page, '', 'get'); ?>&nbsp;&nbsp;<?php echo zen_draw_pull_down_menu('current_category_id', zen_get_category_tree('', '', '0', '', '', true), $current_category_id, 'onChange="this.form.submit();"'); ?><?php if (isset($_GET['products_filter'])) echo zen_draw_hidden_field('products_filter', $_GET['products_filter']); echo zen_hide_session_id(); echo zen_draw_hidden_field('action', 'new_cat'); ?>&nbsp;&nbsp;</form></td>
      </tr>
    </table></td>
  </tr>
      <tr><form name="set_products_filter_id" <?php echo 'action="' . zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'action=set_products_filter') . '"'; ?> method="post"><?php echo zen_draw_hidden_field('products_filter', $products_filter); ?><?php echo zen_draw_hidden_field('current_category_id', $current_category_id); ?><?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']); ?>
<?php if (isset($_GET['products_filter']) && $_GET['products_filter'] != '') { ?>
        <td colspan="2"><table border="0" cellspacing="0" cellpadding="2">
<!--            <td class="attributes-even" align="center"><?php echo zen_draw_products_pull_down('products_filter', 'size="10" id="pwas-filter"', '', true, $_GET['products_filter'], true, true); ?></td>-->
            <td class="attributes-even" align="center"><?php echo zen_draw_products_pull_down('products_filter', 'size="10" id="pwas-filter-drop"', array('')/* @todo should list all product that do not have attributes as an array */, true, $_GET['products_filter'], true, true); /* pwas-filter*/ ?></td>
            <td class="main" align="right" valign="top"><?php echo zen_image_submit('button_display.gif', IMAGE_DISPLAY); ?></td>
<?php    echo zen_draw_input_field('pwas-search-button', 'Suche', 'id="pwas-search-button"', true, 'submit', true);
         echo zen_draw_hidden_field('search_order_by', $search_order_by);
?>
          </tr>
        </table></td>
<?php } ?>
      </form></tr>

<!-- eof: products_previous_next_display -->

        </table></td>

  <hr />
   
    <a class="forward" style="float:right;" href="<?php echo zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, "action=resync_all" . '&search_order_by=' . $search_order_by, $request_type); ?>"><strong>Alle Mengen synchronisieren</strong></a><br class="clearBoth" /><hr />
    <div id="pwa-table"><?php 
    echo $stock->displayFilteredRows(false, null, $seachPID);
    ?></div><?php
    break;
}
}
?>
</div>
<!-- body_text_eof //-->
      </div>
      <!-- body_eof //-->
      <!-- footer //-->
  <?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
      <!-- footer_eof //-->
    </body>
  </html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>