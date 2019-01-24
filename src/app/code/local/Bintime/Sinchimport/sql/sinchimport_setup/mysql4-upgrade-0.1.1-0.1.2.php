<?php
/**
stepan
*/
$installer = $this;

$config = $installer->getConnection()->getConfig();
$cnx = mysqli_connect($config['host'], $config['username'], $config['password']);
if (!$cnx) {
		  throw new Exception('Failed to connect to database.');
}

if (!mysqli_select_db($cnx, $config['dbname'])) {
		  throw new Exception('Failed to select a database.');
}

$check_store_product_id=1;
$check_sinch_product_id=1;
$q="show columns from catalog_product_entity";
$res=mysqli_query($cnx, $q);
while($row=mysqli_fetch_assoc($res)){
		  if($row['Field']=='store_product_id'){
					 $check_store_product_id=0;
		  }elseif($row['Field']=='sinch_product_id'){
					 $check_sinch_product_id=0;
		  }
}


$installer->startSetup();

if($check_store_product_id){
        try{
		  $installer->run("
								ALTER TABLE {$this->getTable('catalog_product_entity')}
								ADD COLUMN `store_product_id` INT(11) UNSIGNED NULL
								");
        }catch(Exception $e){ Mage::log($e->getMessage(), null, 'Sinch.log'); }
        try{
		  $installer->run("
								ALTER IGNORE TABLE {$this->getTable('catalog_product_entity')}
								ADD INDEX `store_product_id` (`store_product_id`);
								");
        }catch(Exception $e){ Mage::log($e->getMessage(), null, 'Sinch.log'); }
}
if($check_sinch_product_id){
        try{
		  $installer->run("
								ALTER TABLE {$this->getTable('catalog_product_entity')}
								ADD COLUMN `sinch_product_id` INT(11) UNSIGNED NULL
								");
        }catch(Exception $e){ Mage::log($e->getMessage(), null, 'Sinch.log'); }
        try{
		  $installer->run("
								ALTER IGNORE TABLE {$this->getTable('catalog_product_entity')}
								ADD INDEX `sinch_product_id` (`sinch_product_id`);
								");
        }catch(Exception $e){ Mage::log($e->getMessage(), null, 'Sinch.log'); }
}

//$installer->installEntities();

$installer->endSetup();
