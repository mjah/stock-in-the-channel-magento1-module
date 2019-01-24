<?php
$installer = $this;
$installer->startSetup();

$installer->run("
	-- DROP TABLE IF EXISTS {$this->getTable('sinch_product_backup')};
	CREATE TABLE IF NOT EXISTS {$this->getTable('sinch_product_backup')} (
	  `entity_id` int(11) unsigned NOT NULL,
	  `sku` varchar(64) NULL,
	  `store_product_id` int(10) unsigned NOT NULL,
	  `sinch_product_id` int(11) unsigned NOT NULL,
      UNIQUE KEY (entity_id),
	  KEY sku (sku),
	  KEY store_product_id (store_product_id),
	  KEY sinch_product_id (sinch_product_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

	-- DROP TABLE IF EXISTS {$this->getTable('sinch_category_backup')};
	CREATE TABLE IF NOT EXISTS {$this->getTable('sinch_category_backup')} (
	  `entity_id` int(10) UNSIGNED NOT NULL,
	  `entity_type_id` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
	  `attribute_set_id` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
	  `parent_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
	  `store_category_id` int(11) UNSIGNED DEFAULT NULL,
	  `parent_store_category_id` int(11) UNSIGNED DEFAULT NULL,
	  UNIQUE KEY (entity_id),
	  KEY entity_type_id (entity_type_id),
	  KEY attribute_set_id (attribute_set_id),
	  KEY parent_id (parent_id),
	  KEY store_category_id (store_category_id),
	  KEY parent_store_category_id (parent_store_category_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

");

$installer->endSetup();
