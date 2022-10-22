<?php
/**
 * @package Stock by Attributes for Zen Cart German
 * @copyright Copyright 2003-2022 Zen Cart Development Team
 * Zen Cart German Version - www.zen-cart-pro.at
 * @copyright Portions Copyright 2003 osCommerce
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 * @version $Id: reg_stock_by_attributes.php 2022-10-22 12:10:14Z webchills $
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

if (function_exists('zen_register_admin_page') && !zen_page_key_exists('stock_by_attributes')) {
    // Add SBA to webshop menu
    zen_register_admin_page('stock_by_attributes', 'BOX_CATALOG_PRODUCTS_WITH_ATTRIBUTES_STOCK','FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK', '', 'catalog', 'Y', 200);
   }

if (function_exists('zen_register_admin_page') && !zen_page_key_exists('stock_by_attributes_ajax')) {
    // Add SBA Ajax hidden to webshop menu for limited admin profiles
    zen_register_admin_page('stock_by_attributes_ajax', 'BOX_CATALOG_PRODUCTS_WITH_ATTRIBUTES_STOCK_AJAX','FILENAME_PRODUCTS_WITH_ATTRIBUTES_STOCK_AJAX', '', 'catalog', 'N', 250);
}