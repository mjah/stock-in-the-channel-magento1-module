<?php
$installer = $this;

$config = $installer->getConnection()->getConfig();
$cnx = mysql_connect($config['host'], $config['username'], $config['password']);
if (!$cnx) {
    throw new Exception('Failed to connect to database.');
}

if (!mysql_select_db($config['dbname'])) {
    throw new Exception('Failed to select a database.');
}

$installer->startSetup();

// create a new procedure
$installer->run("DROP PROCEDURE IF EXISTS ".$installer->getTable('filter_sinch_products_s'));

$query = "
CREATE PROCEDURE " . $installer->getTable('filter_sinch_products_s') . "(
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
            name,
            image,
            supplier_id,
            category_feature_id,
            feature_id,
            feature_name,
            feature_value
        )(
            SELECT
              e.entity_id,
              ccpi.category_id,
              e.entity_id,
              ccpi.category_id as sinch_category,
              sp.product_name,
              sp.main_image_url,
              sp.sinch_manufacturer_id,
              scf.category_feature_id,
              scf.category_feature_id,
              scf.feature_name,
              srv.text
            FROM ', arg_table_prefix, 'catalog_product_entity e
            INNER JOIN ', arg_table_prefix, 'stINch_products sp
              ON (sp.store_product_id = e.store_product_id)
            INNER JOIN ', arg_table_prefix, 'catalog_category_product_index ccpi
              ON (e.entity_id = ccpi.product_id)
            INNER JOIN ', arg_table_prefix, 'stINch_product_features spf
              ON (spf.sinch_product_id = e.sinch_product_id)
            INNER JOIN ', arg_table_prefix, 'stINch_restricted_values srv
              ON (srv.restricted_value_id = spf.restricted_value_id)
            INNER JOIN ', arg_table_prefix, 'stINch_categories_features scf
              ON (scf.category_feature_id = srv.category_feature_id)
            INNER JOIN ', arg_table_prefix, 'stINch_categories_mapping scm
              ON (scm.shop_store_category_id = scf.store_category_id)
            WHERE
              scm.shop_entity_id = ', arg_category_id, '
              scm.shop_entity_id = ', arg_category_id, '
              AND PR.main_image_url <> \'\'
            GROUP BY e.entity_id, scf.category_feature_id, scf.feature_name, srv.text
        )
    ');
    ELSE
    SET @updquery = CONCAT('
        INSERT INTO `tmp_result` (
            entity_id,
            category_id,
            product_id,
            sinch_category_id,
            name,
            image,
            supplier_id,
            category_feature_id,
            feature_id,
            feature_name,
            feature_value
        )(
            SELECT
              e.entity_id,
              ccpi.category_id,
              e.entity_id,
              ccpi.category_id as sinch_category,
              sp.product_name,
              sp.main_image_url,
              sp.sinch_manufacturer_id,
              scf.category_feature_id,
              scf.category_feature_id,
              scf.feature_name,
              srv.text
            FROM ', arg_table_prefix, 'catalog_product_entity e
            INNER JOIN ', arg_table_prefix, 'stINch_products sp
              ON (sp.store_product_id = e.store_product_id)
            INNER JOIN ', arg_table_prefix, 'catalog_category_product_index ccpi
              ON (e.entity_id = ccpi.product_id)
            INNER JOIN ', arg_table_prefix, 'stINch_product_features spf
              ON (spf.sinch_product_id = e.sinch_product_id)
            INNER JOIN ', arg_table_prefix, 'stINch_restricted_values srv
              ON (srv.restricted_value_id = spf.restricted_value_id)
            INNER JOIN ', arg_table_prefix, 'stINch_categories_features scf
              ON (scf.category_feature_id = srv.category_feature_id)
            INNER JOIN ', arg_table_prefix, 'stINch_categories_mapping scm
              ON (scm.shop_store_category_id = scf.store_category_id)
            WHERE
              scm.shop_entity_id = ', arg_category_id, '
            GROUP BY e.entity_id, scf.category_feature_id, scf.feature_name, srv.text
        )
    ');
    END IF;

    PREPARE myquery FROM @updquery;
    EXECUTE myquery;
    DROP PREPARE myquery;

    IF (arg_least IS null AND arg_greatest IS null) THEN
        SET @query = CONCAT('
            INSERT INTO `', arg_table_prefix, 'SinchFilterResult_', arg_table, '` (
                entity_id,
                category_id,
                product_id,
                sinch_category_id,
                name,
                image,
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
                    TR.name,
                    TR.image,
                    TR.supplier_id,
                    TR.category_feature_id,
                    TR.feature_id,
                    TR.feature_name,
                    TR.feature_value
                FROM `tmp_result` AS TR
                WHERE TR.category_feature_id = \'', arg_category_feature, '\'
            )
            ON DUPLICATE KEY UPDATE feature_value = TR.feature_value
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
                name,
                image,
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
                    TR.name,
                    TR.image,
                    TR.supplier_id,
                    TR.category_feature_id,
                    TR.feature_id,
                    TR.feature_name,
                    TR.feature_value
                FROM `tmp_result` AS TR
                WHERE TR.category_feature_id = \'', arg_category_feature, '\'',
                @where,'
            )
            ON DUPLICATE KEY UPDATE feature_value = TR.feature_value
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
