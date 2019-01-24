<?php
/**
stepan
*/
$installer = $this;

$installer->startSetup();

$attr_varchar=array(        
    		'ean' => 'EAN'
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
}


$attr_text=array(
            'specification' => 'Specification' 
        );

foreach($attr_text as $key=>$value){

	$installer->addAttribute('catalog_product', $key,array(
				'label'		    => $value,
				'type'              => 'text',
				'input'             => 'textarea',
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
                'is_visible_on_front' => true,
                'is_html_allowed_on_front' => true,
				'visible_in_advanced_search' => false,
				'unique'            => false
				));
}


//$installer->installEntities();

$installer->endSetup();
