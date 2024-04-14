<?php
/** 
 * Functions related to products
 * Note: Several product-related lookup functions are located in functions_lookups.php
 * Zen Cart German Specific (158 code in 157)
 * @copyright Copyright 2003-2024 Zen Cart Development Team
 * Zen Cart German Version - www.zen-cart-pro.at
 
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 * @version $Id: functions_products.php for SBA 2024-04-14 12:29:14Z webchills $
 */

/**
 * Query product details, returning a db QueryFactory response to iterate through
 *
 * @param int $product_id
 * @param int $language_id (optional)
 * @return queryFactoryResult
 */
function zen_get_product_details($product_id, $language_id = null)
{
    global $db, $zco_notifier;

    if ($language_id === null) {
        $language_id = $_SESSION['languages_id'] ?? 1;
    }

    $sql = "SELECT p.*, pd.*, pt.allow_add_to_cart, pt.type_handler
            FROM " . TABLE_PRODUCTS . " p
            LEFT JOIN " . TABLE_PRODUCT_TYPES . " pt ON (p.products_type = pt.type_id)
            LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (p.products_id = pd.products_id AND pd.language_id = " . (int)$language_id . ")
            WHERE p.products_id = " . (int)$product_id;
    $product = $db->Execute($sql, 1, true, 900);
    //Allow an observer to modify details
    $zco_notifier->notify('NOTIFY_GET_PRODUCT_DETAILS', $product_id, $product);
    return $product;
}

/**
 * @param int $product_id
 * @param null $product_info
 */
function zen_product_set_header_response($product_id, $product_info = null)
{
    global $zco_notifier, $breadcrumb, $robotsNoIndex;

    // make sure we got a dbResponse
    if ($product_info === null || !isset($product_info->EOF)) {
        $product_info = zen_get_product_details($product_id);
    }
    // make sure it's for the current product
    if (!isset($product_info->fields['products_id'], $product_info->fields['products_status']) || (int)$product_info->fields['products_id'] !== (int)$product_id) {
        $product_info = zen_get_product_details($product_id);
    }

    $response_code = 200;
    
    $product_not_found = $product_info->EOF;
    $should_throw_404 = $product_not_found;

    if ($should_throw_404 === true) {
        $response_code = 404;
    }

    global $product_status;
    $product_status = (int)($product_not_found === false && $product_info->fields['products_status'] !== '0') ? $product_info->fields['products_status'] : 0;

    if ($product_status === 0) {
        $response_code = 410;
    }

    if (defined('DISABLED_PRODUCTS_TRIGGER_HTTP200') && DISABLED_PRODUCTS_TRIGGER_HTTP200 === 'true') {
        $response_code = 200;
    }

    if ($product_status === -1) {
        $response_code = 410;
    }

    $use_custom_response_code = false;
    /**
     * optionally update the $product_status, $should_throw_404, $response_code vars via the observer
     */
    $zco_notifier->notify('NOTIFY_PRODUCT_INFO_PRODUCT_STATUS_CHECK', $product_info->fields, $product_status, $should_throw_404, $response_code, $use_custom_response_code);

    if ($use_custom_response_code) {
        // skip this function's processing and leave all header handling to the observer.
        // Note: the observer should do all the 404 stuff from below too
        return;
    }

    if ($should_throw_404) {
        // if specified product_id doesn't exist, ensure that metatags and breadcrumbs don't share bad data or inappropriate information
        unset($_GET['products_id']);
        $breadcrumb->removeLast();
        $robotsNoIndex = true;
        header('HTTP/1.1 404 Not Found');
        return;
    }

    if ($response_code === 410) {
        $robotsNoIndex = true;
        header('HTTP/1.1 410 Gone');
        return;
    }

}

/**
 * @param int $products_id
 * @param int $status
 */
function zen_set_disabled_upcoming_status($products_id, $status)
{
    global $db;

    $sql = "UPDATE " . TABLE_PRODUCTS . "
            SET products_status = " . (int)$status . ", products_date_available = NULL
            WHERE products_id = " . (int)$products_id;

    $db->Execute($sql, 1);
}

/**
 * Enable all disabled products whose date_available is prior to the specified date
 * @param int $datetime optional timestamp
 */
