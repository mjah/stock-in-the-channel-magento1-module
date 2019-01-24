<?php
$installer = $this;

$installer->startSetup();


$attr_varchar=array(
      'supplier_1' => 'Supplier 1',
      'supplier_2' => 'Supplier 2',
      'supplier_3' => 'Supplier 3',
      'supplier_4' => 'Supplier 4',
      'supplier_5' => 'Supplier 5'
);

   foreach($attr_varchar as $key=>$value){
   
       $installer->addAttribute('catalog_product', $key,array(
                   'label'         => $value,
                   'type'              => 'varchar',
                   'input'             => 'text',
                   'backend'           => 'eav/entity_attribute_backend_array',
                   'frontend'          => '',
                   'source'            => '',
                   'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
                   'visible'           => true,
                   'required'          => false,
                   'user_defined'      => false,
                   'searchable'        => false,
                   'filterable'        => false,
                   'comparable'        => false,
                   'visible_on_front'  => true,
                   'visible_in_advanced_search' => false,
                   'unique'            => false
                   ));


    $data=array(
                    'is_visible_on_front'   => 0,
                    'is_html_allowed_on_front' => 1 
               );
    $entityTypeId = $installer->getEntityTypeId('catalog_product');
    if ($id = $installer->getAttribute($entityTypeId, $key, 'attribute_id')) {
            $installer->updateAttribute($entityTypeId, $id, $data);
    }

}



$installer->endSetup();
