<?php
$baseDir = dirname(__FILE__);
require $baseDir . '/../../../../../app/Mage.php';

Mage::app();

$import=Mage::getModel('sinchimport/sinch');

$import->run_sinch_import();

$import->addImportStatus('Finish import', 1);