function zen_enable_disabled_upcoming($datetime = null)
{
    global $db;

    if (empty($datetime)) {
        $datetime = time();
    }

    $zc_disabled_upcoming_date = date('Ymd', $datetime);

    $sql = "SELECT products_id
            FROM " . TABLE_PRODUCTS . "
            WHERE products_status = 0
            AND products_date_available <= " . $zc_disabled_upcoming_date . "
            AND products_date_available != '0001-01-01'
            AND products_date_available IS NOT NULL
            ";

    $results = $db->Execute($sql);

    foreach ($results as $result) {
        zen_set_disabled_upcoming_status($result['products_id'], 1);
    }
}

/**
 * build date range for "upcoming products" query
 */
function zen_get_upcoming_date_range()
{
    // 120 days; 24 hours; 60 mins; 60secs
    $date_range = time();
    $zc_new_date = date('Ymd', $date_range);
// need to check speed on this for larger sites
//    $new_range = ' and date_format(p.products_date_available, \'%Y%m%d\') >' . $zc_new_date;
    $new_range = ' and p.products_date_available >' . $zc_new_date . '235959';

    return $new_range;
}

/**
 * build date range for "new products" query
 * @param int $time_limit
 * @return string
 */
function zen_get_new_date_range($time_limit = false)
{
    if ($time_limit == false) {
        $time_limit = (int)SHOW_NEW_PRODUCTS_LIMIT;
    }
    // 120 days; 24 hours; 60 mins; 60secs
    $date_range = time() - ($time_limit * 24 * 60 * 60);
    $upcoming_mask_range = time();
    $upcoming_mask = date('Ymd', $upcoming_mask_range);

    $zc_new_date = date('Ymd', $date_range);
    switch (true) {
        case (SHOW_NEW_PRODUCTS_LIMIT === '0'):
            $new_range = '';
            break;
        case (SHOW_NEW_PRODUCTS_LIMIT === '1'):
            $zc_new_date = date('Ym', time()) . '01';
            $new_range = ' AND p.products_date_added >= ' . $zc_new_date;
            break;
        default:
            $new_range = ' AND p.products_date_added >= ' . $zc_new_date;
            break;
    }

    if (SHOW_NEW_PRODUCTS_UPCOMING_MASKED !== '0') {
        // do not include upcoming in new
        $new_range .= " AND (p.products_date_available <= " . $upcoming_mask . " OR p.products_date_available IS NULL)";
    }
    return $new_range;
}

/**
 * build New Products query clause
 * @param int $time_limit
 * @return string
 */
function zen_get_products_new_timelimit($time_limit = false)
{
    if ($time_limit == false) {
        $time_limit = SHOW_NEW_PRODUCTS_LIMIT;
    }
    $time_limit = (int)$time_limit;
    switch ($time_limit) {
        case 1:
            $display_limit = " AND date_format(p.products_date_added, '%Y%m') >= date_format(now(), '%Y%m')";
            break;
        case 7:
            $display_limit = ' AND TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 7';
            break;
        case 14:
            $display_limit = ' AND TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 14';
            break;
        case 30:
            $display_limit = ' AND TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 30';
            break;
        case 60:
            $display_limit = ' AND TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 60';
            break;
        case 90:
            $display_limit = ' AND TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 90';
            break;
        case 120:
            $display_limit = ' AND TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 120';
            break;
        default:
            $display_limit = '';
            break;
    }
    return $display_limit;
}


/**
 * Return a product's category (master_categories_id)
 * @param int $product_id
 * @return int|string
 */
function zen_get_products_category_id($product_id)
{
    $result = zen_get_product_details($product_id);
    if ($result->EOF) {
        return '';
    }
    return $result->fields['master_categories_id'];
}

/**
 * Reset master_categories_id for all products linked to the specified $category_id
 * @param int $category_id
 */
function zen_reset_products_category_as_master($category_id)
{
    global $db;
    $sql = "SELECT p.products_id, p.master_categories_id, ptoc.categories_id
            FROM " . TABLE_PRODUCTS . " p
            LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " ptoc USING (products_id)
            WHERE ptoc.categories_id = " . (int)$category_id;

    $results = $db->Execute($sql);
    foreach ($results as $item) {
        zen_set_product_master_categories_id($item['products_id'], $category_id);
    }
}

function zen_reset_all_products_master_categories_id()
{
    global $db;
    $sql = "SELECT products_id FROM " . TABLE_PRODUCTS;
    $products = $db->Execute($sql);
    foreach ($products as $product) {
        // Note: "USE INDEX ()" is intentional, to retrieve results in original insert order
        $sql = "SELECT products_id, categories_id
                FROM " . TABLE_PRODUCTS_TO_CATEGORIES . "
                USE INDEX ()
                WHERE products_id=" . (int)$product['products_id'] . "
                LIMIT 1";
        $check_category = $db->Execute($sql);

        zen_set_product_master_categories_id($product['products_id'], $check_category->fields['categories_id']);
    }
}

