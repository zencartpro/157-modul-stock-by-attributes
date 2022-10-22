###########################################################################
# Stock by Attributes 2.1.0 - UNINSTALL - 2022-10-22 - webchills
# NUR AUSFÜHREN WENN SIE DAS MODUL AUS DER DATENBANK ENTFERNEN WOLLEN!!!!!
###########################################################################


##############################
# delete config settings
##############################

DELETE FROM configuration WHERE configuration_key IN ('STOCK_SHOW_LOW_IN_CART','STOCK_SHOW_IMAGE','SBA_HIDE_SOLDOUT_VARIANTS','SBA_SHOW_STOCK_VARIANTS');
DELETE FROM configuration_language WHERE configuration_key IN ('STOCK_SHOW_LOW_IN_CART','STOCK_SHOW_IMAGE','SBA_HIDE_SOLDOUT_VARIANTS','SBA_SHOW_STOCK_VARIANTS');


##############################
# delete pwa table
# Kommentieren Sie die nächste Zeile mit einer Raute, falls Sie die bereits hinterlegten Lagerbestände NICHT löschen wollen
##############################

DROP TABLE IF EXISTS products_with_attributes_stock;      

##############################
# delete from admin access
##############################

DELETE FROM admin_pages WHERE page_key='stock_by_attributes';    
DELETE FROM admin_pages WHERE page_key='stock_by_attributes_ajax';    