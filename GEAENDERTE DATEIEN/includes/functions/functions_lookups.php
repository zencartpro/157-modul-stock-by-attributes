<?php
/**
 * Zen Cart German Specific
 * functions_lookups.php
 * Lookup Functions for various core activities related to countries, prices, products, product types, etc
 * 
 * @copyright Copyright 2003-2022 Zen Cart Development Team
 * Zen Cart German Version - www.zen-cart-pro.at
 * @copyright Portions Copyright 2003 osCommerce
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 * @version $Id: functions_lookups.php for SBA 2022-10-22 11:49:42Z webchills $
 */

/**
 * Returns an array with countries
 *
 * @param int If set limits to a single country
 * @param boolean If true adds the iso codes to the array
 */
  function zen_get_countries($countries_id = '', $with_iso_codes = false, $activeOnly = TRUE) {
    global $db;
    $countries_array = array();
    if (zen_not_null($countries_id)) {
      $countries_array['countries_name'] = '';

      $countries = "SELECT con.countries_name, co.countries_iso_code_2, co.countries_iso_code_3, co.status
                   FROM " . TABLE_COUNTRIES . " co
                    LEFT JOIN " . TABLE_COUNTRIES_NAME . " con ON con.countries_id = co.countries_id
                    AND con.language_id = " . (int)$_SESSION['languages_id'] ."
                    WHERE co.countries_id = " . (int)$countries_id;

      if ($activeOnly) $countries .= " AND co.status != 0 ";
      $countries .= " ORDER BY con.countries_name";

      $countries_values = $db->Execute($countries);

      if ($with_iso_codes == true) {
        $countries_array['countries_iso_code_2'] = '';
        $countries_array['countries_iso_code_3'] = '';
        if (!$countries_values->EOF) {
          $countries_array = array('countries_name' => $countries_values->fields['countries_name'],
                                   'countries_iso_code_2' => $countries_values->fields['countries_iso_code_2'],
                                   'countries_iso_code_3' => $countries_values->fields['countries_iso_code_3']);
        }
      } else {
        if (!$countries_values->EOF) $countries_array = array('countries_name' => $countries_values->fields['countries_name']);
      }
    } else {

      $countries = "SELECT co.countries_id, co.status, con.countries_name
                    FROM " . TABLE_COUNTRIES . " co
                    LEFT JOIN " . TABLE_COUNTRIES_NAME . " con ON con.countries_id = co.countries_id
                    AND con.language_id = " . (int)$_SESSION['languages_id'];
      if ($activeOnly) {
        $countries .= " WHERE co.status != 0 ";
      }
      $countries .= " ORDER BY con.countries_name";

      $countries_values = $db->Execute($countries);
      while (!$countries_values->EOF) {
        $countries_array[] = array('countries_id' => $countries_values->fields['countries_id'],
                                   'countries_name' => $countries_values->fields['countries_name']);
        $countries_values->MoveNext();
      }
    }

    return $countries_array;
  }

/*
 *  Alias function to zen_get_countries()
 */
  function zen_get_country_name($country_id, $activeOnly = TRUE) {
    $country_array = zen_get_countries($country_id, FALSE, $activeOnly);
    return $country_array['countries_name'];
  }

/**
 * Alias function to zen_get_countries, which also returns the countries iso codes
 *
 * @param int If set limits to a single country
*/
  function zen_get_countries_with_iso_codes($countries_id, $activeOnly = TRUE) {
    return zen_get_countries($countries_id, true, $activeOnly);
  }

/*
 * Return the zone (State/Province) name
 * TABLES: zones
 */
  function zen_get_zone_name($country_id, $zone_id, $default_zone) {
    global $db;
    $zone_query = "select zone_name
                   from " . TABLE_ZONES . "
                   where zone_country_id = '" . (int)$country_id . "'
                   and zone_id = '" . (int)$zone_id . "'";

    $zone = $db->Execute($zone_query);

    if ($zone->RecordCount()) {
      return $zone->fields['zone_name'];
    } else {
      return $default_zone;
    }
  }

/*
 * Returns the zone (State/Province) code
 * TABLES: zones
 */
  function zen_get_zone_code($country_id, $zone_id, $default_zone) {
    global $db;
    $zone_query = "select zone_code
                   from " . TABLE_ZONES . "
                   where zone_country_id = '" . (int)$country_id . "'
                   and zone_id = '" . (int)$zone_id . "'";

    $zone = $db->Execute($zone_query);

    if ($zone->RecordCount() > 0) {
      return $zone->fields['zone_code'];
    } else {
      return $default_zone;
    }
  }


