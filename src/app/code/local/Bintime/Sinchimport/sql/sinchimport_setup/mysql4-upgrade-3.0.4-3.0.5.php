<?php
$installer = $this;

//прямое подключение к базе необходимо для добавления хранимки
$config = $installer->getConnection()->getConfig();
$cnx = mysql_connect($config['host'], $config['username'], $config['password']);
if (!$cnx) {
    throw new Exception('Failed to connect to database.');
}

if (!mysql_select_db($config['dbname'])) {
    throw new Exception('Failed to select a database.');
}

$installer->startSetup();


$attr_varchar=array(
      'contract_id' => 'Contract ID',
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


$installer->run("DROP PROCEDURE IF EXISTS ".$installer->getTable('filter_sinch_products_s'));

$query = "
CREATE PROCEDURE ".$installer->getTable('filter_sinch_products_s')."(
                                            IN arg_table INT,
                                            IN arg_category_id INT,
                                            IN arg_image INT,
                                            IN arg_category_feature INT,
                                            IN arg_least INT,
                                            IN arg_greatest INT,
                                            IN arg_table_prefix VARCHAR(255)
                                           )
BEGIN
    DROP TABLE IF EXISTS `tmp_result`;

    CREATE TEMPORARY TABLE `tmp_result`(
        `entity_id` int(10) unsigned,
        `category_id` int(10) unsigned,
        `product_id` int,
        `sinch_category_id` int,
        `name` varchar(255),
        `image` varchar(255),
        `supplier_id` int,
        `category_feature_id` int,
        `feature_id` int,
        `feature_name` varchar(255),
        `feature_value` text
    );


    IF arg_image = 1 THEN
    SET @updquery = CONCAT('

        INSERT INTO `tmp_result` (
            entity_id, 
            category_id, 
            product_id, 
            sinch_category_id, 
            `name`, 
            `image`, 
            supplier_id, 
            category_feature_id, 
            feature_id, 
            feature_name, 
            feature_value
        )(
          SELECT 
            E.entity_id, 
            PCind.category_id, 
            E.entity_id, 
            PCind.category_id as `sinch_category`, 
            PR.`product_name`, 
            PR.main_image_url, 
            PR.sinch_manufacturer_id, 
            CF.category_feature_id, 
            CF.category_feature_id, 
            CF.`feature_name`, 
            RV.`text`
          FROM ', arg_table_prefix, 'catalog_product_entity E
          INNER JOIN ', arg_table_prefix, 'catalog_category_product_index PCind
            ON (E.entity_id = PCind.product_id)
          INNER JOIN ', arg_table_prefix, 'stINch_categories_mapping scm 
            ON PCind.category_id=scm.shop_entity_id
          INNER JOIN ',arg_table_prefix, 'stINch_categories_features CF
            ON (scm.store_category_id=CF.store_category_id)
          INNER JOIN ',arg_table_prefix, 'stINch_products PR
            ON (PR.store_product_id = E.store_product_id)
          INNER JOIN ',arg_table_prefix, 'stINch_product_features PF
            ON (PR.sinch_product_id = PF.sinch_product_id )
          INNER JOIN ',arg_table_prefix, 'stINch_restricted_values RV
            ON (PF.restricted_value_id=RV.restricted_value_id)
          WHERE
            scm.shop_entity_id = ', arg_category_id, '
            AND PR.main_image_url <> \'\'
        )
    ');
    ELSE
    SET @updquery = CONCAT('

        INSERT INTO `tmp_result` (
            entity_id, 
            category_id, 
            product_id, 
            sinch_category_id, 
            `name`, 
            `image`, 
            supplier_id, 
            category_feature_id, 
            feature_id, 
            feature_name, 
            feature_value
        )(
          SELECT 
            E.entity_id, 
            PCind.category_id, 
            E.entity_id, 
            PCind.category_id as `sinch_category`, 
            PR.`product_name`, 
            PR.main_image_url, 
            PR.sinch_manufacturer_id, 
            CF.category_feature_id, 
            CF.category_feature_id, 
            CF.`feature_name`, 
            RV.`text`
          FROM ', arg_table_prefix ,'catalog_product_entity E
          INNER JOIN ', arg_table_prefix, 'catalog_category_product_index PCind
            ON (E.entity_id = PCind.product_id)
          INNER JOIN ', arg_table_prefix, 'stINch_categories_mapping scm 
            ON PCind.category_id=scm.shop_entity_id
          INNER JOIN ', arg_table_prefix, 'stINch_categories_features CF
            ON (scm.store_category_id=CF.store_category_id)
          INNER JOIN ', arg_table_prefix, 'stINch_products PR
            ON (PR.store_product_id = E.store_product_id)
          INNER JOIN ', arg_table_prefix, 'stINch_product_features PF
            ON (PR.sinch_product_id = PF.sinch_product_id )
          INNER JOIN ', arg_table_prefix, 'stINch_restricted_values RV
            ON (PF.restricted_value_id=RV.restricted_value_id)
          WHERE
            scm.shop_entity_id = ', arg_category_id, '
 
        )
    ');
    END IF;

    PREPARE myquery FROM @updquery;
    EXECUTE myquery;
    DROP PREPARE myquery; 
    
    SET @filter_features_count = 0;
    SET @ifquery = CONCAT('SELECT COUNT(*) INTO @filter_features_count FROM `', arg_table_prefix, 'FilterListOfFeatures`');
    PREPARE myquery FROM @ifquery;
    EXECUTE myquery;
    DROP PREPARE myquery;

    IF (@filter_features_count) > 0 THEN
        SET @query = CONCAT('
                             INSERT INTO `', arg_table_prefix, 'SinchFilterResult_', arg_table, '` (
                                entity_id, 
                                category_id, 
                                product_id, 
                                sinch_category_id, 
                                `name`, 
                                `image`, 
                                supplier_id, 
                                category_feature_id, 
                                feature_id, 
                                feature_name, 
                                feature_value
                             )(
                               SELECT 
                                TR.entity_id, 
                                TR.category_id, 
                                TR.product_id, 
                                TR.sinch_category_id, 
                                TR.`name`, 
                                TR.`image`, 
                                TR.supplier_id, 
                                TR.category_feature_id, 
                                TR.feature_id, 
                                TR.feature_name, 
                                TR.feature_value
                               FROM `tmp_result` AS TR
                               INNER JOIN `', arg_table_prefix, 'FilterListOfFeatures` AS LF 
                                ON (TR.category_feature_id = LF.category_feature_id)
                               WHERE TR.feature_value LIKE LF.feature_value GROUP BY entity_id
                             )
                           ');

    ELSE
        IF (arg_least IS NOT null AND arg_greatest IS NOT null) THEN
            SET @where = CONCAT(' AND TR.feature_value >= ', arg_least, ' AND TR.feature_value <', arg_greatest, ' ');
        ELSE
            IF arg_least IS null THEN
                SET @where = CONCAT(' AND TR.feature_value < ', arg_greatest, ' ');
            ELSE
                SET @where = CONCAT(' AND TR.feature_value >= ', arg_least, ' ');
            END IF;
        END IF;

        SET @query = CONCAT('
                             INSERT INTO `', arg_table_prefix, 'SinchFilterResult_', arg_table, '` (
                                entity_id, 
                                category_id, 
                                product_id, 
                                sinch_category_id, 
                                `name`, 
                                `image`, 
                                supplier_id, 
                                category_feature_id, 
                                feature_id, 
                                feature_name, 
                                feature_value
                             )(
                               SELECT 
                                TR.entity_id, 
                                TR.category_id, 
                                TR.product_id, 
                                TR.sinch_category_id, 
                                TR.`name`, 
                                TR.`image`, 
                                TR.supplier_id, 
                                TR.category_feature_id, 
                                TR.feature_id, 
                                TR.feature_name, 
                                TR.feature_value
                               FROM `tmp_result` AS TR
                               WHERE TR.category_feature_id = \'', arg_category_feature, '\'',
                                @where,
                               'GROUP BY entity_id
                             )
                            ');

    END IF;

    PREPARE myquery FROM @query;
    EXECUTE myquery;
    DROP PREPARE myquery;

END
";

if (!mysql_query($query, $cnx)) {
    throw new Exception("Failed to create stored procedure".$query);
}


mysql_close($cnx);


$installer->endSetup();
