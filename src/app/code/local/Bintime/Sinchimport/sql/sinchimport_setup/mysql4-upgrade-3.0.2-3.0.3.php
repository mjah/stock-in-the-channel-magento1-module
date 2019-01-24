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

$installer->run("DROP FUNCTION IF EXISTS ".$installer->getTable('func_calc_price'));

$query = "
CREATE FUNCTION ".$installer->getTable('func_calc_price')." (price decimal(8,2) , marge decimal(10,2), fixed decimal(10,2), final_price decimal(10,2)) RETURNS decimal(8,2)
BEGIN
    IF marge IS NOT NULL THEN
        RETURN price + price * marge / 100;
    END IF;
    IF fixed IS NOT NULL THEN
        RETURN price + fixed;
    END IF;
    IF final_price IS NOT NULL THEN
        RETURN final_price;
    END IF;
    RETURN price;
END
";

if (!mysqli_query($cnx, $query)) {
    throw new Exception("Failed to create stored function");
}

mysqli_close($cnx);

$installer->endSetup();
