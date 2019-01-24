<?php

$installer = $this;


$installer->startSetup();

$installer->run("
    CREATE TABLE IF NOT EXISTS ".$installer->getTable('stINch_products_mapping')."(
        entity_id int(11) unsigned NOT NULL,
        manufacturer_option_id int(11),
        manufacturer_name varchar(255),
        shop_store_product_id int(11),
        shop_sinch_product_id int(11),
        sku varchar(64) default NULL,
        store_product_id int(11),
        sinch_product_id int(11),
        product_sku varchar(255),
        sinch_manufacturer_id int(11),
        sinch_manufacturer_name varchar(255),
        KEY entity_id (entity_id),
        KEY manufacturer_option_id (manufacturer_option_id),
        KEY manufacturer_name (manufacturer_name),
        KEY store_product_id (store_product_id),
        KEY sinch_product_id (sinch_product_id),
        KEY sku (sku),
        UNIQUE KEY(entity_id)
    );
");

$installer->run("
    CREATE TABLE IF NOT EXISTS ".$installer->getTable('stINch_products')."(
        store_product_id int(11),
        sinch_product_id int(11),
        product_sku varchar(255),
        product_name varchar(255),
        sinch_manufacturer_id int(11),
        store_category_id int(11),
        main_image_url varchar(255),
        thumb_image_url varchar(255),
        specifications text,
        description text,
        search_cache text,
        spec_characte_u_count int(11),
        description_type varchar(50),
        medium_image_url varchar(255),
        products_date_added datetime default NULL,
        products_last_modified datetime default NULL,
        availability_id_in_stock int(11) default '1',
        availability_id_out_of_stock int(11) default '2',
        products_locate varchar(30) default NULL,
        products_ordered int(11) NOT NULL default '0',
        products_url varchar(255) default NULL,
        products_viewed int(5) default '0',
        products_seo_url varchar(100) NOT NULL,
        manufacturer_name varchar(255) default NULL,
        KEY(store_product_id),
        KEY(sinch_manufacturer_id),
        KEY(store_category_id)
    )DEFAULT CHARSET=utf8;
");

$installer->run("
    CREATE TABLE IF NOT EXISTS ".$installer->getTable('stINch_categories_features')."(
        category_feature_id int(11),
        store_category_id int(11),
        feature_name varchar(50),
        display_order_number int(11),
        KEY(store_category_id),
        KEY(category_feature_id)
    );
");

$installer->run("
    CREATE TABLE IF NOT EXISTS ".$installer->getTable('stINch_restricted_values')."(
        restricted_value_id int(11),
        category_feature_id int(11),
        text text,
        display_order_number int(11),
        KEY(restricted_value_id),
        KEY(category_feature_id)
    );
");

$installer->run("
    CREATE TABLE IF NOT EXISTS ".$installer->getTable('stINch_product_features')."(
        product_feature_id int(11),
        sinch_product_id int(11),
        restricted_value_id int(11),
        KEY(sinch_product_id),
        KEY(restricted_value_id)
    );
");

$installer->run("
    CREATE TABLE IF NOT EXISTS ".$installer->getTable('stINch_categories_mapping')."(
        shop_entity_id int(11) unsigned NOT NULL,
        shop_entity_type_id int(11),
        shop_attribute_set_id int(11),
        shop_parent_id int(11),
        shop_store_category_id int(11),
        shop_parent_store_category_id int(11),
        store_category_id int(11),
        parent_store_category_id int(11),
        category_name varchar(255),
        order_number int(11),
        products_within_this_category int(11),
        KEY shop_entity_id (shop_entity_id),
        KEY shop_parent_id (shop_parent_id),
        KEY store_category_id (store_category_id),
        KEY parent_store_category_id (parent_store_category_id),
        UNIQUE KEY(shop_entity_id)
    );
");

/*
$installer->run("
    CREATE TABLE IF NOT EXISTS ".$installer->getTable('stINch_products_mapping')."(
    );
");
*/

$installer->endSetup();