/**
 * Update master_categories_id for specified product
 * Also updates cache of lowest sale price based on the category change
 * @param int $product_id
 * @param int $category_id
 */
function zen_set_product_master_categories_id($product_id, $category_id)
{
    global $db;
    $sql = "UPDATE " . TABLE_PRODUCTS . "
            SET master_categories_id = " . (int)$category_id . "
            WHERE products_id = " . (int)$product_id . " LIMIT 1";
    $db->Execute($sql);

    // reset products_price_sorter for searches etc.
    zen_update_products_price_sorter($product_id);
}

/**
 * @param int $product_id
 * @param array $exclude
 * @return array of categories_id
 */
function zen_get_linked_categories_for_product($product_id, $exclude = [])
{
    global $db;
    $exclude = array_filter($exclude, function ($record) {
        return is_numeric($record) ? (int)$record : null;
    });
    $sql = "SELECT categories_id
            FROM " . TABLE_PRODUCTS_TO_CATEGORIES . "
            WHERE products_id = " . (int)$product_id;
    if (!empty($exclude) && is_array($exclude)) {
        $sql .= " AND categories_id NOT IN (" . implode(',', $exclude) . ")";
    }
    $results = $db->Execute($sql);
    $categories = [];
    foreach ($results as $result) {
        $categories[] = $result['categories_id'];
    }
    return $categories;
}

/**
 * @param int $category_id
 * @param bool $first_only if true, return only the first result (string)
 * @return array|integer Array of products_id, or if $first-only true, a single products_id/0 if record not found
 */
function zen_get_linked_products_for_category($category_id, $first_only = false)
{
    global $db;
    $sql = "SELECT products_id
            FROM " . TABLE_PRODUCTS_TO_CATEGORIES . "
            WHERE categories_id = " . (int)$category_id . "
            ORDER BY products_id";
    if ($first_only) {
        $sql .= ' LIMIT 1';
    }
    $results = $db->Execute($sql);

    if ($first_only) {
        return $results->EOF ? 0 : (int)$results->fields['products_id'];
    }

    $products = [];
    foreach ($results as $result) {
        $products[] = (int)$result['products_id'];
    }
    return $products;
}

/**
 * @param int $product_id
 * @param int $category_id
 */
function zen_link_product_to_category($product_id, $category_id)
{
    global $db;
    $sql = "INSERT IGNORE INTO " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id)
            VALUES (" . (int)$product_id . ", " . (int)$category_id . ")";
    $db->Execute($sql);
}

/**
 * @param int $product_id
 * @param int $category_id
 */
function zen_unlink_product_from_category($product_id, $category_id)
{
    global $db;
    $sql = "DELETE FROM " . TABLE_PRODUCTS_TO_CATEGORIES . "
            WHERE products_id = " . (int)$product_id . "
            AND categories_id = " . (int)$category_id . "
            LIMIT 1";
    $db->Execute($sql);
}

/**
 * Reset by removing all links-to-other-categories for this product, other than its master_categories_id
 * @param int $product_id
 * @param int $master_category_id
 */
function zen_unlink_product_from_all_linked_categories($product_id, $master_category_id = null)
{
    global $db;
    if ($master_category_id === null) {
        $master_category_id = zen_get_products_category_id($product_id);
    }
    if (empty($master_category_id)) {
        return;
    }

    $sql = "DELETE FROM " . TABLE_PRODUCTS_TO_CATEGORIES . "
            WHERE products_id = " . (int)$product_id . "
            AND categories_id != " . (int)$master_category_id;
    $db->Execute($sql);
}

/**
 * Return a product ID with attributes hash
 * @param string|int $prid
 * @param array|string $params
 * @return string
 */
