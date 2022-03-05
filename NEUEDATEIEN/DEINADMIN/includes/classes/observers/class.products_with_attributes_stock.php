<?php

/**************
 *
 *
 * Updated 15-11-14 mc12345678
 */

class products_with_attributes_stock_admin extends base {

  //
  private $_customid = array();
  private $_productI;  
/*  private $_productI;
  
  private $_i;

  private $_stock_info = array();
  
  private $_attribute_stock_left;

  private $_stock_values;*/
  
  /*
   * This is the observer for the admin side of SBA currently covering admin/includes/functions/general.php file to support Stock By Attributes when the order is being processed at the end of the purchase.
   */
  function __construct() {

    $attachNotifier = array();
    $attachNotifier[] = 'NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT';
   
    $attachNotifier[] = 'NOTIFIER_ADMIN_ZEN_DELETE_PRODUCTS_ATTRIBUTES';
    $attachNotifier[] = 'NOTIFY_PACKINGSLIP_INLOOP';
    $attachNotifier[] = 'NOTIFY_PACKINGSLIP_IN_ATTRIB_LOOP';
    $attachNotifier[] = 'NOTIFY_ATTRIBUTE_CONTROLLER_DELETE_ATTRIBUTE';
    $attachNotifier[] = 'NOTIFY_ATTRIBUTE_CONTROLLER_DELETE_ALL';
    $attachNotifier[] = 'NOTIFY_ATTRIBUTE_CONTROLLER_DELETE_OPTION_NAME_VALUES';
    $attachNotifier[] = 'NOTIFY_ADMIN_PRODUCT_COPY_TO_ATTRIBUTES';

    $attachNotifier[] = 'OPTIONS_NAME_MANAGER_DELETE_OPTION';
    $attachNotifier[] = 'OPTIONS_NAME_MANAGER_UPDATE_OPTIONS_VALUES_DELETE';
    $attachNotifier[] = 'OPTIONS_VALUES_MANAGER_DELETE_VALUE';
    $attachNotifier[] = 'OPTIONS_VALUES_MANAGER_DELETE_VALUES_OF_OPTIONNAME';
   

    $this->attach($this, $attachNotifier); 
  }  

