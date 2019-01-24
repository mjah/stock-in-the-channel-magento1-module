<?php

$installer = $this;

//прямое подключение к базе необходимо для добавления хранимки
    $config = $installer->getConnection()->getConfig();
    $cnx = mysqli_connect($config['host'], $config['username'], $config['password']);
    if (!$cnx) {
        throw new Exception('Failed to connect to database.');
    }

    if (!mysqli_select_db($cnx, $config['dbname'])) {
        throw new Exception('Failed to select a database.');
    }

$installer->startSetup();

$installer->run("DROP PROCEDURE IF EXISTS ".$installer->getTable('filter_sinch_products_s'));

$query = "
CREATE PROCEDURE ".$installer->getTable('filter_sinch_products_s')."(
                                            IN arg_table INT,
                                            IN arg_category_id INT,
                                            IN arg_image INT,
                                            IN arg_category_feature INT,
                                            IN arg_least INT,
                                            IN arg_greatest INT
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
				INSERT INTO `tmp_result` (entity_id, category_id, product_id, sinch_category_id, `name`, `image`, supplier_id, category_feature_id, feature_id, feature_name, feature_value)
				(
					SELECT E.entity_id, PCind.category_id, E.entity_id, PCind.category_id as `sinch_category`, PR.`product_name`, PR.main_image_url, PR.sinch_manufacturer_id, CF.category_feature_id, CF.category_feature_id, CF.`feature_name`, RV.`text`
						FROM ".$installer->getTable('catalog_product_entity')." E
                                                INNER JOIN ".$installer->getTable('catalog_category_product_index')." PCind
                                                    ON (E.entity_id = PCind.product_id)
				INNER JOIN ".$installer->getTable('stINch_categories_features')." CF
				ON (PCind.category_id=CF.store_category_id)
				INNER JOIN ".$installer->getTable('stINch_products')." PR
                                ON (PR.store_product_id = E.store_product_id)
                                INNER JOIN ".$installer->getTable('stINch_product_features')." PF
                                ON (PR.sinch_product_id = PF.sinch_product_id )
                                INNER JOIN ".$installer->getTable('stINch_restricted_values')." RV
                                ON (PF.restricted_value_id=RV.restricted_value_id)
                                                WHERE
                                                    PCind.category_id = arg_category_id
                                                    AND PR.main_image_url <> ''
				);
			ELSE
				INSERT INTO `tmp_result` (entity_id, category_id, product_id, sinch_category_id, `name`, `image`, supplier_id, category_feature_id, feature_id, feature_name, feature_value)
				(
					SELECT E.entity_id, PCind.category_id, E.entity_id, PCind.category_id as `sinch_category`, PR.`product_name`, PR.main_image_url, PR.sinch_manufacturer_id, CF.category_feature_id, CF.category_feature_id, CF.`feature_name`, RV.`text`
						FROM ".$installer->getTable('catalog_product_entity')." E
                                                INNER JOIN ".$installer->getTable('catalog_category_product_index')." PCind
                                                    ON (E.entity_id = PCind.product_id)
				INNER JOIN ".$installer->getTable('stINch_categories_features')." CF
				ON (PCind.category_id=CF.store_category_id)
				INNER JOIN ".$installer->getTable('stINch_products')." PR
                                ON (PR.store_product_id = E.store_product_id)
                                INNER JOIN ".$installer->getTable('stINch_product_features')." PF
                                ON (PR.sinch_product_id = PF.sinch_product_id )
                                INNER JOIN ".$installer->getTable('stINch_restricted_values')." RV
                                ON (PF.restricted_value_id=RV.restricted_value_id)
                                                WHERE
                                                    PCind.category_id = arg_category_id
 
				);

                        END IF;

		IF (SELECT COUNT(*) FROM `FilterListOfFeatures`) > 0 THEN
                    SET @query = CONCAT('INSERT INTO `SinchFilterResult_', arg_table, '` (entity_id, category_id, product_id, sinch_category_id, `name`, `image`, supplier_id, category_feature_id, feature_id, feature_name, feature_value)
			(
				SELECT TR.entity_id, TR.category_id, TR.product_id, TR.sinch_category_id, TR.`name`, TR.`image`, TR.supplier_id, TR.category_feature_id, TR.feature_id, TR.feature_name, TR.feature_value
				FROM `tmp_result` AS TR
					INNER JOIN `FilterListOfFeatures` AS LF ON (TR.category_feature_id = LF.category_feature_id)
				WHERE TR.feature_value LIKE LF.feature_value GROUP BY entity_id
			)');

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

			SET @query = CONCAT('INSERT INTO `SinchFilterResult_', arg_table, '` (entity_id, category_id, product_id, sinch_category_id, `name`, `image`, supplier_id, category_feature_id, feature_id, feature_name, feature_value)
			(
				SELECT TR.entity_id, TR.category_id, TR.product_id, TR.sinch_category_id, TR.`name`, TR.`image`, TR.supplier_id, TR.category_feature_id, TR.feature_id, TR.feature_name, TR.feature_value
				FROM `tmp_result` AS TR
				WHERE TR.category_feature_id = \'', arg_category_feature, '\'',
                                @where,
				'GROUP BY entity_id
			)');

                END IF;

                PREPARE myquery FROM @query;
                EXECUTE myquery;
                DROP PREPARE myquery;

END
    ";

if (!mysqli_query($cnx, $query)) {
    throw new Exception("Failed to create stored procedure");
}

mysqli_close($cnx);

$installer->endSetup();
