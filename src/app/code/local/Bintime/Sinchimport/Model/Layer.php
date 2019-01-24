<?php

class Bintime_Sinchimport_Model_Layer extends Mage_Catalog_Model_Layer
{
    /**
     * Возвращает фичи, по которым следует строить навигацию для данной категории.
     * 
     * @return mixed
     */
    public function getFilterableFeatures()
    {
        Varien_Profiler::start(__METHOD__);

	$category =  Mage::registry('current_category');
    if( empty($category) ) { $category = Mage::getModel('catalog/category')->load( Mage::app()->getStore()->getRootCategoryId() ); }
	$categoryId = $category->getEntityId();
        $resource = Mage::getSingleton('core/resource');
	$tCategor =     $resource->getTableName('stINch_categories');
        $tCatFeature =  $resource->getTableName('stINch_categories_features');
        $tRestrictedVal =  $resource->getTableName('stINch_restricted_values');
        $tCategMapp    =    $resource->getTableName('stINch_categories_mapping');
        
        $select = new Varien_Db_Select(Mage::getSingleton('core/resource')->getConnection('core_read'));
        $select->from(array('cf' => $tCatFeature))
                ->joinInner(
                            array('rv' => $tRestrictedVal),
                            'cf.category_feature_id = rv.category_feature_id'
                            )
                ->joinInner(
                            array('cm' => $tCategMapp),
                            'cf.store_category_id = cm.store_category_id'
                                                                                                    )
                ->where('cm.shop_entity_id = '.$categoryId)
		->group('cf.feature_name')
		->order('cf.display_order_number', 'asc')
		->order('cf.feature_name', 'asc')
		->order('rv.display_order_number', 'asc');
                ;
	$select->columns('cf.feature_name AS name');
	$select->columns('cf.category_feature_id as feature_id');
	$select->columns('GROUP_CONCAT(`rv`.`text` SEPARATOR "\n") as restricted_values');
        $result = $select->query();
        Varien_Profiler::stop(__METHOD__);
        return $result;
    }

}