function zen_get_uprid($prid, $params)
{
    // -----
    // The string version of the supplied $prid is returned if:
    //
    // 1. The supplied $params is not an array or is an empty array, implying
    //    that no attributes are associated with the product-selection.
    // 2. The supplied $prid is already in uprid-format (ppp:xxxx), where
    //    ppp is the product's id and xxx is a hash of the associated attributes.
    //
    $prid = (string)$prid;
    if (!is_array($params) || $params === [] || strpos($prid, ':') !== false) {
        return $prid;
    }

    // -----
    // Otherwise, the $params array is expected to contain option/value
    // pairs which are concatenated to the supplied $prid, hashed and then
    // appended to the supplied $prid.
    //
    $uprid = $prid;
    foreach ($params as $option => $value) {
        if (is_array($value)) {
            foreach ($value as $opt => $val) {
                $uprid .= '{' . $option . '}' . trim((string)$opt);
            }
        } else {
            $uprid .= '{' . $option . '}' . trim((string)$value);
        }
    }

    $md_uprid = md5($uprid);
    return $prid . ':' . $md_uprid;
}

/**
 * Return a product ID from a product ID with attributes
 * Alternate: simply (int) the product id
 * @param string|int $uprid ie: '11:abcdef12345'
 * @return int
 */
function zen_get_prid(string|int $uprid): int
{
    return (int)$uprid;
//    $pieces = explode(':', $uprid);
//    return (int)$pieces[0];
}

/**
 * @param int|string $product_id (while a hashed string is accepted, only the (int) portion is used)
 * Check if product_id exists in database
 */
function zen_products_id_valid($product_id)
{
    $product = zen_get_product_details($product_id);
    return !$product->EOF;
}

/**
 * Return a product's name.
 *
 * @param int $product_id The product id of the product who's name we want
 * @param int $language_id The language id to use. Defaults to current language
 */
function zen_get_products_name($product_id, $language_id = null)
{
    $product = zen_get_product_details($product_id, $language_id);
    return ($product->EOF) ? '' : $product->fields['products_name'];
}

/**
 * lookup attributes model
 * @param int $product_id
 */
function zen_get_products_model($product_id)
{
    $product = zen_get_product_details($product_id);
    return ($product->EOF) ? '' : $product->fields['products_model'];
}

/**
 * Get the status of a product
 * @param int $product_id
 */
function zen_get_products_status($product_id)
{
   $product = zen_get_product_details($product_id);
   return ($product->EOF) ? '' : $product->fields['products_status'];
}

/**
 * check if linked
 * @TODO - check to see whether true/false string responses can be changed to boolean
 *
 * @param int $product_id
 */
function zen_get_product_is_linked($product_id, $show_count = 'false')
{
    global $db;

    $sql = "SELECT * FROM " . TABLE_PRODUCTS_TO_CATEGORIES . (!empty($product_id) ? " where products_id=" . (int)$product_id : "");
    $check_linked = $db->Execute($sql);
    if ($check_linked->RecordCount() > 1) {
        if ($show_count === 'true') {
            return $check_linked->RecordCount();
        } else {
            return 'true';
        }
    } else {
        return 'false';
    }
}

/**
 * Return a product's stock-on-hand
 * modified for SBA
 * @param int $products_id The product id of the product whose stock we want
 */
  function zen_get_products_stock($products_id, $attributes = '') {
    global $db;
    $products_id = zen_get_prid($products_id);

    // get product level stock quantity
	  $stock_query = "select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . (int)$products_id . "'"; 

    // check if there attributes for this product
    if(is_array($attributes) and sizeof($attributes) > 0){

      // check if any attribute stock values have been set for the product
			// (only of there is will we continue, otherwise we'll use product level data)
			$attribute_stock = $db->Execute("select stock_id from " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " where products_id = '" . (int)$products_id . "'");
      if ($attribute_stock->RecordCount() > 0) {

  		  // prepare to search for details for the particular attribute combination passed as a parameter
	  	  if(sizeof($attributes) > 1){
          $first_search = 'where options_values_id in ("'.implode('","',$attributes).'")';
		    } else {
			    foreach($attributes as $attribute){
				    $first_search = 'where options_values_id="'.$attribute.'"';
          }
        }

        // obtain the attribute ids
  		  $query = 'select products_attributes_id from '.TABLE_PRODUCTS_ATTRIBUTES.' '.$first_search.' and products_id="'.$products_id.'" order by products_attributes_id';
	  	  $attributes_new = $db->Execute($query);

  		  while(!$attributes_new->EOF){
	  		  $stock_attributes[] = $attributes_new->fields['products_attributes_id'];	
		  	  $attributes_new->MoveNext();
		    }
  		  if(sizeof($stock_attributes) > 1){
	  		  $stock_attributes = implode(',',$stock_attributes);
		    } else {
			    $stock_attributes = $stock_attributes[0];
		    }
		
		    // create the query to find attribute stock 	
		    $stock_query = 'select quantity as products_quantity from '.TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK.' where products_id = "'.(int)$products_id.'" and stock_attributes="'.$stock_attributes.'"';

			}

    }

    // get the stock value for the product or attribute combination
		$stock_values = $db->Execute($stock_query); 		
    return $stock_values->fields['products_quantity'];

  }