/*
 *  validate products_id
 */
  function zen_products_id_valid($valid_id) {
    global $db;
    $check_valid = $db->Execute("select p.products_id
                                 from " . TABLE_PRODUCTS . " p
                                 where products_id='" . (int)$valid_id . "' limit 1");
    if ($check_valid->EOF) {
      return false;
    } else {
      return true;
    }
  }

/**
 * Return a product's name.
 *
 * @param int The product id of the product who's name we want
 * @param int The language id to use. If this is not set then the current language is used
*/
  function zen_get_products_name($product_id, $language = '') {
    global $db;

    if (empty($language)) $language = $_SESSION['languages_id'];

    $product_query = "select products_name
                      from " . TABLE_PRODUCTS_DESCRIPTION . "
                      where products_id = '" . (int)$product_id . "'
                      and language_id = '" . (int)$language . "'";

    $product = $db->Execute($product_query);

    return $product->fields['products_name'];
  }


/**
 * Return a product's stock-on-hand
 *
 * @param int $products_id The product id of the product whose stock we want
*/
/* Begin SBA */
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
/*
 *End SBA
*/

/**
 * Check if the required stock is available.
 * If insufficent stock is available return an out of stock message
 *
 * @param int $products_id        The product id of the product whose stock is to be checked
 * @param int $products_quantity  Quantity to compare against
*/
/* Begin SBA */
  function zen_check_stock($products_id, $products_quantity, $attributes = '') {

    $stock_left = zen_get_products_stock($products_id, $attributes) - $products_quantity;
    $out_of_stock = '';

    if ($stock_left < 0) {
      $out_of_stock = '<span class="markProductOutOfStock">' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . '</span>';
    }

    return $out_of_stock;
  }
/*
 *End SBA
*/



/*
 * List manufacturers (returned in an array)
 */
  function zen_get_manufacturers($manufacturers_array = array(), $have_products = false) {
    global $db;
    if (!is_array($manufacturers_array)) $manufacturers_array = array();

    if ($have_products == true) {
      $manufacturers_query = "SELECT DISTINCT m.manufacturers_id, m.manufacturers_name
                              FROM " . TABLE_MANUFACTURERS . " m
                              LEFT JOIN " . TABLE_PRODUCTS . " p ON m.manufacturers_id = p.manufacturers_id
                              WHERE p.products_status = 1
                              AND p.products_quantity > 0
                              ORDER BY m.manufacturers_name";
    } else {
      $manufacturers_query = "SELECT manufacturers_id, manufacturers_name
                              FROM " . TABLE_MANUFACTURERS . "
                              ORDER BY manufacturers_name";
    }

    $manufacturers = $db->Execute($manufacturers_query);

    foreach ($manufacturers as $manufacturer) {
      $manufacturers_array[] = array(
        'id' => $manufacturer['manufacturers_id'],
        'text' => $manufacturer['manufacturers_name']
      );
    }

    return $manufacturers_array;
  }

/*
 *  Check if product has attributes
 */
  function zen_has_product_attributes($products_id, $not_readonly = 'true') {
    global $db;

    if (PRODUCTS_OPTIONS_TYPE_READONLY_IGNORED == '1' and $not_readonly == 'true') {
      // don't include READONLY attributes to determin if attributes must be selected to add to cart
      $attributes_query = "select pa.products_attributes_id
                           from " . TABLE_PRODUCTS_ATTRIBUTES . " pa left join " . TABLE_PRODUCTS_OPTIONS . " po on pa.options_id = po.products_options_id
                           where pa.products_id = '" . (int)$products_id . "' and po.products_options_type != '" . PRODUCTS_OPTIONS_TYPE_READONLY . "' limit 1";
    } else {
      // regardless of READONLY attributes no add to cart buttons
      $attributes_query = "select pa.products_attributes_id
                           from " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                           where pa.products_id = '" . (int)$products_id . "' limit 1";
    }

    $attributes = $db->Execute($attributes_query);

    if ($attributes->recordCount() > 0 && $attributes->fields['products_attributes_id'] > 0) {
      return true;
    } else {
      return false;
    }
  }

/**
 *  Check if specified product has attributes which require selection before adding product to the cart.
 *  This is used by various parts of the code to determine whether to allow for add-to-cart actions
 *  since adding a product without selecting attributes could lead to undesired basket contents.
 *
 *  @param integer $products_id
 *  @return integer
 */
  function zen_requires_attribute_selection($products_id) {
    global $db, $zco_notifier;

    $noDoubles = array();
    $noDoubles[] = PRODUCTS_OPTIONS_TYPE_RADIO;
    $noDoubles[] = PRODUCTS_OPTIONS_TYPE_SELECT;

    $noSingles = array();
    $noSingles[] = PRODUCTS_OPTIONS_TYPE_CHECKBOX;
    $noSingles[] = PRODUCTS_OPTIONS_TYPE_FILE;
    $noSingles[] = PRODUCTS_OPTIONS_TYPE_TEXT;
    if (PRODUCTS_OPTIONS_TYPE_READONLY_IGNORED == '0') {
      $noSingles[] = PRODUCTS_OPTIONS_TYPE_READONLY;
    }

    $query = "select products_options_id, count(pa.options_values_id) as number_of_choices, po.products_options_type as options_type
              from " . TABLE_PRODUCTS_ATTRIBUTES . " pa
              left join " . TABLE_PRODUCTS_OPTIONS . " po on pa.options_id = po.products_options_id
              where pa.products_id = " . (int)$products_id . "
              and po.language_id = " . (int)$_SESSION['languages_id'] . "
              group by products_options_id, options_type";

    $zco_notifier->notify('NOTIFY_FUNCTIONS_LOOKUPS_REQUIRES_ATTRIBUTES_SELECTION', '', $query, $noSingles, $noDoubles);

    $result = $db->Execute($query);

    // if no attributes found, return false
    if ($result->RecordCount() == 0) return false;

    // loop through the results, auditing for whether each kind of attribute requires "selection" or not
    // return whether selections must be made, so a more-info button needs to be presented, if true
    foreach($result as $row => $field) {
      // if there's more than one for any $noDoubles type, can't add from listing
      if (in_array($field['options_type'], $noDoubles) && $field['number_of_choices'] > 1) {
        return true;
      }
      // if there's any type from $noSingles, can't add from listing
      if (in_array($field['options_type'], $noSingles)) {
        return true;
      }
    }

    // return false to indicate that defaults can be automatically added by just using a buy-now button
    return false;
  }
/*
 *  Check if option name is not expected to have an option value (ie. text field, or File upload field)
 */
  function zen_option_name_base_expects_no_values($option_name_id) {
    global $db, $zco_notifier;

    $option_name_no_value = true;
    if (!is_array($option_name_id)) {
      $option_name_id = array($option_name_id);
    }
    
    $sql = "SELECT products_options_type FROM " . TABLE_PRODUCTS_OPTIONS . " WHERE products_options_id :option_name_id:";
    if (sizeof($option_name_id) > 1 ) {
      $sql2 = 'in (';
      foreach($option_name_id as $option_id) {
        $sql2 .= ':option_id:,';
        $sql2 = $db->bindVars($sql2, ':option_id:', $option_id, 'integer');
      }
      $sql2 = rtrim($sql2, ','); // Need to remove the final comma off of the above.
      $sql2 .= ')';
    } else {
      $sql2 = ' = :option_id:';
      $sql2 = $db->bindVars($sql2, ':option_id:', $option_name_id[0], 'integer');
    }
      
    $sql = $db->bindVars($sql, ':option_name_id:', $sql2, 'noquotestring');
    
    $sql_result = $db->Execute($sql);
    
    foreach($sql_result as $opt_type) {

      $test_var = true; // Set to false in observer if the name is not supposed to have a value associated
      $zco_notifier->notify('FUNCTIONS_LOOKUPS_OPTION_NAME_NO_VALUES_OPT_TYPE', $opt_type, $test_var);

      if ($test_var && $opt_type['products_options_type'] != PRODUCTS_OPTIONS_TYPE_TEXT && $opt_type['products_options_type'] != PRODUCTS_OPTIONS_TYPE_FILE) {
        $option_name_no_value = false;
        break;
      }
    }
    
    return $option_name_no_value;
  }
/*
 *  Check if product has attributes values
 */
  function zen_has_product_attributes_values($products_id) {
    global $db;
    
    // -----
    // Allow a watching observer to override this function's return value.
    //
    $value_to_return = '';
    $GLOBALS['zco_notifier']->notify('NOTIFY_ZEN_HAS_PRODUCT_ATTRIBUTES_VALUES', $products_id, $value_to_return);
    if ($value_to_return !== '') {
        return $value_to_return;
    }
    
    $attributes_query = "select count(options_values_price) as total
                         from " . TABLE_PRODUCTS_ATTRIBUTES . "
                         where products_id = " . (int)$products_id . "
                         and options_values_price <> 0";

    $attributes = $db->Execute($attributes_query);

    return ($attributes->fields['total'] != 0);
  }

/*
 * Find category name from ID, in indicated language
 */
  function zen_get_category_name($category_id, $fn_language_id) {
    global $db;
    $category_query = "select categories_name
                       from " . TABLE_CATEGORIES_DESCRIPTION . "
                       where categories_id = '" . $category_id . "'
                       and language_id = '" . $fn_language_id . "'";

    $category = $db->Execute($category_query);

    return $category->fields['categories_name'];
  }


/*
 * Find category description, from category ID, in given language
 */
  function zen_get_category_description($category_id, $fn_language_id) {
    global $db;
    $category_query = "select categories_description
                       from " . TABLE_CATEGORIES_DESCRIPTION . "
                       where categories_id = '" . $category_id . "'
                       and language_id = '" . $fn_language_id . "'";

    $category = $db->Execute($category_query);

    return $category->fields['categories_description'];
  }

/*
 * Return a product's category
 * TABLES: products_to_categories
 */
  function zen_get_products_category_id($products_id) {
    global $db;

    $the_products_category_query = "select products_id, master_categories_id from " . TABLE_PRODUCTS . " where products_id = '" . (int)$products_id . "'";
    $the_products_category = $db->Execute($the_products_category_query);

    return $the_products_category->fields['master_categories_id'];
  }


/*
 * Return category's image
 * TABLES: categories
 */
  function zen_get_categories_image($what_am_i) {
    global $db;

    $the_categories_image_query= "select categories_image from " . TABLE_CATEGORIES . " where categories_id= '" . (int)$what_am_i . "'";
    $result = $db->Execute($the_categories_image_query);

    if ($result->EOF) return '';
    
    return $result->fields['categories_image'];
  }

/*
 *  Return category's name from ID, assuming current language
 *  TABLES: categories_description
 */
  function zen_get_categories_name($who_am_i) {
    global $db;
    $the_categories_name_query= "select categories_name from " . TABLE_CATEGORIES_DESCRIPTION . " where categories_id= '" . (int)$who_am_i . "' and language_id= '" . (int)$_SESSION['languages_id'] . "'";

    $the_categories_name = $db->Execute($the_categories_name_query);
    
    if ($the_categories_name->EOF) return '';

    return $the_categories_name->fields['categories_name'];
  }

/*
 * Return a product's manufacturer's name, from ID
 * TABLES: products, manufacturers
 */
  function zen_get_products_manufacturers_name($product_id) {
    global $db;

    $product_query = "select m.manufacturers_name
                      from " . TABLE_PRODUCTS . " p, " .
                            TABLE_MANUFACTURERS . " m
                      where p.products_id = '" . (int)$product_id . "'
                      and p.manufacturers_id = m.manufacturers_id";

    $product =$db->Execute($product_query);

    return ($product->RecordCount() > 0) ? $product->fields['manufacturers_name'] : "";
  }

/*
 * Return a product's manufacturer's image, from Prod ID
 * TABLES: products, manufacturers
 */
  function zen_get_products_manufacturers_image($product_id) {
    global $db;

    $product_query = "select m.manufacturers_image
                      from " . TABLE_PRODUCTS . " p, " .
                            TABLE_MANUFACTURERS . " m
                      where p.products_id = '" . (int)$product_id . "'
                      and p.manufacturers_id = m.manufacturers_id";

    $product =$db->Execute($product_query);
    if ($product->EOF) return '';
    return $product->fields['manufacturers_image'];
  }

/*
 * Return a product's manufacturer's id, from Prod ID
 * TABLES: products
 */
  function zen_get_products_manufacturers_id($product_id) {
    global $db;

    $product_query = "select p.manufacturers_id
                      from " . TABLE_PRODUCTS . " p
                      where p.products_id = '" . (int)$product_id . "'";

    $product =$db->Execute($product_query);

    return $product->fields['manufacturers_id'];
  }

/*
 * Return attributes products_options_sort_order
 * TABLE: PRODUCTS_ATTRIBUTES
 */
  function zen_get_attributes_sort_order($products_id, $options_id, $options_values_id) {
    global $db;
      $check = $db->Execute("select products_options_sort_order
                             from " . TABLE_PRODUCTS_ATTRIBUTES . "
                             where products_id = '" . (int)$products_id . "'
                             and options_id = '" . (int)$options_id . "'
                             and options_values_id = '" . (int)$options_values_id . "' limit 1");

      return $check->fields['products_options_sort_order'];
  }

/*
 *  return attributes products_options_sort_order
 *  TABLES: PRODUCTS_OPTIONS, PRODUCTS_ATTRIBUTES
 */
  function zen_get_attributes_options_sort_order($products_id, $options_id, $options_values_id, $lang_num = '') {
    global $db;
      if ($lang_num == '') $lang_num = (int)$_SESSION['languages_id'];
      $check = $db->Execute("select products_options_sort_order
                             from " . TABLE_PRODUCTS_OPTIONS . "
                             where products_options_id = '" . (int)$options_id . "' and language_id = '" . $lang_num . "' limit 1");

      $check_options_id = $db->Execute("select products_id, options_id, options_values_id, products_options_sort_order
                             from " . TABLE_PRODUCTS_ATTRIBUTES . "
                             where products_id='" . (int)$products_id . "'
                             and options_id='" . (int)$options_id . "'
                             and options_values_id = '" . (int)$options_values_id . "' limit 1");


      return $check->fields['products_options_sort_order'] . '.' . str_pad($check_options_id->fields['products_options_sort_order'],5,'0',STR_PAD_LEFT);
  }

/*
 *  check if attribute is display only
 */
  function zen_get_attributes_valid($product_id, $option, $value) {
    global $db;

// regular attribute validation
    $check_attributes = $db->Execute("select attributes_display_only, attributes_required from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id='" . (int)$product_id . "' and options_id='" . (int)$option . "' and options_values_id='" . (int)$value . "'");

    $check_valid = true;

// display only cannot be selected
    if (!$check_attributes->EOF && $check_attributes->fields['attributes_display_only'] == '1') {
      $check_valid = false;
    }

// text required validation
    if (preg_match('/^txt_/', $option)) {
      $check_attributes = $db->Execute("select attributes_display_only, attributes_required from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id='" . (int)$product_id . "' and options_id='" . (int)preg_replace('/txt_/', '', $option) . "' and options_values_id='0'");
// text cannot be blank
      if ($check_attributes->fields['attributes_required'] == '1' && (empty($value) && !is_numeric($value))) {
        $check_valid = false;
      }
    }

    return $check_valid;
  }

/*
 * Return Options_Name from ID
 */

  function zen_options_name($options_id) {
    global $db;

    $options_id = str_replace('txt_','',$options_id);

    $options_values = $db->Execute("select products_options_name
                                    from " . TABLE_PRODUCTS_OPTIONS . "
                                    where products_options_id = '" . (int)$options_id . "'
                                    and language_id = '" . (int)$_SESSION['languages_id'] . "'");

    return $options_values->fields['products_options_name'];
  }

/*
 * Return Options_values_name from value-ID
 */
  function zen_values_name($values_id) {
    global $db;

    $values_values = $db->Execute("select products_options_values_name
                                   from " . TABLE_PRODUCTS_OPTIONS_VALUES . "
                                   where products_options_values_id = '" . (int)$values_id . "'
                                   and language_id = '" . (int)$_SESSION['languages_id'] . "'");

    return $values_values->fields['products_options_values_name'];
  }

/*
 *  configuration key value lookup
 *  TABLE: configuration
 */
function zen_get_configuration_key_value($lookup) 
{
    global $db;
    $configuration_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key='" . $lookup . "' LIMIT 1");
    $lookup_value = ($configuration_query->EOF) ? '' : $configuration_query->fields['configuration_value'];
    if (empty($lookup_value)) {
        $lookup_value = '<span class="lookupAttention">' . $lookup . '</span>';
    }
    return $lookup_value;
}

/*
 *  Return products description, based on specified language (or current lang if not specified)
 */
  function zen_get_products_description($product_id, $language = '') {
    global $db;

    if (empty($language)) $language = $_SESSION['languages_id'];

    $product_query = "select products_description
                      from " . TABLE_PRODUCTS_DESCRIPTION . "
                      where products_id = '" . (int)$product_id . "'
                      and language_id = '" . (int)$language . "'";

    $product = $db->Execute($product_query);

    return $product->fields['products_description'];
  }

/*
 * look up the product type from product_id and return an info page name (for template/page handling)
 */
  function zen_get_info_page($zf_product_id) {
    global $db;
    $sql = "select products_type from " . TABLE_PRODUCTS . " where products_id = '" . (int)$zf_product_id . "'";
    $zp_type = $db->Execute($sql);
    if ($zp_type->RecordCount() == 0) {
      return 'product_info';
    } else {
      $zp_product_type = $zp_type->fields['products_type'];
      $sql = "select type_handler from " . TABLE_PRODUCT_TYPES . " where type_id = '" . (int)$zp_product_type . "'";
      $zp_handler = $db->Execute($sql);
      return $zp_handler->fields['type_handler'] . '_info';
    }
  }

/*
 * Get accepted credit cards
 * There needs to be a define on the accepted credit card in the language file credit_cards.php example: TEXT_CC_ENABLED_VISA
 */
  function zen_get_cc_enabled($text_image = 'TEXT_', $cc_seperate = ' ', $cc_make_columns = 0) {
    global $db;
    $cc_check_accepted_query = $db->Execute(SQL_CC_ENABLED);
    $cc_check_accepted = '';
    $cc_counter = 0;
    if ($cc_make_columns == 0) {
      while (!$cc_check_accepted_query->EOF) {
        $check_it = $text_image . $cc_check_accepted_query->fields['configuration_key'];
        if (defined($check_it)) {
          $cc_check_accepted .= constant($check_it) . $cc_seperate;
        }
        $cc_check_accepted_query->MoveNext();
      }
    } else {
      // build a table
      $cc_check_accepted = '<table class="ccenabled">' . "\n";
      $cc_check_accepted .= '<tr class="ccenabled">' . "\n";
      while (!$cc_check_accepted_query->EOF) {
        $check_it = $text_image . $cc_check_accepted_query->fields['configuration_key'];
        if (defined($check_it)) {
          $cc_check_accepted .= '<td class="ccenabled">' . constant($check_it) . '</td>' . "\n";
        }
        $cc_check_accepted_query->MoveNext();
        $cc_counter++;
        if ($cc_counter >= $cc_make_columns) {
          $cc_check_accepted .= '</tr>' . "\n" . '<tr class="ccenabled">' . "\n";
          $cc_counter = 0;
        }
      }
      $cc_check_accepted .= '</tr>' . "\n" . '</table>' . "\n";
    }
    return $cc_check_accepted;
  }

////
// TABLES: categories_name from products_id
  function zen_get_categories_name_from_product($product_id) {
    global $db;

//    $check_products_category= $db->Execute("select products_id, categories_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id='" . $product_id . "' limit 1");
    $check_products_category = $db->Execute("select products_id, master_categories_id from " . TABLE_PRODUCTS . " where products_id = '" . (int)$product_id . "'");
    $the_categories_name= $db->Execute("select categories_name from " . TABLE_CATEGORIES_DESCRIPTION . " where categories_id= '" . $check_products_category->fields['master_categories_id'] . "' and language_id= '" . $_SESSION['languages_id'] . "'");

    return $the_categories_name->fields['categories_name'];
  }


/*
 * configuration key value lookup in TABLE_PRODUCT_TYPE_LAYOUT
 * Used to determine keys/flags used on a per-product-type basis for template-use, etc
 */
  function zen_get_configuration_key_value_layout($lookup, $type=1) {
    global $db;
    $configuration_query= $db->Execute("select configuration_value from " . TABLE_PRODUCT_TYPE_LAYOUT . " where configuration_key='" . $lookup . "' and product_type_id='". (int)$type . "'");
    $lookup_value= $configuration_query->fields['configuration_value'];
    if ( !($lookup_value) ) {
      $lookup_value='<span class="lookupAttention">' . $lookup . '</span>';
    }
    return $lookup_value;
  }

/*
 * look up a products image and send back the image's HTML \<IMG...\> tag
 */
  function zen_get_products_image($product_id, $width = SMALL_IMAGE_WIDTH, $height = SMALL_IMAGE_HEIGHT) {
    global $db;

    $sql = "select p.products_image from " . TABLE_PRODUCTS . " p  where products_id='" . (int)$product_id . "'";
    $look_up = $db->Execute($sql);

    if ($look_up->EOF) {
      return false;
    }
    return zen_image(DIR_WS_IMAGES . $look_up->fields['products_image'], zen_get_products_name($product_id), $width, $height);
  }

/*
 * look up whether a product is virtual
 */
  function zen_get_products_virtual($lookup) {
    global $db;

    $sql = "select p.products_virtual from " . TABLE_PRODUCTS . " p  where p.products_id='" . (int)$lookup . "'";
    $look_up = $db->Execute($sql);

    if ($look_up->fields['products_virtual'] == '1') {
      return true;
    } else {
      return false;
    }
  }

/*
 * Look up whether the given product ID is allowed to be added to cart, according to product-type switches set in Admin
 */
  function zen_get_products_allow_add_to_cart($lookup) {
    global $db;

    $sql = "select products_type from " . TABLE_PRODUCTS . " where products_id='" . (int)$lookup . "'";
    $type_lookup = $db->Execute($sql);

    $sql = "select allow_add_to_cart from " . TABLE_PRODUCT_TYPES . " where type_id = '" . (int)$type_lookup->fields['products_type'] . "'";
    $allow_add_to_cart = $db->Execute($sql);

    return $allow_add_to_cart->fields['allow_add_to_cart'];
  }

/*
 * Look up SHOW_XXX_INFO switch for product ID and product type
 */
    function zen_get_show_product_switch_name($lookup, $field, $prefix= 'SHOW_', $suffix= '_INFO', $field_prefix= '_', $field_suffix='') {
      global $db;

      $sql = "select products_type from " . TABLE_PRODUCTS . " where products_id='" . (int)$lookup . "'";
      $type_lookup = $db->Execute($sql);

      $sql = "select type_handler from " . TABLE_PRODUCT_TYPES . " where type_id = '" . (int)$type_lookup->fields['products_type'] . "'";
      $show_key = $db->Execute($sql);


      $zv_key = strtoupper($prefix . $show_key->fields['type_handler'] . $suffix . $field_prefix . $field . $field_suffix);

      return $zv_key;
    }

/*
 * Look up SHOW_XXX_INFO switch for product ID and product type
 */
    function zen_get_show_product_switch($lookup, $field, $prefix= 'SHOW_', $suffix= '_INFO', $field_prefix= '_', $field_suffix='') {
      global $db;

      $sql = "select products_type from " . TABLE_PRODUCTS . " where products_id='" . $lookup . "'";
      $type_lookup = $db->Execute($sql);

      if ($type_lookup->RecordCount() == 0) {
        return false;
      }

      $sql = "select type_handler from " . TABLE_PRODUCT_TYPES . " where type_id = '" . $type_lookup->fields['products_type'] . "'";
      $show_key = $db->Execute($sql);


      $zv_key = strtoupper($prefix . $show_key->fields['type_handler'] . $suffix . $field_prefix . $field . $field_suffix);

      $sql = "select configuration_key, configuration_value from " . TABLE_PRODUCT_TYPE_LAYOUT . " where configuration_key='" . $zv_key . "'";
      $zv_key_value = $db->Execute($sql);
      if ($zv_key_value->RecordCount() > 0) {
        return $zv_key_value->fields['configuration_value'];
      } else {
        $sql = "select configuration_key, configuration_value from " . TABLE_CONFIGURATION . " where configuration_key='" . $zv_key . "'";
        $zv_key_value = $db->Execute($sql);
        if ($zv_key_value->RecordCount() > 0) {
          return $zv_key_value->fields['configuration_value'];
        } else {
          return false;
        }
      }
    }

/*
 *  Look up whether a product is always free shipping
 */
  function zen_get_product_is_always_free_shipping($lookup) {
    global $db;

    $sql = "select p.product_is_always_free_shipping from " . TABLE_PRODUCTS . " p  where p.products_id='" . (int)$lookup . "'";
    $look_up = $db->Execute($sql);

    if ($look_up->fields['product_is_always_free_shipping'] == '1') {
      return true;
    } else {
      return false;
    }
  }

/*
 *  stop regular behavior based on customer/store settings
 *  Used to disable various activities if store is in an operating mode that should prevent those activities
 */
  function zen_run_normal() {
    $zc_run = false;
    switch (true) {
      case (zen_is_whitelisted_admin_ip()):
      // down for maintenance not for ADMIN
        $zc_run = true;
        break;
      case (DOWN_FOR_MAINTENANCE == 'true'):
      // down for maintenance
        $zc_run = false;
        break;
      case (STORE_STATUS >= 1):
      // showcase no prices
        $zc_run = false;
        break;
      case (CUSTOMERS_APPROVAL == '1' && !zen_is_logged_in()):
      // customer must be logged in to browse
        $zc_run = false;
        break;
      case (CUSTOMERS_APPROVAL == '2' && !zen_is_logged_in()):
      // show room only
      // customer may browse but no prices
        $zc_run = false;
        break;
      case (CUSTOMERS_APPROVAL == '3'):
      // show room only
        $zc_run = false;
        break;
      case (CUSTOMERS_APPROVAL_AUTHORIZATION != '0' && !zen_is_logged_in()):
      // customer must be logged in to browse
        $zc_run = false;
        break;
      case (CUSTOMERS_APPROVAL_AUTHORIZATION != '0' && isset($_SESSION['customers_authorization']) && (int)$_SESSION['customers_authorization'] > 0):
      // customer must be logged in to browse
        $zc_run = false;
        break;
      default:
      // proceed normally
        $zc_run = true;
        break;
    }
    return $zc_run;
  }

/*
 *  Look up whether to show prices, based on customer-authorization levels
 */
function zen_check_show_prices() 
{
    if (!(CUSTOMERS_APPROVAL == '2' and !zen_is_logged_in()) and !((CUSTOMERS_APPROVAL_AUTHORIZATION > 0 and CUSTOMERS_APPROVAL_AUTHORIZATION < 3) and ($_SESSION['customers_authorization'] > '0' or !zen_is_logged_in())) and STORE_STATUS != 1) {
      return true;
    } else {
      return false;
    }
}

/*
 * Return any field from products or products_description table
 * Example: zen_products_lookup('3', 'products_date_added');
 */
  function zen_products_lookup($product_id, $what_field = 'products_name', $language = '') {
    global $db;

    if (empty($language)) $language = $_SESSION['languages_id'];

    $product_lookup = $db->Execute("select " . $what_field . " as lookup_field
                              from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd
                              where p.products_id ='" . (int)$product_id . "'
                              and pd.products_id = p.products_id
                              and pd.language_id = '" . (int)$language . "'");

    $return_field = $product_lookup->fields['lookup_field'];

    return $return_field;
  }

/*
 * Return any field from categories or categories_description table
 * Example: zen_categories_lookup('10', 'parent_id');
 */
  function zen_categories_lookup($categories_id, $what_field = 'categories_name', $language = '') {
    global $db;

    if (empty($language)) $language = $_SESSION['languages_id'];

    $category_lookup = $db->Execute("select " . $what_field . " as lookup_field
                              from " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd
                              where c.categories_id ='" . (int)$categories_id . "'
                              and c.categories_id = cd.categories_id
                              and cd.language_id = '" . (int)$language . "'");

    $return_field = $category_lookup->fields['lookup_field'];

    return $return_field;
  }

/*
 * Find index_filters directory
 * suitable for including template-specific immediate /modules files, such as:
 * new_products, products_new_listing, featured_products, featured_products_listing, product_listing, specials_index, upcoming,
 * products_all_listing, products_discount_prices, also_purchased_products
 */
  function zen_get_index_filters_directory($check_file, $dir_only = 'false') {
    global $template_dir;
    $zv_filename = $check_file;
    if (!strstr($zv_filename, '.php')) $zv_filename .= '.php';
    $checkArray = array();
    $checkArray[] = DIR_WS_INCLUDES . 'index_filters/' . $template_dir . '/' . $zv_filename;
    $checkArray[] = DIR_WS_INCLUDES . 'index_filters/' . $zv_filename;
    $checkArray[] = DIR_WS_INCLUDES . 'index_filters/' . $template_dir . '/' . 'default_filter.php';
    foreach($checkArray as $key => $val) {
      if (file_exists($val)) {
        return ($dir_only == 'true') ? $val = substr($val, 0, strpos($val, '/')) : $val;
      }
    }
    return DIR_WS_INCLUDES . 'index_filters/' . 'default_filter.php';
  }

////
// get define of New Products
  function zen_get_products_new_timelimit($time_limit = false) {
    if ($time_limit == false) {
      $time_limit = SHOW_NEW_PRODUCTS_LIMIT;
    }
    switch (true) {
      case ($time_limit == '0'):
        $display_limit = '';
        break;
      case ($time_limit == '1'):
        $display_limit = " and date_format(p.products_date_added, '%Y%m') >= date_format(now(), '%Y%m')";
        break;
      case ($time_limit == '7'):
        $display_limit = ' and TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 7';
        break;
      case ($time_limit == '14'):
        $display_limit = ' and TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 14';
        break;
      case ($time_limit == '30'):
        $display_limit = ' and TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 30';
        break;
      case ($time_limit == '60'):
        $display_limit = ' and TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 60';
        break;
      case ($time_limit == '90'):
        $display_limit = ' and TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 90';
        break;
      case ($time_limit == '120'):
        $display_limit = ' and TO_DAYS(NOW()) - TO_DAYS(p.products_date_added) <= 120';
        break;
    }
    return $display_limit;
  }

////
// check if Product is set to use downloads
// does not validate download filename
function zen_has_product_attributes_downloads_status($products_id) {
  if (!defined('DOWNLOAD_ENABLED') || DOWNLOAD_ENABLED != 'true') {
    return false;
  }

  $query = "select pad.products_attributes_id
              from " . TABLE_PRODUCTS_ATTRIBUTES . " pa
              inner join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
              on pad.products_attributes_id = pa.products_attributes_id
              where pa.products_id = " . (int) $products_id;

  global $db;
  return ($db->Execute($query)->RecordCount() > 0);
}

// build date range for new products
  function zen_get_new_date_range($time_limit = false) {
    if ($time_limit == false) {
      $time_limit = SHOW_NEW_PRODUCTS_LIMIT;
    }
    // 120 days; 24 hours; 60 mins; 60secs
    $date_range = time() - ($time_limit * 24 * 60 * 60);
    $upcoming_mask_range = time();
    $upcoming_mask = date('Ymd', $upcoming_mask_range);

    $zc_new_date = date('Ymd', $date_range);
    switch (true) {
    case (SHOW_NEW_PRODUCTS_LIMIT == 0):
      $new_range = '';
      break;
    case (SHOW_NEW_PRODUCTS_LIMIT == 1):
      $zc_new_date = date('Ym', time()) . '01';
      $new_range = ' and p.products_date_added >=' . $zc_new_date;
      break;
    default:
      $new_range = ' and p.products_date_added >=' . $zc_new_date;
    }

    if (SHOW_NEW_PRODUCTS_UPCOMING_MASKED == 0) {
      // do nothing upcoming shows in new
    } else {
      // do not include upcoming in new
      $new_range .= " and (p.products_date_available <=" . $upcoming_mask . " or p.products_date_available IS NULL)";
    }
    return $new_range;
  }


// build date range for upcoming products
  function zen_get_upcoming_date_range() {
    // 120 days; 24 hours; 60 mins; 60secs
    $date_range = time();
    $zc_new_date = date('Ymd', $date_range);
// need to check speed on this for larger sites
//    $new_range = ' and date_format(p.products_date_available, \'%Y%m%d\') >' . $zc_new_date;
    $new_range = ' and p.products_date_available >' . $zc_new_date . '235959';

    return $new_range;
  }

/*
 * This function, added to the storefront in zc1.5.6, mimics the like-named admin function in
 * support of plugins that "span" both the storefront and admin.
 *
 * Returns the "name" associated with the specified orders_status_id.
 *
 */
function zen_get_orders_status_name($orders_status_id, $language_id = '') 
{
    if ($language_id == '') {
        $language_id = $_SESSION['languages_id'];
    }
    $orders_status = $GLOBALS['db']->Execute(
        "SELECT orders_status_name
           FROM " . TABLE_ORDERS_STATUS . "
          WHERE orders_status_id = " . (int)$orders_status_id . "
            AND language_id = " . (int)$language_id . "
          LIMIT 1"
    );
    return ($orders_status->EOF) ? '' : $orders_status->fields['orders_status_name'];
}
