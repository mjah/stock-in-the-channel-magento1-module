<?php

$installer = $this;


$installer->startSetup();

try{
    $installer->run("
                     ALTER IGNORE TABLE ".$installer->getTable('stINch_import_status_statistic')." 
                     ADD COLUMN import_run_type ENUM ('MANUAL', 'CRON') DEFAULt NULL 
                     AFTER detail_status_import;
    ");
}catch(Exception $e){ Mage::log($e->getMessage(), null, 'Sinch.log'); }

$installer->endSetup();