/**
 * Check if the required stock is available.
 * If insufficent stock is available return an out of stock message
 * modified for SBA
 * @param int $products_id The product id of the product whose stock is to be checked
 * @param int $products_quantity Quantity to compare against
 */
function zen_check_stock($products_id, $products_quantity, $attributes = '') {
	
	global $zco_notifier;

    $stock_left = zen_get_products_stock($products_id, $attributes) - $products_quantity;
    
    // Give an observer the opportunity to change the out-of-stock message.
    $the_message = '';

    if ($stock_left < 0) {
        $out_of_stock_message = STOCK_MARK_PRODUCT_OUT_OF_STOCK;
        $zco_notifier->notify(
            'ZEN_CHECK_STOCK_MESSAGE',
            [
                $products_id,
                $products_quantity
            ],
            $out_of_stock_message
        );
        $the_message = '<span class="markProductOutOfStock">' . $out_of_stock_message . '</span>';
    }

    return $the_message;
  }

/**
 * Return a product's manufacturer's name, from ID
 * @param int $product_id
 * @return string
 */
function zen_get_products_manufacturers_name($product_id)
{
    global $db;

    $sql = "SELECT m.manufacturers_name
            FROM " . TABLE_PRODUCTS . " p
            LEFT JOIN " . TABLE_MANUFACTURERS . " m USING (manufacturers_id)
            WHERE p.products_id = " . (int)$product_id;

    $product = $db->Execute($sql, 1);

    return ($product->EOF) ? '' : $product->fields['manufacturers_name'];
}

/**
 * Return a product's manufacturer's image, from Prod ID
 * @param int $product_id
 * @return string
 */
function zen_get_products_manufacturers_image($product_id)
{
    global $db;

    $product_query = "SELECT m.manufacturers_image
                      FROM " . TABLE_PRODUCTS . " p
                      INNER JOIN " . TABLE_MANUFACTURERS . " m USING (manufacturers_id)
                      WHERE p.products_id = " . (int)$product_id;

    $product = $db->Execute($product_query, 1);
    return ($product->EOF) ? '' : $product->fields['manufacturers_image'];
}

/**
 * Return a product's manufacturer's id
 * @param int $product_id
 * @return int
 */
function zen_get_products_manufacturers_id($product_id)
{
    $product = zen_get_product_details($product_id);
    return ($product->EOF) ? 0 : (int)$product->fields['manufacturers_id'];
}

/**
 * @param int $product_id
 * @param int $language_id
 * @return string
 */
function zen_get_products_url($product_id, $language_id)
{
    $product = zen_get_product_details($product_id, $language_id);
    return ($product->EOF) ? '' : (string)$product->fields['products_url'];
}

/**
 * Return product description, based on specified language (or current lang if not specified)
 * @param int $product_id
 * @param int $language_id
 * @return string
 */
function zen_get_products_description($product_id, $language_id = null)
{
    global $zco_notifier;

    $product = zen_get_product_details($product_id, $language_id);

    //Allow an observer to modify the description
    $zco_notifier->notify('NOTIFY_GET_PRODUCTS_DESCRIPTION', $product_id, $product);
    return ($product->EOF) ? '' : $product->fields['products_description'];
}

/**
 * look up the product type from product_id and return an info page name (for template/page handling)
 * @param int $product_id
 * @return string
 */
function zen_get_info_page($product_id)
{
    $result = zen_get_product_details($product_id);
    return ($result->EOF) ? 'product_info' : ($result->fields['type_handler'] . '_info');
}

/**
 * get products_type for specified $product_id
 * @param int $product_id
 * @return int|string
 */
function zen_get_products_type($product_id)
{
    $result = zen_get_product_details($product_id);

    // -----
    // NOTE: Empty string return is used by the admin/product.php to identify a product
    // that doesn't exist in the database!
    //
    return ($result->EOF) ? '' : (int)$result->fields['products_type'];
}

/**
 * look up a products image and send back the image's IMG tag
 * @param int $product_id
 * @param int $width
 * @param int $height
 * @return string
 */
