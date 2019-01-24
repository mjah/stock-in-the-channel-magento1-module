<?php

$installer = $this;


$installer->startSetup();

$installer->run("
    DROP TABLE IF EXISTS ".$installer->getTable('stINch_import_status_statistic').";

    CREATE TABLE ".$installer->getTable('stINch_import_status_statistic')."(
        id int(11) NOT NULL auto_increment PRIMARY KEY,
        start_import timestamp NOT NULL default '0000-00-00 00:00:00',
        finish_import timestamp NOT NULL default '0000-00-00 00:00:00',    
        number_of_products int(11) default '0',
        global_status_import varchar(255) default '',
        detail_status_import varchar(255) default '',
        error_report_message  text not null default ''
    );


");
/*
    DROP TABLE IF EXISTS `stINch_import_log;

	CREATE TABLE `stINch_import_log`(
                `entity_id` bigint NOT NULL auto_increment PRIMARY KEY,
                `message_date` timestamp NOT NULL default '0000-00-00 00:00:00',
		        `message` text
	);

*/
$installer->endSetup();
