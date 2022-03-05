<?php
/**
 * @package Stock by Attributes for Zen Cart German
 * @copyright Copyright 2003-2019 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 * @version $Id: products_with_attributes_stock_ajax.php 2019-08-11 08:37:14Z webchills $
 */
 
require('includes/application_top.php');
require(DIR_WS_CLASSES . 'currencies.php');
// Attempt to load the admin's specific language version of the file, but if unable to find it then default to german instead of displaying constants.
  if (file_exists(DIR_WS_LANGUAGES . $_SESSION['language'] . '/products_with_attributes_stock.php')) { 
    include(DIR_WS_LANGUAGES . $_SESSION['language'] . '/products_with_attributes_stock.php');
  } else {
    include(DIR_WS_LANGUAGES . 'german/products_with_attributes_stock.php');
  }

$stock = $products_with_attributes_stock_class;

    if( isset($_GET['save']) && $_GET['save'] == 1 ){
    if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
      $parameters = 'page=' . (int)$_GET['page'];
    } else {
      $parameters = '';
    }
        $x = $stock->saveAttrib();
    if( isset($_GET['pid']) && is_numeric($_GET['pid']) && $_GET['pid'] > 0 ){
      zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, 'updateReturnedPID=' . (int)$_GET['pid'] . '&amp;' . $parameters, 'NONSSL'));
    }
    else{
       zen_redirect(zen_href_link(FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK, $parameters, 'NONSSL'));
    }
    } else {
        $x = $stock->displayFilteredRows();
        print_r($x);
    }