function zen_get_products_image($product_id, $width = SMALL_IMAGE_WIDTH, $height = SMALL_IMAGE_HEIGHT)
{
    $result = zen_get_product_details($product_id);
    if ($result->EOF) {
        return '';
    }

    if (IS_ADMIN_FLAG === true) {
        return $result->fields['products_image'];
    }
    return zen_image(DIR_WS_IMAGES . $result->fields['products_image'], zen_get_products_name($product_id), $width, $height);
}

/**
 * look up whether a product is virtual
 * @param int $product_id
 * @return bool
 */
function zen_get_products_virtual($product_id)
{
    $result = zen_get_product_details($product_id);
    return (!$result->EOF && $result->fields['products_virtual'] === '1');
}

/**
 * Look up whether the given product ID is allowed to be added to cart, according to product-type switches set in Admin
 * @param int|string $product_id  (while a hashed string is accepted, only the (int) portion is used)
 * @return string Y|N
 */
function zen_get_products_allow_add_to_cart($product_id)
{
    global $zco_notifier;

    $product_query_results = zen_get_product_details($product_id);

    // If product found, and product_type's allow_add_to_cart is not 'N', allow
    $allow_add_to_cart = !$product_query_results->EOF && $product_query_results->fields['allow_add_to_cart'] !== 'N';


    // If product is encoded as GV but GV feature is turned off, disallow add-to-cart
    if ($allow_add_to_cart === true && strpos($product_query_results->fields['products_model'], 'GIFT') === 0) {
        if (!defined('MODULE_ORDER_TOTAL_GV_STATUS') || MODULE_ORDER_TOTAL_GV_STATUS !== 'true') {
            $allow_add_to_cart = false;
        }
    }

    $zco_notifier->notify('NOTIFY_GET_PRODUCT_ALLOW_ADD_TO_CART', $product_id, $allow_add_to_cart, $product_query_results);

    // test for boolean and for 'Y', since observer might try to return 'Y'
    return in_array($allow_add_to_cart, [true, 'Y'], true) ? 'Y' : 'N';
}

/**
 * build configuration_key based on product type and return its value
 * example: To get the settings for metatags_products_name_status for a product use:
 * zen_get_show_product_switch($_GET['pID'], 'metatags_products_name_status')
 * the product is looked up for the products_type which then builds the configuration_key example:
 * SHOW_PRODUCT_INFO_METATAGS_PRODUCTS_NAME_STATUS
 * the value of the configuration_key is then returned
 * NOTE: keys are looked up first in the product_type_layout table and if not found looked up in the configuration table.
 */
function zen_get_show_product_switch($lookup, $field, $prefix = 'SHOW_', $suffix = '_INFO', $field_prefix = '_', $field_suffix = '')
{
    global $db;
    $keyName = zen_get_show_product_switch_name($lookup, $field, $prefix, $suffix, $field_prefix, $field_suffix);
    $sql = "SELECT configuration_key, configuration_value FROM " . TABLE_PRODUCT_TYPE_LAYOUT . " WHERE configuration_key='" . zen_db_input($keyName) . "'";
    $zv_key_value = $db->Execute($sql, 1);

    if (!$zv_key_value->EOF) {
        return $zv_key_value->fields['configuration_value'];
    }
    $sql = "SELECT configuration_key, configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='" . zen_db_input($keyName) . "'";
    $zv_key_value = $db->Execute($sql, 1);
    if (!$zv_key_value->EOF) {
        return $zv_key_value->fields['configuration_value'];
    }
    return '';
}

/**
 * return switch name
 */
function zen_get_show_product_switch_name($lookup, $field, $prefix = 'SHOW_', $suffix = '_INFO', $field_prefix = '_', $field_suffix = '')
{
    $product = zen_get_product_details((int)$lookup);
    $type_handler = ($product->EOF) ? 'product' : $product->fields['type_handler'];

    return strtoupper($prefix . $type_handler . $suffix . $field_prefix . $field . $field_suffix);
}

/**
 * Look up whether a product is always free shipping
 * @param int $product_id
 */
function zen_get_product_is_always_free_shipping($product_id): bool
{
    $look_up = zen_get_product_details($product_id);
    return (!$look_up->EOF && $look_up->fields['product_is_always_free_shipping'] === '1');
}

/**
 * Return any field from products or products_description table.
 *
 * @param int $product_id
 * @param string $what_field
 * @param int $language ID
 */
function zen_products_lookup($product_id, $what_field = 'products_name', $language = null)
{
    $product_lookup = zen_get_product_details($product_id, $language);
    if ($product_lookup->EOF || !array_key_exists($what_field, $product_lookup->fields)) {
        return '';
    }
    return $product_lookup->fields[$what_field];
}

