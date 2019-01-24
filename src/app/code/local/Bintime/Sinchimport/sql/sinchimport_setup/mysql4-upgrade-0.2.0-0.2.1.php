<?php

$installer = $this;

$installer->startSetup();

$installer->run("
                 DROP TABLE IF EXISTS ".$installer->getTable('stINch_sinchcheck').";
               ");


$installer->run("
                 CREATE TABLE ".$installer->getTable('stINch_sinchcheck')."
			(
				id            INTEGER      NOT NULL AUTO_INCREMENT,
				caption       VARCHAR(100) NOT NULL DEFAULT 'caption',
				descr         VARCHAR(100) NOT NULL DEFAULT 'descr',
				check_code    VARCHAR(100)          DEFAULT NULL,
				check_value   VARCHAR(100)          DEFAULT 'check value',
				check_measure VARCHAR(100)          DEFAULT 'check measure',
				error_msg     VARCHAR(256)          DEFAULT 'error message',
				fix_msg       VARCHAR(256)          DEFAULT 'fix message',
				PRIMARY KEY (id),
				UNIQUE KEY uk_check_code(check_code)
			);
               ");


$installer->run("
INSERT ".$installer->getTable('stINch_sinchcheck')."(caption, descr, check_code, check_value, check_measure, 
		error_msg, fix_msg) 
	VALUE('Physical memory', 'checking system memory', 'memory', '2048', 'MB', 
		'You have %s MB of memory', 'You need to enlarge memory to %s');
               ");

$installer->run("
INSERT ".$installer->getTable('stINch_sinchcheck')."(caption, descr, check_code, check_value, check_measure, 
		error_msg, fix_msg) 
	VALUE('Loaddata option', 'checking mysql load data', 'loaddata', 'ON', '', 
		'You need to enable the MySQL Loaddata option', 'You need to add set-variable=local-infile=1 or modify this line in /etc/my.cnf and restart MySQL');
               ");

$installer->run("
INSERT ".$installer->getTable('stINch_sinchcheck')."(caption, descr, check_code, check_value, check_measure, 
		error_msg, fix_msg) 
	VALUE('PHP safe mode', 'checking php safe mode', 'phpsafemode', 'OFF', '', 
		'You need to set PHP safe mode to %s', 'You need to set safe_mode = %s in /etc/php.ini');
               ");

$installer->run("
INSERT ".$installer->getTable('stINch_sinchcheck')."(caption, descr, check_code, check_value, check_measure, 
		error_msg, fix_msg) 
	VALUE('MySQL Timeout', 'checking mysql wait timeout', 'waittimeout', '28000', 'sec', 
		'Wait_timeout is too short:', 'You need to set wait_timeout = %s in /etc/my.cnf and restart MySQL');
               ");

$installer->run("
INSERT ".$installer->getTable('stINch_sinchcheck')."(caption, descr, check_code, check_value, check_measure, 
		error_msg, fix_msg) 
	VALUE('MySQL Buffer Pool', 'checking mysql innodb buffer pool size', 'innodbbufpool', '512', 'MB', 
		'The innodb_buffer_pool_size in /etc/my.cnf is %s', 'It needs to be set to %s or higher');
               ");

$installer->run("
INSERT ".$installer->getTable('stINch_sinchcheck')."(caption, descr, check_code, check_value, check_measure, 
		error_msg, fix_msg) 
	VALUE('PHP run string', 'checking php5 run string', 'php5run', 'php5', '', 
		'PHP_RUN_STRING uses value:', 'Change it to define(PHP_RUN_STRING, php5) in Bintime/Sinchimport/Model/config.php');
               ");

$installer->run("
INSERT ".$installer->getTable('stINch_sinchcheck')."(caption, descr, check_code, check_value, check_measure, 
		error_msg, fix_msg) 
	VALUE('File Permissions', 'checking chmod wget', 'chmodwget', '0755', '', 
		'You need to assign more rights to wget', 'Run chmod a+x wget');
               ");

$installer->run("
INSERT ".$installer->getTable('stINch_sinchcheck')."(caption, descr, check_code, check_value, check_measure, 
		error_msg, fix_msg) 
	VALUE('File Permissions', 'checking chmod for cron.php', 'chmodcronphp', '0755', '', 
		'You need to assign more rights to Magento Cron', 'Run chmod +x [shop dir]/cron.php');
               ");

$installer->run("
INSERT ".$installer->getTable('stINch_sinchcheck')."(caption, descr, check_code, check_value, check_measure, 
		error_msg, fix_msg) 
	VALUE('File Permissions', 'checking chmod for cron.sh', 'chmodcronsh', '0755', '', 
		'You need to assign more rights to Magento Cron', 'Run chmod +x [shop dir]/cron.sh');
               ");

$installer->run("
INSERT ".$installer->getTable('stINch_sinchcheck')."(caption, descr, check_code, 
		check_value, check_measure, 
		error_msg, fix_msg) 
	VALUE('Missing Procedure', 'checking absense of procedure ".$installer->getTable('filter_sinch_products_s').".sql store procedue and showing hot to add it', 'routine',
		'".$installer->getTable('filter_sinch_products_s')."', '', 
		'You are missing the MySQL stored procedure ".$installer->getTable('filter_sinch_products_s').".sql', 'You can recreate it by running the script found in [shop dir]/app/code/local/Bintime/Sinchimport/sql/ in PhpMyAdmin');
               ");

mysqli_close($cnx);

$installer->endSetup();





