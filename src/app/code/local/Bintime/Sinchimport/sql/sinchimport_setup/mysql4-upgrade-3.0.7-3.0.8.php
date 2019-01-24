<?php
$installer = $this;
$installer->startSetup();

$installer->run("
	ALTER TABLE {$this->getTable('catalog_category_entity_varchar')} MODIFY COLUMN `value` VARCHAR(1000) NOT NULL default ''
");

$installer->endSetup();
