<?php
/**
stepan
*/
$installer = $this;

$installer->startSetup();


$attr_text=array(
            'specification' => 'Specification',
            'manufacturer' => 'Manufacturer',
            'ean' => 'EAN', 
            'sku' => 'SKU'
        );

foreach($attr_text as $key=>$value){
$data=array(
'is_visible_on_front'   => 1,
'is_html_allowed_on_front' => 1
);    
$entityTypeId = $installer->getEntityTypeId('catalog_product');
if ($id = $installer->getAttribute($entityTypeId, $key, 'attribute_id')) {
                $installer->updateAttribute($entityTypeId, $id, $data);
}

}

$attr_filt=array(
            'manufacturer' => 'Manufacturer'
                    );

foreach($attr_filt as $key=>$value){
    $data=array(
    'is_filterable'   => 1,
    'is_global' => 1
    );
    $entityTypeId = $installer->getEntityTypeId('catalog_product');
    if ($id = $installer->getAttribute($entityTypeId, $key, 'attribute_id')) {
                        $installer->updateAttribute($entityTypeId, $id, $data);
    }

    $sets = $installer->_conn->fetchAll('select * from '.$installer->getTable('eav/attribute_set').' where entity_type_id=?', $entityTypeId);
    foreach ($sets as $set) {
        $installer->addAttributeToSet($entityTypeId, $set['attribute_set_id'], 'Global', 'manufacturer');
    }

}


//$installer->installEntities();

$installer->endSetup();