/**
 * Lookup and return product's master_categories_id
 * @param int $product_id
 * @return mixed|int
 */
function zen_get_parent_category_id($product_id)
{
    $result = zen_get_product_details($product_id);
    return ($result->EOF) ? '' : $result->fields['master_categories_id'];
}

/**
 * @TODO - check to see whether true/false string responses can be changed to boolean
 * check if products has quantity-discounts defined
 * @param int $product_id
 * @return string
 */
function zen_has_product_discounts($product_id)
{
    global $db;

    $check_discount_query = "SELECT products_id FROM " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . " WHERE products_id=" . (int)$product_id;
    $check_discount = $db->Execute($check_discount_query, 1);

    // @TODO - check calling references in application code to see whether true/false string responses can be changed to boolean
    return (!$check_discount->EOF) ? 'true' : 'false';
}

/**
 * Set the status of a product.
 * Used for toggling
 *
 * @param int $product_id
 * @param int $status
 */
function zen_set_product_status($product_id, $status)
{
    global $db;
    $db->Execute(
        "UPDATE " . TABLE_PRODUCTS . "
            SET products_status = " . (int)$status . ",
                products_last_modified = now()
          WHERE products_id = " . (int)$product_id . "
          LIMIT 1"
    );
}

/**
 * @TODO - can the ptc string 'true' be changed to boolean?
 * @param int $product_id
 * @param string $ptc
 */
function zen_remove_product($product_id, $ptc = 'true')
{
    global $db, $zco_notifier;
    $zco_notifier->notify('NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT', [], $product_id, $ptc);

    $product_id = (int)$product_id;
    $product_image = $db->Execute(
        "SELECT products_image
           FROM " . TABLE_PRODUCTS . "
          WHERE products_id = $product_id
            AND products_image IS NOT NULL
            AND products_image != ''
            AND products_image NOT LIKE '%" . zen_db_input(PRODUCTS_IMAGE_NO_IMAGE) . "'
          LIMIT 1"
    );

    if (!$product_image->EOF) {
        $duplicate_image = $db->Execute(
            "SELECT COUNT(*) as total
               FROM " . TABLE_PRODUCTS . "
              WHERE products_image = '" . zen_db_input($product_image->fields['products_image']) . "'"
        );

        if ($duplicate_image->fields['total'] < 2) {
            $products_image = $product_image->fields['products_image'];
            $image_parts = pathinfo($products_image);
            $products_image_extension = '.' . $image_parts['extension'];
            $products_image_base = $image_parts['dirname'] . DIRECTORY_SEPARATOR  . $image_parts['filename'];

            $filename_medium = 'medium/' . $products_image_base . IMAGE_SUFFIX_MEDIUM . $products_image_extension;
            $filename_large = 'large/' . $products_image_base . IMAGE_SUFFIX_LARGE . $products_image_extension;

            if (file_exists(DIR_FS_CATALOG_IMAGES . $products_image)) {
                @unlink(DIR_FS_CATALOG_IMAGES . $products_image);
            }
            if (file_exists(DIR_FS_CATALOG_IMAGES . $filename_medium)) {
                @unlink(DIR_FS_CATALOG_IMAGES . $filename_medium);
            }
            if (file_exists(DIR_FS_CATALOG_IMAGES . $filename_large)) {
                @unlink(DIR_FS_CATALOG_IMAGES . $filename_large);
            }
        }
    }

    $db->Execute("DELETE FROM " . TABLE_SPECIALS . " WHERE products_id = $product_id");

    $db->Execute("DELETE FROM " . TABLE_PRODUCTS . " WHERE products_id = $product_id LIMIT 1");

//    if ($ptc == 'true') {
    $db->Execute("DELETE FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE products_id = $product_id");
//    }

    $db->Execute("DELETE FROM " . TABLE_PRODUCTS_DESCRIPTION . " WHERE products_id = $product_id");

    $db->Execute("DELETE FROM " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . " WHERE products_id = $product_id");

    zen_products_attributes_download_delete($product_id);

    $db->Execute("DELETE FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_id = $product_id");

    $db->Execute("DELETE FROM " . TABLE_CUSTOMERS_BASKET . " WHERE products_id LIKE '$product_id:%'");

    $db->Execute("DELETE FROM " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " WHERE products_id LIKE '$product_id:%'");

    $product_reviews = $db->Execute(
        "SELECT reviews_id
           FROM " . TABLE_REVIEWS . "
          WHERE products_id = $product_id"
    );
    foreach ($product_reviews as $row) {
        $db->Execute("DELETE FROM " . TABLE_REVIEWS_DESCRIPTION . " WHERE reviews_id = " . $row['reviews_id']);
    }

    $db->Execute("DELETE FROM " . TABLE_REVIEWS . " WHERE products_id = $product_id");

    $db->Execute("DELETE FROM " . TABLE_FEATURED . " WHERE products_id = $product_id");

    $db->Execute("DELETE FROM " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . " WHERE products_id = $product_id");

    $db->Execute("DELETE FROM " . TABLE_COUPON_RESTRICT . " WHERE product_id = $product_id");

    $db->Execute("DELETE FROM " . TABLE_PRODUCTS_NOTIFICATIONS . " WHERE products_id = $product_id");

    $db->Execute("DELETE FROM " . TABLE_COUNT_PRODUCT_VIEWS . " WHERE product_id = $product_id");

    zen_record_admin_activity("Deleted product $product_id from database via admin console.", 'warning');
}

