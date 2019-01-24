<?php

class Bintime_Sinchimport_Model_Observer extends Mage_Index_Model_Resource_Abstract
{
    protected $_existsFlatTables     = array();
    protected $_attributes;

    protected function _construct()
    {
        $this->_init('catalog/product', 'entity_id');
    }

    public function prepareColumn(Varien_Event_Observer $obs)
    {
        $columnsObject = $obs->getColumns();
        $storeProductId = array(
            "type" => "int(10)",
            "unsigned" => true,
            "is_null" => false,
            "default" => NULL,
            "extra" => NULL
        );

        $sinchProductId = array(
            "type" => "int(10)",
            "unsigned" => true,
            "is_null" => false,
            "default" => NULL,
            "extra" => NULL
        );
        $columnsObject->setColumns(array_merge($columnsObject->getColumns(), array('store_product_id' => $storeProductId, 'sinch_product_id' => $sinchProductId)));
        return $columnsObject;
    }

    public function updateEventAttributes(Varien_Event_Observer $obs)
    {
        $adapter   = $this->_getWriteAdapter();
        $storeId = $obs->getStoreId();
        if (!$this->_isFlatTableExists($storeId)) {
            return $this;
        }

        $sql = "UPDATE {$obs->getTable()} as t2 INNER JOIN catalog_product_entity AS e SET t2.store_product_id = e.store_product_id, t2.sinch_product_id = e.sinch_product_id where t2.entity_id = e.entity_id";
        $adapter->query($sql);
    }

    protected function _isFlatTableExists($storeId)
    {
        if (!isset($this->_existsFlatTables[$storeId])) {
            $tableName     = $this->getFlatTableName($storeId);
            $isTableExists = $this->_getWriteAdapter()->isTableExists($tableName);

            $this->_existsFlatTables[$storeId] = $isTableExists ? true : false;
        }

        return $this->_existsFlatTables[$storeId];
    }

    public function getFlatTableName($storeId)
    {
        return sprintf('%s_%s', $this->getTable('catalog/product_flat'), $storeId);
    }

}