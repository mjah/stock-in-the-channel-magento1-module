<?php

$installer = $this;


$installer->startSetup();

try{
    $installer->run("
            ALTER IGNORE TABLE ".$installer->getTable('stINch_import_status_statistic')."
            ADD COLUMN `import_type` ENUM('FULL', 'PRICE STOCK') default NULL after finish_import
            ");
}catch(Exception $e){ Mage::log($e->getMessage(), null, 'Sinch.log'); }

$installer->endSetup();
