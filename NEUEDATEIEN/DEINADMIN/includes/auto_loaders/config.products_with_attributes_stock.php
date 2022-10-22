<?php
/**
 * @package Stock by Attributes for Zen Cart German
 * @copyright Copyright 2003-2022 Zen Cart Development Team
 * Zen Cart German Version - www.zen-cart-pro.at
 * @copyright Portions Copyright 2003 osCommerce
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 * @version $Id: config.products_with_attributes_stock.php 2022-10-22 16:10:14Z webchills $
 */

 $autoLoadConfig[0][] = array(
  'autoType' => 'class',
  'loadFile' => 'observers/class.products_with_attributes_stock.php',
  'classPath'=>DIR_WS_CLASSES
  );
 $autoLoadConfig[199][] = array(
  'autoType' => 'classInstantiate',
  'className' => 'products_with_attributes_stock_admin',
  'objectName' => 'products_with_attributes_stock_admin_observe'
  );
 $autoLoadConfig[0][] = array(
   'autoType' => 'class',
   'loadFile' => 'products_with_attributes_stock.php',
   'classPath'=> DIR_WS_CLASSES
 );
 $autoLoadConfig[199][] = array(
   'autoType' => 'classInstantiate',
   'className' => 'products_with_attributes_stock',
   'objectName' => 'products_with_attributes_stock_class'
 );
 $autoLoadConfig[0][] = array(
   'autoType' => 'class',
   'loadFile' => 'class.products_with_attributes_class_stock.php'
 );
 $autoLoadConfig[199][] = array(
  'autoType' => 'classInstantiate',
  'className' => 'products_with_attributes_class_stock',
  'objectName' => 'pwas_class2',
  'checkInstantiated' => true,
  'classSession'=>true
  ); 
 $autoLoadConfig[200][] = array(
  'autoType' => 'objectMethod',
  'objectName' => 'pwas_class2',
  'methodName' => '__construct'
  ); 