/**
 * Remove downloads (if any) from specified product
 *
 * @param int $product_id
 */
function zen_products_attributes_download_delete($product_id)
{
    global $db, $zco_notifier;
    $zco_notifier->notify('NOTIFIER_ADMIN_ZEN_PRODUCTS_ATTRIBUTES_DOWNLOAD_DELETE', [], $product_id);

    $results = $db->Execute("SELECT products_attributes_id FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_id= " . (int)$product_id);
    foreach ($results as $row) {
        $db->Execute("DELETE FROM " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " WHERE products_attributes_id= " . (int)$row['products_attributes_id']);
    }
}

/**
 * copy quantity-discounts from one product to another
 * @param int $copy_from
 * @param int $copy_to
 * @return false on failure
 */
function zen_copy_discounts_to_product($copy_from, $copy_to)
{
    global $db;

    $copy_from = (int)$copy_from;
    $check_discount_type_query = "SELECT products_discount_type, products_discount_type_from, products_mixed_discount_quantity FROM " . TABLE_PRODUCTS . " WHERE products_id = $copy_from";
    $check_discount_type = $db->Execute($check_discount_type_query, 1);
    if ($check_discount_type->EOF) {
        return false;
    }

    $copy_to = (int)$copy_to;
    $db->Execute(
        "UPDATE " . TABLE_PRODUCTS . "
            SET products_discount_type = " . $check_discount_type->fields['products_discount_type'] . ",
                products_discount_type_from = " . $check_discount_type->fields['products_discount_type_from'] . ",
                products_mixed_discount_quantity = " . $check_discount_type->fields['products_mixed_discount_quantity'] . "
          WHERE products_id = $copy_to",
         1
    );

    $check_discount_query = "SELECT * FROM " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . " WHERE products_id = $copy_from ORDER BY discount_id";
    $results = $db->Execute($check_discount_query);
    $cnt_discount = 1;
    foreach ($results as $result) {
        $db->Execute(
            "INSERT INTO " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . "
                (discount_id, products_id, discount_qty, discount_price, discount_price_w)
             VALUES
                ($cnt_discount, $copy_to, " . $result['discount_qty'] . ", " . $result['discount_price'] . ", '" . $result['discount_price_w'] . "')"
        );
        $cnt_discount++;
    }
}

function zen_products_sort_order($includeOrderBy = true): string
{
    switch (PRODUCT_INFO_PREVIOUS_NEXT_SORT) {
        case (0):
            $productSort = 'LPAD(p.products_id,11,"0")';
            $productSort = 'p.products_id';
            break;
        case (1):
            $productSort = 'pd.products_name';
            break;
        case (2):
            $productSort = 'p.products_model';
            break;
        case (3):
            $productSort = 'p.products_price_sorter, pd.products_name';
            break;
        case (4):
            $productSort = 'p.products_price_sorter, p.products_model';
            break;
        case (5):
            $productSort = 'pd.products_name, p.products_model';
            break;
        case (6):
            $productSort = 'LPAD(p.products_sort_order,11,"0"), pd.products_name';
            $productSort = 'products_sort_order, pd.products_name';
            break;
        default:
            $productSort = 'pd.products_name';
            break;
    }
    if ($includeOrderBy) {
        return ' ORDER BY ' . $productSort;
    }
    return $productSort;
}
