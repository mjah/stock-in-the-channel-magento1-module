<?php
ini_set('memory_limit','256M');
$dir = Mage::getBaseDir('code')."/local/Bintime/Sinchimport/Model";//dirname(__FILE__);
require_once ($dir.'/config.php');

class Bintime_Sinchimport_Model_Backup extends Mage_Core_Model_Abstract {
	var $connection, $db;

	function __construct() {
		$this->connection=$this->db_connect();
		$this->backupProductIds();
		$this->backupCategoryIds();
	}

	private function backupProductIds() {
		$this->db_do("TRUNCATE ".Mage::getSingleton('core/resource')->getTableName('sinch_product_backup'));
		$result = $this->db_do("
			INSERT INTO ".Mage::getSingleton('core/resource')->getTableName('sinch_product_backup')." (
				entity_id,
				sku,
                store_product_id,
				sinch_product_id
			)(SELECT
					entity_id,
					sku,
                	store_product_id,
					sinch_product_id
				FROM ".Mage::getSingleton('core/resource')->getTableName('catalog_product_entity')."
		 )
		");
	}

	private function backupCategoryIds() {
		$this->db_do("TRUNCATE ".Mage::getSingleton('core/resource')->getTableName('sinch_category_backup'));
		$result = $this->db_do("
			INSERT INTO ".Mage::getSingleton('core/resource')->getTableName('sinch_category_backup')." (
				entity_id,
                entity_type_id,
                attribute_set_id,
                parent_id,
                store_category_id,
                parent_store_category_id
			)(SELECT
				entity_id,
                entity_type_id,
                attribute_set_id,
                parent_id,
                store_category_id,
                parent_store_category_id
			FROM ".Mage::getSingleton('core/resource')->getTableName('catalog_category_entity')."
		 	)
		");
	}

	////// Utility functions
	private function db_connect() {
		$dbConf = Mage::getConfig()->getResourceConnectionConfig('core_setup');
		$dbConn = mysqli_init();
		mysqli_options($dbConn, MYSQLI_OPT_LOCAL_INFILE, true);
		if (mysqli_real_connect($dbConn, $dbConf->host, $dbConf->username, $dbConf->password)) {
			$this->db = $dbConn;
			if(!mysqli_select_db($this->db, $dbConf->dbname)) {
				throw new Exception("Can't select the database: " . mysqli_error($this->db));
			}
		} else {
			throw new Exception("Could not connect: " . mysqli_error($this->db));
		}

	}

	private function db_do($query) {
		$result = mysqli_query($this->db, $query) or throw new Exception("Query failed: " . mysqli_error($this->db));
		if (!$result) {
			throw new Exception("Invalid query: $sql\n" . mysqli_error($this->db));
		} else {
			return $result;
		}
		return $result;
	}
}