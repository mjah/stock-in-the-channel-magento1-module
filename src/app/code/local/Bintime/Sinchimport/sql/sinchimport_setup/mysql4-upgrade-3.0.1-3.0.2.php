<?php
$installer = $this;

$installer->startSetup();

$installer->run("
                    DROP TABLE IF EXISTS ".$installer->getTable('stINch_distributors').";
                ");

$installer->run("
                    CREATE TABLE ".$installer->getTable('stINch_distributors')."
               (
                      distributor_id int(11),
                      distributor_name varchar(255),
                      website varchar(255),
                      KEY(distributor_id)
               );
                  ");

$installer->run("
                    DROP TABLE IF EXISTS ".$installer->getTable('stINch_distributors_stock_and_price').";
                ");

$installer->run("
                    CREATE TABLE ".$installer->getTable('stINch_distributors_stock_and_price')."
               (
                     `store_product_id` int(11) DEFAULT NULL,
                     `distributor_id` int(11) DEFAULT NULL,
                     `stock` int(11) DEFAULT NULL,
                     `cost` decimal(15,4) DEFAULT NULL,
                     `distributor_sku` varchar(255) DEFAULT NULL,
                     `distributor_category` varchar(50) DEFAULT NULL,
                     `eta` varchar(50) DEFAULT NULL,
                      UNIQUE KEY `product_distri` (store_product_id, distributor_id)
               );
                  ");


$attr_text=array(
            'sinch_search_cache' => 'Sinch Search Cache' 
        );

foreach($attr_text as $key=>$value){

    $installer->addAttribute('catalog_product', $key,array(
                'label'             => $value,
                'type'              => 'text',
                'input'             => 'textarea',
                'backend'           => 'eav/entity_attribute_backend_array',
                'frontend'          => '',
                'source'            => '',
                'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
                'visible'           => false,
                'required'          => false,
                'user_defined'      => false,
                'searchable'        => 1,
                'filterable'        => false,
                'comparable'        => false,
                'visible_on_front'  => true,
                'is_visible_on_front' => 1,
                'is_html_allowed_on_front' => 1,
                'visible_in_advanced_search' => false,
                'unique'            => false
                ));

    $installer->updateAttribute('catalog_product', $key, 'is_searchable', '1');
}

$attr_varchar=array(
       'pdf_url' => 'PDF Url'
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
                    'is_visible_on_front'   => 1,
                    'is_html_allowed_on_front' => 1
               );
    $entityTypeId = $installer->getEntityTypeId('catalog_product');
    if ($id = $installer->getAttribute($entityTypeId, $key, 'attribute_id')) {
            $installer->updateAttribute($entityTypeId, $id, $data);
    }

}



$installer->endSetup();
