<?php
$baseDir = dirname(__FILE__);
require $baseDir . '/../../../../../app/Mage.php';

Mage::app();

$import=Mage::getModel('sinchimport/sinch');

$import->run_stock_price_sinch_import();

$import->addImportStatus('Stock Price Finish import', 1);
