#########################################################################################
# Stock by Attributes 2.2.0 Multilanguage Install Zen-Cart 1.5.7 - 2024-04-13 - webchills
#########################################################################################


##############################
# create new table
##############################

CREATE TABLE IF NOT EXISTS products_with_attributes_stock (
        stock_id INT NOT NULL AUTO_INCREMENT,
        products_id INT NOT NULL,
        stock_attributes VARCHAR(255) NOT NULL,
        quantity FLOAT NOT NULL,
        sort INT NOT NULL DEFAULT '0',
        PRIMARY KEY (stock_id)
        ) ENGINE=MyISAM;
        
##############################
# add configuration
##############################

INSERT IGNORE INTO configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function, last_modified) 
VALUES ('Stock by Attributes - Show available stock level in cart when less than order', 'STOCK_SHOW_LOW_IN_CART', 'false', 'When customer places more items in cart than are available, show the available amount on the shopping cart page:','9','6', now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),', now()),
       ('Stock by Attributes - Display Images in Admin', 'STOCK_SHOW_IMAGE', 'true', 'Display image thumbnails on Products With Attributes Stock page? (warning, setting this to true can severly slow the loading of this page):', '9', '6', now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),', now()),
       ('Stock by Attributes - Hide soldout variants', 'SBA_HIDE_SOLDOUT_VARIANTS', 'false', 'Do you want to hide variants which are currently not in stock?', '9', '6', now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),', now()),
       ('Stock by Attributes - Show stock level for variants', 'SBA_SHOW_STOCK_VARIANTS', 'false', 'Do you want to show the available stock for each variant?', '9', '6', now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),', now());
 
      
##############################
# add values for German admin
##############################

REPLACE INTO configuration_language (configuration_title, configuration_key, configuration_description, configuration_language_id) VALUES
('Stock by Attributes - verfügbaren Lagerbestand im Warenkorb anzeigen', 'STOCK_SHOW_LOW_IN_CART', 'Wenn der Kunde mehr in den Warenkorb legt als im Lagerbestand verfügbar, soll dann im Warenkorb die verfügbare Menge angezeigt werden?',	43),
('Stock by Attributes - Bilder im Admin anzeigen', 'STOCK_SHOW_IMAGE', 'Sollen in der Stock by Attributes Administration Thumbnails der Artikelbilder angezeigt werden?<br />Achtung: Wenn das aktiviert wird, kann sich die Ladezeit dieser Adminseite deutlich erhöhen!',	43),
('Stock by Attributes - Nicht lagernde Varianten ausblenden', 'SBA_HIDE_SOLDOUT_VARIANTS', 'Sollen nicht lagernde Varianten ausgeblendet werden?',	43),
('Stock by Attributes - Lagerbestand bei den Varianten anzeigen', 'SBA_SHOW_STOCK_VARIANTS', 'Wollen Sie neben der Variantenauswahl den entsprechenden Lagerbestand anzeigen?<br/>Hinweis: Aktivieren Sie das nur, wenn Sie wirklich für alle Varianten in Stock by Attributes auch einen Lagerbestand hinterlegt haben.<br/>Weitere Voraussetzung: Falls der Attributname ein Radio Button ist, dann muss der Attributstil 3, 4 oder 5 sein.',	43);