  /*
   * Function that is activated when NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT is encountered as a notifier.
   */
  // NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT  //admin/includes/functions/general.php
  function updateNotifierAdminZenRemoveProduct(&$callingClass, $notifier, $paramsArray, & $product_id, & $ptc) {
    global $db;
    $db->Execute("delete from " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . "
                  where products_id = '" . (int)$product_id . "'");
  }
  
  //NOTIFIER_ADMIN_ZEN_REMOVE_ORDER //admin/includes/functions/general.php

  
  // NOTIFIER_ADMIN_ZEN_DELETE_PRODUCTS_ATTRIBUTES //admin/includes/functions/general.php
  function updateNotifierAdminZenDeleteProductsAttributes (&$callingClass, $notifier, $paramsArray, & $delete_product_id){
    global $db;
    /* START STOCK BY ATTRIBUTES */
    $db->Execute("delete from " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " where products_id = '" . (int)$delete_product_id . "'");
    /* END STOCK BY ATTRIBUTES */

  }
  
  

 

 
  
  // NOTIFY_ATTRIBUTE_CONTROLLER_DELETE_ATTRIBUTE
  function updateNotifyAttributeControllerDeleteAttribute(&$callingClass, $notifier, $paramsArray, &$attribute_id) {
    global $db;
    
    $stock_ids = zen_get_sba_ids_from_attribute($attribute_id);

    if (!empty($stock_ids) && is_array($stock_ids)) {
      $db->Execute("delete from " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " 
           where stock_id in (" . implode(',', $stock_ids) . ")");
    }

  }
  
  // NOTIFY_ATTRIBUTE_CONTROLLER_DELETE_ALL', array('pID' => $_POST['products_filter']));
  function updateNotifyAttributeControllerDeleteAll(&$callingClass, $notifier, $paramsArray) {
    // , array('pID' => $_POST['products_filter']));
    
    global $db;
    
    $pID = $paramsArray['pID'];

    $db->Execute("DELETE IGNORE FROM " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " 
         WHERE products_id = " . (int)$pID);
    
  }
  
  // 'NOTIFY_ATTRIBUTE_CONTROLLER_DELETE_OPTION_NAME_VALUES', array('pID' => $_POST['products_filter'], 'options_id' => $_POST['products_options_id_all']));
  function updateNotifyAttributeControllerDeleteOptionNameValues(&$callingClass, $notifier, $paramsArray) {
    //  array('pID' => $_POST['products_filter'], 'options_id' => $_POST['products_options_id_all'])

    global $db;
    
    $pID = $paramsArray['pID'];
    $options_id = $paramsArray['options_id'];
    
    $delete_attributes_options_id = $db->Execute("select * from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id='" . $pID . "' and options_id='" . $options_id . "'");

    while (!$delete_attributes_options_id->EOF) {
      $stock_ids = zen_get_sba_ids_from_attribute($delete_attributes_options_id->fields['products_attributes_id']);
    
    if (!empty($stock_ids) && is_array($stock_ids)) {
        $delete_attributes_stock_options_id_values = $db->Execute("delete from " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " where products_id='" . $pID . "' and stock_id in (" . implode(',', $stock_ids) . ")");
      }
      $delete_attributes_options_id->MoveNext();
    }

  }

  // NOTIFY_MODULES_COPY_TO_CONFIRM_ATTRIBUTES
  function updateNotifyModulesCopyToConfirmAttributes(&$callingClass, $notifier, $paramsArray) {

/*    if ( $_POST['copy_sba_attributes']=='copy_sba_attributes_yes' and $_POST['copy_as'] == 'duplicate' ) {
      global $products_with_attributes_stock_class;

      $products_id_from = $paramsArray['products_id_from'];
      $products_id_to = $paramsArray['products_id_to'];

      $products_with_attributes_stock_class->zen_copy_sba_products_attributes($products_id_from, $products_id_to);
    }*/
  }
  
  // OPTIONS_NAME_MANAGER_DELETE_OPTION', array('option_id' => $option_id, 'options_values_id' => (int)$remove_option_values->fields['products_options_values_id']));
  function updateOptionsNameManagerDeleteOption(&$callingClass, $notifier, $paramsArray) {
    //array('option_id' => $option_id, 'options_values_id' => (int)$remove_option_values->fields['products_options_values_id']));
    
    global $db;
    
    $option_id = $paramsArray['option_id'];
    $options_values_id = $paramsArray['options_values_id'];
    
    $remove_attributes_query = $db->Execute("select products_attributes_id from " . TABLE_PRODUCTS_ATTRIBUTES . " where options_id = " . (int)$option_id . " and options_values_id = " . (int)$options_values_id);
    unset($option_id);
    unset($options_values_id);

    while (!$remove_attributes_query->EOF) {
      $remove_attributes_list[] = $remove_attributes_query->fields['products_attributes_id'];
      $remove_attributes_query->MoveNext();
    }
    unset($remove_attributes_query);

    $stock_ids = zen_get_sba_ids_from_attribute($remove_attributes_list);
    unset($remove_attributes_list);

    if (!empty($stock_ids) && is_array($stock_ids)) {
      $db->Execute("delete from " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . "
                    where stock_id in (" . implode(',', $stock_ids) . ")");
    }
    unset($stock_ids);
    
    
  }
  
  // OPTIONS_NAME_MANAGER_UPDATE_OPTIONS_VALUES_DELETE', array('products_id' => $all_update_products->fields['products_id'], 'options_id' => $all_options_values->fields['products_options_id'], 'options_values_id' => $all_options_values->fields['products_options_values_id']));
  function updateOptionsNameManagerUpdateOptionsValuesDelete(&$callingClass, $notifier, $paramsArray) {
  // ', array('products_id' => $all_update_products->fields['products_id'], 'options_id' => $all_options_values->fields['products_options_id'], 'options_values_id' => $all_options_values->fields['products_options_values_id']));
    global $db;
    
    $products_id = $paramsArray['products_id'];
    $options_id = $paramsArray['options_id'];
    $options_values_id = $paramsArray['options_values_id'];
    
    $check_all_options_values = $db->Execute("select products_attributes_id from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id='" . (int)$products_id . "' and options_id='" . (int)$options_id . "' and options_values_id='" . (int)$options_values_id . "'");
    unset($products_id);
    unset($options_id);
    unset($options_values_id);

    $stock_ids = zen_get_sba_ids_from_attribute($check_all_options_values->fields['products_attributes_id']);
    unset($check_all_options_values);
    if (!empty($stock_ids) && is_array($stock_ids)) {
      $db->Execute("delete from " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . "
                    where stock_id in (" . implode(',', $stock_ids) . ")");
    }

    unset($stock_ids);

  }
  
  // OPTIONS_VALUES_MANAGER_DELETE_VALUE', array('value_id' => $value_id));
  function updateOptionsValuesManagerDeleteValue(&$callingClass, $notifier, $paramsArray) {
  // ', array('value_id' => $value_id));
  
    global $db;
    
    $value_id = $paramsArray['value_id'];
    
    $remove_attributes_query = $db->Execute("select products_id, products_attributes_id, options_id, options_values_id from " . TABLE_PRODUCTS_ATTRIBUTES . " where options_values_id ='" . (int)$value_id . "'");
    unset($value_id);
    
    if ($remove_attributes_query->RecordCount() > 0) {
      // clean all tables of option value
      while (!$remove_attributes_query->EOF) {
        $stock_ids = zen_get_sba_ids_from_attribute($remove_attributes_query->fields['products_attributes_id']);
        
        if (!empty($stock_ids) && is_array($stock_ids)) {
          $db->Execute("delete from " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . "
                        where stock_id in (" . implode(',', $stock_ids) . ")");
        }

        unset($stock_ids);
        $remove_attributes_query->MoveNext();
      }
    }
    unset($remove_attributes_query);

  }
  
  // OPTIONS_VALUES_MANAGER_DELETE_VALUES_OF_OPTIONNAME', array('current_products_id' => $current_products_id, 'remove_ids' => $remove_downloads_ids, 'options_id'=>$options_id_from, 'options_values_id'=>$options_values_values_id_from));
  function updateOptionsValuesManagerDeleteValuesOfOptionname(&$callingClass, $notifier, $paramsArray) {
    // ', array('current_products_id' => $current_products_id, 'remove_ids' => $remove_downloads_ids, 'options_id'=>$options_id_from, 'options_values_id'=>$options_values_values_id_from));
    
    global $db;
    
    $remove_ids = $paramsArray['remove_ids'];
    
    $stock_ids = zen_get_sba_ids_from_attribute($remove_ids);
    unset($remove_ids);

    if (!empty($stock_ids) && is_array($stock_ids)) {
      $db->Execute("delete from " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . "
                    where stock_id in (" . implode(',', $stock_ids) . ")");
    }
    unset($stock_ids);
  }



    
//  notify('NOTIFY_PACKINGSLIP_IN_ATTRIB_LOOP', array('i'=>$i, 'j'=>$j, 'productsI'=>$order->products[$i], 'prod_img'=>$prod_img), $order->products[$i], $prod_img);

//    $this->notify('NOTIFY_EO_GET_PRODUCTS_STOCK', $products_id, $stock_quantity, $stock_handled);
  // function updateNotifyEOGetProductsStock

  function update(&$callingClass, $notifier, $paramsArray) {

    // Duplicate of updateNotifierAdminZenDeleteProductsAttributes
    if ($notifier == 'NOTIFIER_ADMIN_ZEN_DELETE_PRODUCTS_ATTRIBUTES '){
      //admin/includes/functions/general.php
      $delete_product_id = $paramsArray['delete_product_id'];
      $this->updateNotifierAdminZenDeleteProductsAttributes($callingClass, $notifier, $paramsArray, $delete_product_id);
    }
    
    // Duplicate of updateNotifierAdminZenRemoveOrder
    if ($notifier == 'NOTIFIER_ADMIN_ZEN_REMOVE_ORDER'){  
      //admin/includes/functions/general.php
      $restock = $paramsArray['restock'];
      $order_id = $paramsArray['order_id'];
      
      $this->updateNotifierAdminZenRemoveOrder($callingClass, $notifier, $paramsArray, $order_id, $restock);
    }
  
    // Duplicate of updateNotifierAdminZenRemoveProduct
    if ($notifier == 'NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT'){
      //admin/includes/functions/general.php
      $product_id = $paramsArray['product_id']; //=>$product_id
      $ptc = $paramsArray['ptc'];
      $this->updateNotifierAdminZenRemoveProduct($callingClass, $notifier, $paramsArray, $product_id, $ptc);
    }    
    
    if ($notifier == 'NOTIFY_ATTRIBUTE_CONTROLLER_DELETE_ATTRIBUTE'){
      $attribute_id = $paramsArray['attribute_id'];
      $this->updateNotifyAttributeControllerDeleteAttribute($callingClass, $notifier, $paramsArray, $attribute_id);
    }
    
    if ($notifier == 'NOTIFY_ATTRIBUTE_CONTROLLER_DELETE_ALL') {
      $this->updateNotifyAttributeControllerDeleteAll($callingClass, $notifier, $paramsArray);
    }
    
    if ($notifier == 'NOTIFY_ATTRIBUTE_CONTROLLER_DELETE_OPTION_NAME_VALUES') {
    //, array('pID' => $_POST['products_filter'], 'options_id' => $_POST['products_options_id_all']));
      $this->updateNotifyAttributeControllerDeleteOptionNameValues($callingClass, $notifier, $paramsArray);
    }

    if ($notifier == 'NOTIFY_MODULES_COPY_TO_CONFIRM_ATTRIBUTES') {
      $this->updateNotifyModulesCopyToConfirmAttributes($callingClass, $notifier, $paramsArray);
    }
    
    if ($notifier == 'OPTIONS_NAME_MANAGER_DELETE_OPTION') {
    //, array('option_id' => $option_id, 'options_values_id' => (int)$remove_option_values->fields['products_options_values_id']));
      $this->updateOptionsNameManagerDeleteOption($callingClass, $notifier, $paramsArray);
    }
    
    if ($notifier == 'OPTIONS_NAME_MANAGER_UPDATE_OPTIONS_VALUES_DELETE') {
    // , array('products_id' => $all_update_products->fields['products_id'], 'options_id' => $all_options_values->fields['products_options_id'], 'options_values_id' => $all_options_values->fields['products_options_values_id']));
      $this->updateOptionsNameManagerUpdateOptionsValuesDelete($callingClass, $notifier, $paramsArray);
    }
    
    if ($notifier == 'OPTIONS_VALUES_MANAGER_DELETE_VALUES_OF_OPTIONNAME') {
      //, array('current_products_id' => $current_products_id, 'remove_ids' => $remove_downloads_ids, 'options_id'=>$options_id_from, 'options_values_id'=>$options_values_values_id_from));
      $this->updateOptionsValuesManagerDeleteValuesOfOptionname($callingClass, $notifier, $paramsArray);
    }
    
    if ($notifier == 'ORDER_QUERY_ADMIN_COMPLETE') {
      $this->updateOrderQueryAdminComplete($callingClass, $notifier, $paramsArray);
    }
    
    if ($notifier == 'EDIT_ORDERS_REMOVE_PRODUCT') {
      $this->updateEditOrdersRemoveProduct($callingClass, $notifier, $paramsArray);
    }

    if ($notifier == 'EDIT_ORDERS_ADD_PRODUCT') {
//    $zco_notifier->notify ('EDIT_ORDERS_ADD_PRODUCT', array ( 'order_id' => (int)$order_id, 'orders_products_id' => $order_products_id, 'product' => $product ));
      $this->updateEditOrdersAddProduct($callingClass, $notifier, $paramsArray);
    }

  } //end update function - mc12345678
} // EOF Class
