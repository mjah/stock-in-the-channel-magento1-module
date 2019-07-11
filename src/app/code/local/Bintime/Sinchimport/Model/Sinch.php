<?php
ini_set('memory_limit', '768M'); # Moe : Increased memory limit from 256M to 768M
$dir = Mage::getBaseDir('code') . "/local/Bintime/Sinchimport/Model";
require_once($dir . '/config.php');

class Bintime_Sinchimport_Model_Sinch extends Mage_Core_Model_Abstract
{
    var
        $connection,
        $varDir,
        $shellDir,
        $files,
        $attributes,
        $db,
        $lang_id,
        $debug_mode = 0;
    public $php_run_string;
    public $php_run_strings;
    public $price_breaks_filter;
    private $field_terminated_char;

    private $productDescriptionList = array();
    private $specifications;
    private $productDescription;
    private $fullProductDescription;
    private $lowPicUrl;
    private $highPicUrl;
    private $galleryPhotos = array();
    private $productName;
    private $relatedProducts = array();
    private $sinchProductId;

    private $_productEntityTypeId = 0;
    private $defaultAttributeSetId = 0;
    private $current_import_status_statistic_id;

    private $import_status_table;
    private $import_status_statistic_table;
    private $import_log_table;

    private $_attributeId;
    private $_categoryEntityTypeId;
    private $_categoryDefault_attribute_set_id;
    private $_root_cat;
    private $_categoryMetaTitleAttrId;
    private $_categoryMetadescriptionAttrId;
    private $_categoryDescriptionAttrId;

    private $_ignore_category_features = false;
    private $_ignore_product_features = false;
    private $_ignore_product_related = false;
    private $_ignore_product_categories = false;
    private $_ignore_product_contracts = false;
    private $_ignore_price_rules = false;
    private $_ignore_restricted_values = false;

    private $product_file_format = "NEW";
    private $import_run_type = 'MANUAL';
    private $im_type;

    function __construct()
    {
        $this->import_status_table = Mage::getSingleton('core/resource')->getTableName('stINch_import_status');
        $this->import_status_statistic_table = Mage::getSingleton('core/resource')->getTableName('stINch_import_status_statistic');
        $this->import_log_table = "stINch_import_log";

        $this->php_run_string = PHP_RUN_STRING;
        $this->php_run_strings = PHP_RUN_STRINGS;

        $this->price_breaks_filter = PRICE_BREAKS;
        $this->field_terminated_char = DEFAULT_FILE_TERMINATED_CHAR;

        $this->varDir = Mage::getBaseDir('var') . DS . 'bintime' . DS . 'sinchimport' . DS;
        // $this->varDir = str_replace('\\', '/', $this->varDir);
        if (!is_dir($this->varDir)) {
            mkdir($this->varDir, 0777, true);
        }

        $this->shellDir = Mage::getBaseDir('base') . DS . 'shell' . DS;

        $this->connection = $this->db_connect();

        $this->_logFile = "Sinch.log";
        $this->_errorLogFile = "SinchError.log";

        $this->files = array(
            FILE_CATEGORIES,
            FILE_CATEGORY_TYPES,
            FILE_CATEGORIES_FEATURES,
            FILE_DISTRIBUTORS,
            FILE_DISTRIBUTORS_STOCK_AND_PRICES,
            FILE_EANCODES,
            FILE_MANUFACTURERS,
            FILE_PRODUCT_FEATURES,
            FILE_PRODUCT_CATEGORIES,
            FILE_PRODUCTS,
            FILE_RELATED_PRODUCTS,
            FILE_RESTRICTED_VALUES,
            FILE_STOCK_AND_PRICES,
            FILE_PRODUCTS_PICTURES_GALLERY,
            FILE_PRICE_RULES,
            FILE_PRODUCT_CONTRACTS
        );
        $this->attributes['manufacturer'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('manufacturer')->getFirstItem()->getId();
        $this->attributes['name'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('name')->getFirstItem()->getId();
        $this->attributes['is_active'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('is_active')->getFirstItem()->getId();
        $this->attributes['include_in_menu'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('include_in_menu')->getFirstItem()->getId();
        $this->attributes['url_key'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('url_key')->getFirstItem()->getId();
        $this->attributes['display_mode'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('display_mode')->getFirstItem()->getId();
        $this->attributes['status'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('status')->getFirstItem()->getId();
        $this->attributes['visibility'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('visibility')->getFirstItem()->getId();
        $this->attributes['price'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('price')->getFirstItem()->getId();
        $this->attributes['cost'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('cost')->getFirstItem()->getId();
        $this->attributes['weight'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('weight')->getFirstItem()->getId();
        $this->attributes['tax_class_id'] = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter('tax_class_id')->getFirstItem()->getId();
    }

    private function db_connect()
    {
        $dbConf = Mage::getConfig()->getResourceConnectionConfig('core_setup');
        $dbConn = mysqli_init();
        mysqli_options($dbConn, MYSQLI_OPT_LOCAL_INFILE, true);
        if (mysqli_real_connect($dbConn, $dbConf->host, $dbConf->username, $dbConf->password)) {
            $this->db = $dbConn;
            if (!mysqli_select_db($this->db, $dbConf->dbname)) {
                throw new Exception("Can't select the database: " . mysqli_error($this->db));
            }
        } else {
            throw new Exception("Could not connect: " . mysqli_error($this->db));
        }

    }

    function _LOG($log, $isError = false)
    {
        if ($log) {
            if ($isError) {
                Mage::log($log, null, $this->_errorLogFile);
            } else {
                Mage::log($log, null, $this->_logFile);
            }
        }
    }

    function cron_start_import()
    {
        $this->_LOG("Start import from cron");
        $start_hr = Mage::getStoreConfig('sinchimport_root/sinch_cron/sinch_cron_time');
        $now_hr = date('H');
        $this->_LOG("Now $now_hr hr, scheduler time is $start_hr hr");

        if ($start_hr == $now_hr) {
            $this->run_sinch_import();
        } else {
            $this->_LOG(" it's NOT time for SINCH ");
        }

        $this->_LOG("Finish import from cron");
    }
    
    function run_sinch_import()
    {
        $this->_categoryMetaTitleAttrId = $this->_getCategoryAttributeId('meta_title');
        $this->_categoryMetadescriptionAttrId = $this->_getCategoryAttributeId('meta_description');
        $this->_categoryDescriptionAttrId = $this->_getCategoryAttributeId('description');

        $safe_mode_set = ini_get('safe_mode');

        $this->InitImportStatuses('FULL');
        if ($safe_mode_set) {
            $this->_LOG('safe_mode is enable. import stoped.');
            $this->set_import_error_reporting_message('Safe_mode is enabled. Please check the documentation on how to fix this. Import stopped.');
            throw new Exception("Safe mode is enabled");
        }
        $store_proc = $this->check_store_procedure_exist();

        if (!$store_proc) {
            $this->_LOG('store prcedure "' . Mage::getSingleton('core/resource')->getTableName('filter_sinch_products_s') . '" is absent in this database. import stoped.');
            $this->set_import_error_reporting_message('Stored procedure "' . Mage::getSingleton('core/resource')->getTableName('filter_sinch_products_s') . '" is absent in this database. Import stopped.');
            throw new Exception("filter_sinch_products_s missing from database");
        }

        $file_privileg = $this->check_db_privileges();

        if (!$file_privileg) {
            $this->_LOG("Loaddata option not set - please check the documentation on how to fix this. You dan't have privileges for LOAD DATA.");
            $this->set_import_error_reporting_message("Loaddata option not set - please check the documentation on how to fix this. Import stopped.");
            throw new Exception("LOAD DATA not enabled");
        }
        $local_infile = $this->check_local_infile();
        if (!$local_infile) {
            $this->_LOG("Loaddata option not set - please check the documentation on how to fix this. Add this string to  'set-variable=local-infile=0' in '/etc/my.cnf'");
            $this->set_import_error_reporting_message("Loaddata option not set - please check the documentation on how to fix this. Import stopped.");
            throw new Exception("LOCAL INFILE not enabled");
        }

        if ($this->is_imort_not_run()) {
            try {
                $q = "SELECT GET_LOCK('sinchimport', 30)";
                $this->db_do($q);

                $import = $this;
                $import->addImportStatus('Start Import');
                Mage::log("Upload Files\n", null, "importstatus.log");
                $import->UploadFiles();
                $import->addImportStatus('Upload Files');

                Mage::log("Parse Category Types\n", null, "importstatus.log");
                $import->ParseCategoryTypes();

                Mage::log("Parse Categories\n", null, "importstatus.log");
                $coincidence = $import->ParseCategories();
                $import->addImportStatus('Parse Categories');

                Mage::log("Parse Category Features\n", null, "importstatus.log");
                $import->ParseCategoryFeatures();
                $import->addImportStatus('Parse Category Features');

                Mage::log("Parse Distributors\n", null, "importstatus.log");
                $import->ParseDistributors();
                if ($this->product_file_format == "NEW") {
                    $this->ParseDistributorsStockAndPrice();
                    $this->ParseProductContracts();
                }
                $import->addImportStatus('Parse Distributors');

                Mage::log("Parse EAN Codes\n", null, "importstatus.log");
                $import->ParseEANCodes();
                $import->addImportStatus('Parse EAN Codes');


                Mage::log("Parse Manufacturers\n", null, "importstatus.log");
                $import->ParseManufacturers();
                $import->addImportStatus('Parse Manufacturers');

                Mage::log("Parse Related Products\n", null, "importstatus.log");
                $import->ParseRelatedProducts();
                $import->addImportStatus('Parse Related Products');

                Mage::log("Parse Product Features\n", null, "importstatus.log");
                $import->ParseProductFeatures();
                $import->addImportStatus('Parse Product Features');

                Mage::log("Parse Product Categories\n", null, "importstatus.log");
                $import->ParseProductCategories();

                Mage::log("Parse Products\n", null, "importstatus.log");
                $import->ParseProducts($coincidence);
                $import->addImportStatus('Parse Products');

                Mage::log("Parse Pictures Gallery\n", null, "importstatus.log");
                $import->ParseProductsPicturesGallery();
                $import->addImportStatus('Parse Pictures Gallery');

                Mage::log("Parse Restricted Values\n", null, "importstatus.log");
                $import->ParseRestrictedValues();
                $import->addImportStatus('Parse Restricted Values');

                Mage::log("Parse Stock And Prices\n", null, "importstatus.log");
                $import->ParseStockAndPrices();
                $import->addImportStatus('Parse Stock And Prices');

                Mage::log("Apply Customer Group Price\n", null, "importstatus.log");
                //$import->ParsePriceRules();
                //$import->AddPriceRules();
                //$import->ApplyCustomerGroupPrice();

                $ftpCred = Mage::getStoreConfig('sinchimport_root/sinch_ftp');
                Mage::dispatchEvent('sinch_import_after', array('ftp_cred' => $ftpCred));

                Mage::log("Finish Sinch import", null, $this->_logFile);
                Mage::log("Finish Sinch import\n", null, "importstatus.log");

                Mage::log("Start cleanin Sinch cache\n", null, $this->_logFile);
                Mage::log("Start cleanin Sinch cache\n", null, "importstatus.log");
                Mage::app()->getCacheInstance()->cleanType('block_html');

                Mage::log("Start indexing Sinch features for filters", null, $this->_logFile);
                Mage::log("Start indexing Sinch features for filters\n", null, "importstatus.log");

                // Drop feature result tables
                $this->_dropFeatureResultTables();

                Mage::log("Finish indexing Sinch features for filters", null, $this->_logFile);
                $import->addImportStatus('Generate category filters');
                Mage::log("Finish indexing Sinch features for filters\n", null, "importstatus.log");


                Mage::log("Start indexing data", null, $this->_logFile);
                Mage::log("Start indexing data\n", null, "importstatus.log");
                $import->_cleanCateoryProductFlatTable();
                $import->runIndexer();

                // Reindex manufacturers
                $import->reindexManufacturers();

                Mage::log("Finish indexing data", null, $this->_logFile);
                $import->addImportStatus('Indexing data', 1);
                Mage::log("Finish indexing data\n", null, "importstatus.log");

                $q = "SELECT RELEASE_LOCK('sinchimport')";
                $this->db_do($q);

                $ftpCred = Mage::getStoreConfig('sinchimport_root/sinch_ftp');
                Mage::dispatchEvent('sinch_import_after', array('ftp_cred' => $ftpCred, 'full_import' => true));
            } catch (Exception $e) {
                $this->set_import_error_reporting_message($e);
            }
        } else {
            Mage::log("Sinchimport already run", null, $this->_logFile);
            Mage::log("Sinchimport already run\n", null, "importstatus.log");
        }

    }

    private function _getCategoryAttributeId($attributeCode)
    {
        return $this->_getAttributeId($attributeCode, 'catalog_category');
    }

    private function _getAttributeId($attributeCode, $typeCode)
    {
        if ($typeCode == 'catalog_product') {
            $typeId = $this->_getProductEntityTypeId();
        } else {
            $typeId = $this->_getEntityTypeId($typeCode);
        }
        if (!isset($this->_attributeId[$typeCode]) OR !is_array($this->_attributeId[$typeCode])) {
            $sql = "
                    SELECT attribute_id, attribute_code
                    FROM " . Mage::getSingleton('core/resource')->getTableName('eav_attribute') . "
                    WHERE entity_type_id = '" . $typeId . "'
                   ";
            $result = $this->db_do($sql);
            while ($row = mysqli_fetch_assoc($result)) {
                $this->_attributeId[$typeCode][$row['attribute_code']] = $row['attribute_id'];
            }
        }
        return $this->_attributeId[$typeCode][$attributeCode];
    }

    private function _getProductEntityTypeId()
    {
        if (!$this->_productEntityTypeId) {
            $this->_productEntityTypeId = $this->_getEntityTypeId('catalog_product');
        }
        return $this->_productEntityTypeId;
    }

    private function _getEntityTypeId($code)
    {
        $sql = "
                SELECT entity_type_id
                FROM " . Mage::getSingleton('core/resource')->getTableName('eav_entity_type') . "
                WHERE entity_type_code = '" . $code . "'
                LIMIT 1
               ";
        $result = $this->db_do($sql);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['entity_type_id'];
        }
        return false;
    }

    private function db_do($query, $stopLog = false)
    {
        if ($this->debug_mode && !$stopLog) {
            Mage::log("Query: " . $query, null, $this->_logFile);
        }
        $result = mysqli_query($this->db, $query);
        if (!$result) {
            $this->_LOG("Invalid query: $query\n    -->Exception: " . mysqli_error($this->db), true);
            throw new Exception("Invalid query: $query\n" . mysqli_error($this->db));
        } else {
            return $result;
        }
        return $result;
    }

    function InitImportStatuses($type)
    {
        $this->db_do("DROP TABLE IF EXISTS " . $this->import_status_table);
        $this->db_do("CREATE TABLE " . $this->import_status_table . "(
                        id int(11) NOT NULL auto_increment PRIMARY KEY,
                        message varchar(50),
                        finished int(1) default 0
                      )"
        );
        $this->db_do("INSERT INTO " . $this->import_status_statistic_table . " (
                        start_import,
                        finish_import,
                        import_type,
                        global_status_import,
                        import_run_type,
                        error_report_message)
                      VALUES(
                        now(),
                        now(),
                        '$type',
                        'Run',
                        '" . $this->import_run_type . "',
                        ''
                      )
                    ");
        $q = "SELECT MAX(id) AS id FROM " . $this->import_status_statistic_table;

        $quer = $this->db_do($q);
        $row = mysqli_fetch_array($quer);
        $this->current_import_status_statistic_id = $row['id'];
        $this->db_do("UPDATE " . $this->import_status_statistic_table . "
                      SET global_status_import='Failed'
                      WHERE global_status_import='Run' AND id!=" . $this->current_import_status_statistic_id);

    }

    function set_import_error_reporting_message($message)
    {
        $this->db_do("UPDATE " . $this->import_status_statistic_table . "
                      SET error_report_message='" . mysqli_real_escape_string($this->db, $message) . "'
                      WHERE id=" . $this->current_import_status_statistic_id);
    }

    function check_store_procedure_exist()
    {
        $dbConf = Mage::getConfig()->getResourceConnectionConfig('core_setup');
        $q = 'SHOW PROCEDURE STATUS LIKE "' . Mage::getSingleton('core/resource')->getTableName('filter_sinch_products_s') . '"';
        $quer = $this->db_do($q);
        $result = false;
        While ($row = mysqli_fetch_array($quer)) {
            if (($row['Name'] == Mage::getSingleton('core/resource')->getTableName('filter_sinch_products_s')) && ($row['Db'] == $dbConf->dbname)) {
                $result = true;
            }
        }
        return $result;
    }

    function check_db_privileges()
    {
        $q = 'SHOW PRIVILEGES';
        $quer = $this->db_do($q);
        while ($row = mysqli_fetch_array($quer)) {
            if ($row['Privilege'] == 'File' && $row['Context'] == 'File access on server') {
                return true;
            }
        }
        return false;
    }

    function check_local_infile()
    {
        $q = 'SHOW VARIABLES LIKE "local_infile"';
        $quer = $this->db_do($q);
        $row = mysqli_fetch_array($quer);
        if ($row['Variable_name'] == 'local_infile' && $row['Value'] == "ON") {
            return true;
        } else {
            return false;
        }
    }

    function is_imort_not_run()
    {
        $q = "SELECT IS_FREE_LOCK('sinchimport') as getlock";
        $quer = $this->db_do($q);
        $row = mysqli_fetch_array($quer);
        return $row['getlock'];
    }

    function addImportStatus($message, $finished = 0)
    {
        $q = "INSERT INTO " . $this->import_status_table . "
            (message, finished)
            VALUES('" . $message . "', $finished)";
        $this->db_do($q);
        $this->db_do("UPDATE " . $this->import_status_statistic_table . "
                      SET detail_status_import='" . $message . "'
                      WHERE id=" . $this->current_import_status_statistic_id);
        if ($finished == 1) {
            $this->db_do("UPDATE " . $this->import_status_statistic_table . "
                          SET
                            global_status_import='Successful',
                            finish_import=now()
                          WHERE
                                error_report_message='' and
                                id=" . $this->current_import_status_statistic_id);
        }
    }

    function UploadFiles()
    {
        $this->_LOG("Start upload files");
        $dataConf = Mage::getStoreConfig('sinchimport_root/sinch_ftp');
        $login = $dataConf['login'];
        $passw = $dataConf['password'];
        $server = $dataConf['ftp_server'];

        if (!$login || !$passw) {
            $this->_LOG('ftp login or password not defined');
            $this->set_import_error_reporting_message('FTP login or password has not been defined. Import stopped.');
            throw new Exception("FTP login/password not defined");
        }

        $file_url_and_dir = $this->repl_ph(FILE_URL_AND_DIR, array(
                'server' => $server,
                'login' => $login,
                'password' => $passw
            )
        );
        foreach ($this->files as $file) {
            $this->_LOG("Copy " . $file_url_and_dir . $file . " to  " . $this->varDir . $file);
            if (strstr($file_url_and_dir, 'ftp://')) {
                preg_match("/ftp:\/\/(.*?):(.*?)@(.*?)(\/.*)/i", $file_url_and_dir, $match);
                if ($conn = ftp_connect($match[3])) {
                    if (!ftp_login($conn, $login, $passw)) {
                        $this->set_import_error_reporting_message('Incorrect username or password for the Stock In The Channel server. Import stopped.');
                        throw new Exception("Username or password invalid, FTP login failed");
                    }
                } else {
                    $this->set_import_error_reporting_message('FTP connection failed. Unable to connect to the Stock In The Channel server');
                    throw new Exception("FTP connection failed");
                }
                if (!$this->wget($file_url_and_dir . $file, $this->varDir . $file, 'system')) {
                    $this->_LOG("wget Can't copy " . $file . ", will use old one");
                    Mage::log("copy Can't copy " . $file_url_and_dir . $file . " to  " . $this->varDir . $file . ", will use old one\n", null, "importstatus.log");
                }
            } else {
                if (!copy($file_url_and_dir . $file, $this->varDir . $file)) {
                    $this->_LOG("copy Can't copy " . $file . ", will use old one");
                    Mage::log("copy Can't copy " . $file_url_and_dir . $file . " to  " . $this->varDir . $file . " will use old one\n", null, "importstatus.log");
                }
            }
            exec("chmod a+rw " . $this->varDir . $file);
            if (!filesize($this->varDir . $file)) {
                if ($file != FILE_CATEGORIES_FEATURES && $file != FILE_PRODUCT_FEATURES && $file != FILE_RELATED_PRODUCTS && $file != FILE_RESTRICTED_VALUES && $file != FILE_PRODUCT_CATEGORIES && $file != FILE_CATEGORY_TYPES && $file != FILE_DISTRIBUTORS_STOCK_AND_PRICES && $file != FILE_PRODUCT_CONTRACTS && $file != FILE_PRICE_RULES) {
                    $this->_LOG("Can't copy " . $file_url_and_dir . $file . ". file $this->varDir.$file is emty");
                    $this->set_import_error_reporting_message("Can't copy " . $file_url_and_dir . $file . ". file " . $this->varDir . $file . " is emty");
                    $this->addImportStatus('Sinch import stoped. Import file(s) empty', 1);
                    throw new Exception("Import file(s) empty");
                } else {
                    if ($file == FILE_CATEGORIES_FEATURES) {
                        $this->_LOG("Can't copy " . FILE_CATEGORIES_FEATURES . " file ignored");
                        $this->_ignore_category_features = true;
                    } elseif ($file == FILE_PRODUCT_FEATURES) {
                        $this->_LOG("Can't copy " . FILE_PRODUCT_FEATURES . " file ignored");
                        $this->_ignore_product_features = true;
                    } elseif ($file == FILE_RELATED_PRODUCTS) {
                        $this->_LOG("Can't copy " . FILE_RELATED_PRODUCTS . " file ignored");
                        $this->_ignore_product_related = true;
                    } elseif ($file == FILE_RESTRICTED_VALUES) {
                        $this->_LOG("Can't copy " . FILE_RESTRICTED_VALUES . " file ignored");
                        $this->_ignore_restricted_values = true;
                    } elseif ($file == FILE_PRODUCT_CATEGORIES) {
                        $this->_LOG("Can't copy " . FILE_PRODUCT_CATEGORIES . " file ignored");
                        $this->_ignore_product_categories = true;
                        $this->product_file_format = "OLD";
                    } elseif ($file == FILE_CATEGORY_TYPES) {
                        $this->_LOG("Can't copy " . FILE_CATEGORY_TYPES . " file ignored");
                        $this->_ignore_category_types = true;
                    } elseif ($file == FILE_DISTRIBUTORS_STOCK_AND_PRICES) {
                        $this->_LOG("Can't copy " . FILE_DISTRIBUTORS_STOCK_AND_PRICES . " file ignored");
                        $this->_ignore_category_types = true;
                    } elseif ($file == FILE_PRODUCT_CONTRACTS) {
                        $this->_LOG("Can't copy " . FILE_PRODUCT_CONTRACTS . " file ignored");
                        $this->_ignore_product_contracts = true;
                    } elseif ($file == FILE_PRICE_RULES) {
                        $this->_LOG("Can't copy " . FILE_PRICE_RULES . " file ignored");
                        $this->_ignore_price_rules = true;
                    }

                }
            }
        }
        if (file_exists($file_url_and_dir . FILE_PRODUCT_CATEGORIES)) {
            $this->product_file_format = "NEW";
            $this->_LOG("File " . $file_url_and_dir . FILE_PRODUCT_CATEGORIES . " exist. Will used parser for NEW format product.csv");
        } else {
            $this->product_file_format = "OLD";
            $this->_LOG("File " . $file_url_and_dir . FILE_PRODUCT_CATEGORIES . " dosen't exist. Will used parser for OLD format product.csv");
        }
        $this->_LOG("Finish upload files");
    }

    private function repl_ph($content, $hash)
    {
        if ($hash) {
            foreach ($hash as $key => $val) {
                if ($key == "category_name") {
                    if (strlen($val) > 25) {
                        $val = substr($val, 0, 24) . "...";
                    }
                }
                $content = preg_replace("/%%%$key%%%/", $val, $content);
            }
        }
        return $content;
    }

    function wget()
    {

        $got = func_num_args();
        $url = $file = $flag = false;

        if ($got < 1) {
            return false;
        } elseif ($got == 1) {
            $url = func_get_arg(0);
        } elseif ($got == 2) {
            $url = func_get_arg(0);
            $file = func_get_arg(1);
        } elseif ($got == 3) {
            $url = func_get_arg(0);
            $file = func_get_arg(1);
            $flag = func_get_arg(2);
        }

        if ($flag == 'copy') {
            if (copy($url, $file)) {
                return true;
            } else {
                return false;
            }
        } elseif ($flag == 'system') {
            exec("wget -O$file $url");
            return true;
        } else {
            $c = curl_init($url);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($c, CURLOPT_HEADER, array("Accept-Encoding: gzip"));
            if (!$file) {
                $page = curl_exec($c);
                curl_close($c);
                return $page;
            } else {
                $FH = fopen($file, "wb");
                fwrite($FH, curl_exec($c));
                fclose($FH);
                curl_close($c);
                return true;
            }
        }
    }

    function ParseCategoryTypes()
    {
        $parse_file = $this->varDir . FILE_CATEGORY_TYPES;
        if (filesize($parse_file)) {
            $this->_LOG("Start parse " . FILE_CATEGORY_TYPES);

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('category_types_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('category_types_temp') . "(
                          id int(11),
                          name varchar(255),
                          key(id)
                          )");

            $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                          INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('category_types_temp') . "
                          FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                          OPTIONALLY ENCLOSED BY '\"'
                          LINES TERMINATED BY \"\r\n\"
                          IGNORE 1 LINES ");

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_category_types'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('category_types_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_category_types'));

            $this->_LOG("Finish parse " . FILE_CATEGORY_TYPES);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(' ');

    }

    function ParseCategories()
    {

        $dataConf = Mage::getStoreConfig('sinchimport_root/sinch_ftp');
        $im_type = $dataConf['replace_category'];
        $parse_file = $this->varDir . FILE_CATEGORIES;
        //$parse_file = $this->varDir . FILE_CATEGORIES_TEST;
        $field_terminated_char = $this->field_terminated_char;

        $this->im_type = $im_type;

        if (filesize($parse_file)) {
            $this->_LOG("Start parse " . FILE_CATEGORIES);

            $this->_getCategoryEntityTypeIdAndDefault_attribute_set_id();

            $categories_temp = Mage::getSingleton('core/resource')->getTableName('categories_temp');
            $catalog_category_entity = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity');
            $catalog_category_entity_varchar = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity_varchar');
            $catalog_category_entity_int = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity_int');
            $stINch_categories_mapping_temp = Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping_temp');
            $stINch_categories_mapping = Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping');
            $stINch_categories = Mage::getSingleton('core/resource')->getTableName('stINch_categories');
            $category_types = Mage::getSingleton('core/resource')->getTableName('stINch_category_types');

            $_categoryEntityTypeId = $this->_categoryEntityTypeId;
            $_categoryDefault_attribute_set_id = $this->_categoryDefault_attribute_set_id;

            $name_attrid = $this->_getCategoryAttributeId('name');
            $is_anchor_attrid = $this->_getCategoryAttributeId('is_anchor');
            $image_attrid = $this->_getCategoryAttributeId('image');

            $attr_url_key = $this->attributes['url_key'];
            $attr_display_mode = $this->attributes['display_mode'];
            $attr_is_active = $this->attributes['is_active'];
            $attr_include_in_menu = $this->attributes['include_in_menu'];

            $this->loadCategoriesTemp($categories_temp, $parse_file, $field_terminated_char);
            $coincidence = $this->calculateCategoryCoincidence($categories_temp, $catalog_category_entity, $catalog_category_entity_varchar, $im_type, $category_types);

            if (!$this->check_loaded_data($parse_file, $categories_temp)) {
                $inf = mysqli_info();
                $this->set_import_error_reporting_message('The Stock In The Channel data files do not appear to be in the correct format. Check file' . $parse_file . "(LOAD DATA ... " . $inf . ")");
                throw new Exception("Data files in invalid format");
            }

            Mage::log("\n\ncoincidence = [" . count($coincidence) . "]\n\n", null, "importstatus.log");

            if (count($coincidence) == 1) // one store logic
            {
                Mage::log("\n\n\n====================================\nSINGLE STORE\n====================================\n\n\n", null, "importstatus.log");
                if ($im_type == "REWRITE") {
                    $root_cat = 2;

                    $root_cat = $this->truncateAllCateriesAndRecreateDefaults($root_cat, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                        $_categoryEntityTypeId, $_categoryDefault_attribute_set_id,
                        $name_attrid, $attr_url_key, $attr_display_mode, $attr_is_active, $attr_include_in_menu);
                } else {
                    $root_cat = $this->_getShopRootCategoryId();
                }

                $this->_root_cat = $root_cat;

                $this->setCategorySettings($categories_temp, $root_cat);
                $this->mapSinchCategories($stINch_categories_mapping, $catalog_category_entity, $categories_temp, $im_type, $root_cat);
                $this->addCategoryData($categories_temp, $stINch_categories_mapping, $stINch_categories, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                    $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $name_attrid, $attr_is_active, $attr_include_in_menu, $is_anchor_attrid, $image_attrid, $im_type, $root_cat);
            } else if (count($coincidence) > 1) // multistore logic
            {
                Mage::log("\n\n\n====================================\nMULTIPESTORE\n====================================\n\n\n", null, "importstatus.log");
                switch ($im_type) {
                    case "REWRITE":
                        $this->rewriteMultistoreCategories($coincidence, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                            $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $im_type,
                            $name_attrid, $attr_display_mode, $attr_url_key, $attr_include_in_menu, $attr_is_active, $image_attrid, $is_anchor_attrid,
                            $stINch_categories_mapping_temp, $stINch_categories_mapping, $stINch_categories, $categories_temp);
                        break;
                    case "MERGE"  :
                        $this->mergeMultistoreCategories($coincidence, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                            $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $im_type,
                            $name_attrid, $attr_display_mode, $attr_url_key, $attr_include_in_menu, $attr_is_active, $image_attrid, $is_anchor_attrid,
                            $stINch_categories_mapping_temp, $stINch_categories_mapping, $stINch_categories, $categories_temp);
                        break;
                    default       :
                        //do nothing
                };
            } else {
                Mage::log("error", null, "importstatus.log");
            }

            $this->_LOG("Finish parse " . FILE_CATEGORIES);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(' ');
        $this->_set_default_root_category();
        return $coincidence;
    }

    private function _getCategoryEntityTypeIdAndDefault_attribute_set_id()
    {
        if (!$this->_categoryEntityTypeId || !$this->_categoryDefault_attribute_set_id) {
            $sql = "
                    SELECT entity_type_id, default_attribute_set_id
                    FROM " . Mage::getSingleton('core/resource')->getTableName('eav_entity_type') . "
                    WHERE entity_type_code = 'catalog_category'
                    LIMIT 1
                   ";
            $result = $this->db_do($sql);
            if ($row = mysqli_fetch_assoc($result)) {
                $this->_categoryEntityTypeId = $row['entity_type_id'];
                $this->_categoryDefault_attribute_set_id = $row['default_attribute_set_id'];
            }
        }
    }

    private function loadCategoriesTemp($categories_temp, $parse_file, $field_terminated_char)
    {
        $this->db_do("DROP TABLE IF EXISTS $categories_temp");

        $this->db_do("
            CREATE TABLE $categories_temp
                (
                    store_category_id              INT(11),
                    parent_store_category_id       INT(11),
                    category_name                  VARCHAR(50),
                    order_number                   INT(11),
                    is_hidden                      VARCHAR(10),
                    products_within_sub_categories INT(11),
                    products_within_this_category  INT(11),
                    categories_image               VARCHAR(255),
                    level                          INT(10) NOT NULL DEFAULT 0,
                    children_count                 INT(11) NOT NULL DEFAULT 0,
                    UNSPSC                         INT(10) DEFAULT NULL,
                    RootName                       INT(10) DEFAULT NULL,
                    MainImageURL                   VARCHAR(255),
                    MetaTitle                      TEXT,
                    MetaDescription                TEXT,
                    Description                    TEXT,
                    KEY(store_category_id),
                    KEY(parent_store_category_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db_do("
            LOAD DATA LOCAL INFILE '$parse_file' INTO TABLE $categories_temp
            FIELDS TERMINATED BY '$field_terminated_char' OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY \"\r\n\" IGNORE 1 LINES");

        $this->db_do("ALTER TABLE $categories_temp ADD COLUMN include_in_menu TINYINT(1) NOT NULL DEFAULT 1");
        $this->db_do("UPDATE $categories_temp SET include_in_menu = 0 WHERE UCASE(is_hidden)='TRUE'");

        $this->db_do("ALTER TABLE $categories_temp ADD COLUMN is_anchor TINYINT(1) NOT NULL DEFAULT 1");
        $this->db_do("UPDATE $categories_temp SET level = (level+2) WHERE level >= 0");
    }

    private function calculateCategoryCoincidence($categories_temp, $catalog_category_entity, $catalog_category_entity_varchar, $im_type, $category_types)
    {
        $root_categories = $this->db_do("
            SELECT
                cce.entity_id,
                ccev.value AS category_name
            FROM $catalog_category_entity cce
            JOIN $catalog_category_entity_varchar ccev
                ON cce.entity_id = ccev.entity_id
                AND ccev.store_id = 0
                AND cce.entity_type_id = ccev.entity_type_id
                AND ccev.attribute_id = 41
            WHERE parent_id = 1"); // 41 - category name
        $OLD = array();
        while ($root_cat = mysqli_fetch_array($root_categories)) $OLD[] = $root_cat['category_name'];

        $new_categories = $this->db_do("SELECT DISTINCT RootName FROM $categories_temp");

        while ($new_root_cat = mysqli_fetch_array($new_categories)) $exists_coincidence[$new_root_cat['RootName']] = TRUE;

        Mage::log("\ncalculateCategoryCoincidence ...im_type = [$im_type]\n\n", null, "importstatus.log");

        return $exists_coincidence;
    }

    function check_loaded_data($file, $table)
    {
        $cnt_strings_in_file = $this->file_strings_count($file);
        $cnt_rows_int_table = $this->table_rows_count($table);
        $persent_cnt_strings_in_file = $cnt_strings_in_file / 10;
        if ($cnt_rows_int_table > $persent_cnt_strings_in_file) {
            return true;
        } else {
            return false;
        }
    }

    function file_strings_count($parse_file)
    {
        $files_str = count(file($parse_file));
        return $files_str;
    }

    function table_rows_count($table)
    {
        $rows_count_res = $this->db_do("select count(*) as cnt from " . $table);
        $rows_count = mysqli_fetch_array($rows_count_res);
        return ($rows_count['cnt']);
    }

    private function truncateAllCateriesAndRecreateDefaults($root_cat, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                                                            $_categoryEntityTypeId, $_categoryDefault_attribute_set_id,
                                                            $name_attrid, $attr_url_key, $attr_display_mode, $attr_is_active, $attr_include_in_menu)
    {
        $this->db_do('SET foreign_key_checks=0');

        $this->db_do("TRUNCATE $catalog_category_entity");
        $this->db_do("
                    INSERT $catalog_category_entity
                        (
                            entity_id,
                            entity_type_id,
                            attribute_set_id,
                            parent_id,
                            created_at,
                            updated_at,
                            path,
                            position,
                            level,
                            children_count,
                            store_category_id,
                            parent_store_category_id
                        )
                    VALUES
                                (1, $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, 0, '0000-00-00 00:00:00', now(), '1', 0, 0, 1, null, null),
                                (2, $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, 1, now(), now(), '1/2', 1, 1, 1, null, null)");

        $this->db_do("TRUNCATE $catalog_category_entity_varchar");
        $this->db_do("
                    INSERT $catalog_category_entity_varchar
                        (
                            value_id,
                            entity_type_id,
                            attribute_id,
                            store_id,
                            entity_id,
                            value
                        )
                    VALUES
                        (1, $_categoryEntityTypeId, $name_attrid, 0, 1, 'Root Catalog'),
                        (2, $_categoryEntityTypeId, $name_attrid, 1, 1, 'Root Catalog'),
                        (3, $_categoryEntityTypeId, $attr_url_key, 0, 1, 'root-catalog'),
                        (4, $_categoryEntityTypeId, $name_attrid, 0, 2, 'Default Category'),
                        (5, $_categoryEntityTypeId, $name_attrid, 1, 2, 'Default Category'),
                        (6, $_categoryEntityTypeId, $attr_display_mode, 1, 2, 'PRODUCTS'),
                        (7, $_categoryEntityTypeId, $attr_url_key, 0, 2, 'default-category')");

        $this->db_do("TRUNCATE $catalog_category_entity_int");
        $this->db_do("
                    INSERT $catalog_category_entity_int
                        (
                            value_id,
                            entity_type_id,
                            attribute_id,
                            store_id,
                            entity_id,
                            value
                        )
                    VALUES
                        (1, $_categoryEntityTypeId, $attr_is_active, 0, 2, 1),
                        (2, $_categoryEntityTypeId, $attr_is_active, 1, 2, 1),
                        (3, $_categoryEntityTypeId, $attr_include_in_menu, 0, 1, 1),
                        (4, $_categoryEntityTypeId, $attr_include_in_menu, 0, 2, 1)");

        return $root_cat;
    }

    private function _getShopRootCategoryId($cat_id = 0)
    {
        if ($root_cat = Mage::app()->getStore()->getRootCategoryId()) {
            return $root_cat;
        } else {
            $q = "SELECT
                entity_id
            FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity_varchar') . "
            WHERE
                value='default-category'";
            $res = $this->db_do($q);
            $row = mysqli_fetch_array($res);
            if ($row['entity_id'] > 0) {
                return $row['entity_id'];
            } else {
                $q = "SELECT entity_id
                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . "
                WHERE parent_id=" . $cat_id;
                $quer = $this->db_do($q);
                $count = 0;
                while ($row = mysqli_fetch_array($quer)) {
                    $count++;
                    $entity_id = $row['entity_id'];
                }
                if ($count > 1 || $count == 0) {
                    return ($cat_id);
                } else {
                    return $this->_getShopRootCategoryId($entity_id);
                }
            }
        }
    }

    private function setCategorySettings($categories_temp, $root_cat)
    {
        $this->db_do("
            UPDATE $categories_temp
            SET parent_store_category_id = $root_cat
            WHERE parent_store_category_id = 0");

        $store_cat_ids = $this->db_do("SELECT store_category_id FROM $categories_temp");
        while ($row = mysqli_fetch_array($store_cat_ids)) {
            $store_category_id = $row['store_category_id'];

            $children_count = $this->count_children($store_category_id);
            $level = $this->get_category_level($store_category_id);

            $this->db_do("
                UPDATE $categories_temp
                SET children_count = $children_count,
                    level = $level
                WHERE store_category_id = $store_category_id");
        }
    }

    function count_children($id)
    {

        $q = "SELECT store_category_id
            FROM " . Mage::getSingleton('core/resource')->getTableName('categories_temp') . "
            WHERE parent_store_category_id=" . $id;
        $quer = $this->db_do($q);
        $count = 0;
        while ($row = mysqli_fetch_array($quer)) {
            $count += $this->count_children($row['store_category_id']);
            $count++;
        }
        return ($count);
    }

    function get_category_level($id)
    {
        $q = "SELECT parent_store_category_id
            FROM " . Mage::getSingleton('core/resource')->getTableName('categories_temp') . "
            WHERE store_category_id=" . $id;
        $quer = $this->db_do($q);
        $level = 1;
        $row = mysqli_fetch_array($quer);
        while ($row['parent_store_category_id'] != 0) {
            $q = "SELECT parent_store_category_id
                FROM " . Mage::getSingleton('core/resource')->getTableName('categories_temp') . "
                WHERE store_category_id=" . $row['parent_store_category_id'];
            $quer = $this->db_do($q);
            $row = mysqli_fetch_array($quer);
            $level++;
            if ($level > 20) {
                break;
            }
        }

        return ($level);
    }

    public function mapSinchCategories($stINch_categories_mapping, $catalog_category_entity, $categories_temp, $im_type, $root_cat, $mapping_again = false)
    {
        $stINch_categories_mapping_temp = Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping_temp');

        $this->db_do("DROP TABLE IF EXISTS $stINch_categories_mapping_temp");

        $this->db_do("
            CREATE TABLE $stINch_categories_mapping_temp
                (
                    shop_entity_id                INT(11) UNSIGNED NOT NULL,
                    shop_entity_type_id           INT(11),
                    shop_attribute_set_id         INT(11),
                    shop_parent_id                INT(11),
                    shop_store_category_id        INT(11),
                    shop_parent_store_category_id INT(11),
                    store_category_id             INT(11),
                    parent_store_category_id      INT(11),
                    category_name                 VARCHAR(255),
                    order_number                  INT(11),
                    products_within_this_category INT(11),

                    KEY shop_entity_id (shop_entity_id),
                    KEY shop_parent_id (shop_parent_id),
                    KEY store_category_id (store_category_id),
                    KEY parent_store_category_id (parent_store_category_id),
                    UNIQUE KEY(shop_entity_id)
                )");

        $this->db_do("CREATE TABLE IF NOT EXISTS $stINch_categories_mapping LIKE $stINch_categories_mapping_temp");

        // added for mapping new sinch categories in merge && !UPDATE_CATEGORY_DATA mode
        if ((UPDATE_CATEGORY_DATA && $im_type == "MERGE") || ($im_type == "REWRITE")) {
            // backup Category ID in REWRITE mode
            if ($mapping_again) {
                $this->db_do("
                    INSERT IGNORE INTO $stINch_categories_mapping_temp
                        (
                            shop_entity_id,
                            shop_entity_type_id,
                            shop_attribute_set_id,
                            shop_parent_id,
                            shop_store_category_id,
                            shop_parent_store_category_id
                        )
                    (SELECT
                        entity_id,
                        entity_type_id,
                        attribute_set_id,
                        parent_id,
                        store_category_id,
                        parent_store_category_id
                    FROM $catalog_category_entity)");

                $this->db_do("
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $categories_temp c
                        ON cmt.shop_store_category_id = c.store_category_id
                    SET
                        cmt.store_category_id             = c.store_category_id,
                        cmt.parent_store_category_id      = c.parent_store_category_id,
                        cmt.category_name                 = c.category_name,
                        cmt.order_number                  = c.order_number,
                        cmt.products_within_this_category = c.products_within_this_category");

                $this->db_do("
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $catalog_category_entity cce
                        ON cmt.parent_store_category_id = cce.store_category_id
                    SET cmt.shop_parent_id = cce.entity_id");

                $this->db_do("
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $categories_temp c
                        ON cmt.shop_store_category_id = c.store_category_id
                    SET shop_parent_id = " . $this->_root_cat . "
                    WHERE shop_parent_id = 0");

                $this->db_do("
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $catalog_category_entity cce
                        ON cmt.shop_entity_id = cce.entity_id
                    SET cce.parent_id = cmt.shop_parent_id");
            } else {
                $catalog_category_entity_backup = Mage::getSingleton('core/resource')->getTableName('sinch_category_backup');
                if (!$this->_checkDataExist($catalog_category_entity_backup)) {
                    $catalog_category_entity_backup = $catalog_category_entity;
                }

                $this->db_do("
                    INSERT IGNORE INTO $stINch_categories_mapping_temp
                        (
                            shop_entity_id,
                            shop_entity_type_id,
                            shop_attribute_set_id,
                            shop_parent_id,
                            shop_store_category_id,
                            shop_parent_store_category_id
                        )
                    (SELECT
                        entity_id,
                        entity_type_id,
                        attribute_set_id,
                        parent_id,
                        store_category_id,
                        parent_store_category_id
                    FROM $catalog_category_entity_backup)");

                $this->db_do("
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $categories_temp c
                        ON cmt.shop_store_category_id = c.store_category_id
                    SET
                        cmt.store_category_id             = c.store_category_id,
                        cmt.parent_store_category_id      = c.parent_store_category_id,
                        cmt.category_name                 = c.category_name,
                        cmt.order_number                  = c.order_number,
                        cmt.products_within_this_category = c.products_within_this_category");

                $this->db_do("
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $catalog_category_entity_backup cce
                        ON cmt.parent_store_category_id = cce.store_category_id
                    SET cmt.shop_parent_id = cce.entity_id");

                $this->db_do("
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $categories_temp c
                        ON cmt.shop_store_category_id = c.store_category_id
                    SET shop_parent_id = " . $this->_root_cat . "
                    WHERE shop_parent_id = 0");

                $this->db_do("
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $catalog_category_entity cce
                        ON cmt.shop_entity_id = cce.entity_id
                    SET cce.parent_id = cmt.shop_parent_id");
            }
            // (end) backup Category ID in REWRITE mode
        } else {
            $this->db_do("
                INSERT IGNORE INTO $stINch_categories_mapping_temp
                    (
                        shop_entity_id,
                        shop_entity_type_id,
                        shop_attribute_set_id,
                        shop_parent_id,
                        shop_store_category_id,
                        shop_parent_store_category_id
                    )
                (SELECT
                    entity_id,
                    entity_type_id,
                    attribute_set_id,
                    parent_id,
                    store_category_id,
                    parent_store_category_id
                FROM $catalog_category_entity)");

            $this->db_do("
                UPDATE $stINch_categories_mapping_temp cmt
                JOIN $categories_temp c
                    ON cmt.shop_store_category_id = c.store_category_id
                SET
                    cmt.store_category_id             = c.store_category_id,
                    cmt.parent_store_category_id      = c.parent_store_category_id,
                    cmt.category_name                 = c.category_name,
                    cmt.order_number                  = c.order_number,
                    cmt.products_within_this_category = c.products_within_this_category");

            $this->db_do("
                UPDATE $stINch_categories_mapping_temp cmt
                JOIN $catalog_category_entity cce
                    ON cmt.parent_store_category_id = cce.store_category_id
                SET cmt.shop_parent_id = cce.entity_id");

            $this->db_do("
                UPDATE $stINch_categories_mapping_temp cmt
                JOIN $categories_temp c
                    ON cmt.shop_store_category_id = c.store_category_id
                SET shop_parent_id = " . $this->_root_cat . "
                WHERE shop_parent_id = 0");

            $this->db_do("
                UPDATE $stINch_categories_mapping_temp cmt
                JOIN $catalog_category_entity cce
                    ON cmt.shop_entity_id = cce.entity_id
                SET cce.parent_id = cmt.shop_parent_id
                WHERE cce.parent_id = 0 AND cce.store_category_id IS NOT NULL");
        }
        $this->_LOG("Execute function mapSinchCategories");
        $this->db_do("DROP TABLE IF EXISTS $stINch_categories_mapping");
        $this->db_do("RENAME TABLE $stINch_categories_mapping_temp TO $stINch_categories_mapping");
    }

    private function addCategoryData($categories_temp, $stINch_categories_mapping, $stINch_categories, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                                     $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $name_attrid, $attr_is_active, $attr_include_in_menu, $is_anchor_attrid, $image_attrid, $im_type, $root_cat)
    {
        if (UPDATE_CATEGORY_DATA) {
            Mage::log("Update category_entity \n", null, "importstatus.log");

            $q = "
                INSERT INTO $catalog_category_entity
                    (
                        entity_type_id,
                        attribute_set_id,
                        created_at,
                        updated_at,
                        level,
                        children_count,
                        entity_id,
                        position,
                        parent_id,
                        store_category_id,
                        parent_store_category_id
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $_categoryDefault_attribute_set_id,
                    now(),
                    now(),
                    c.level,
                    c.children_count,
                    scm.shop_entity_id,
                    c.order_number,
                    scm.shop_parent_id,
                    c.store_category_id,
                    c.parent_store_category_id
                FROM $categories_temp c
                LEFT JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    updated_at = now(),
                    store_category_id = c.store_category_id,
                    level = c.level,
                    children_count = c.children_count,
                    position = c.order_number,
                    parent_store_category_id = c.parent_store_category_id";
        } else {
            Mage::log("Insert ignore category_entity \n", null, "importstatus.log");

            $q = "
                INSERT IGNORE INTO $catalog_category_entity
                    (
                        entity_type_id,
                        attribute_set_id,
                        created_at,
                        updated_at,
                        level,
                        children_count,
                        entity_id,
                        position,
                        parent_id,
                        store_category_id,
                        parent_store_category_id
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $_categoryDefault_attribute_set_id,
                    now(),
                    now(),
                    c.level,
                    c.children_count,
                    scm.shop_entity_id,
                    c.order_number,
                    scm.shop_parent_id,
                    c.store_category_id,
                    c.parent_store_category_id
                    FROM $categories_temp c
                    LEFT JOIN $stINch_categories_mapping scm
                        ON c.store_category_id = scm.store_category_id
                )";
        }
        $this->db_do($q);

        $this->mapSinchCategories($stINch_categories_mapping, $catalog_category_entity, $categories_temp, $im_type, $root_cat, true);


        $categories = $this->db_do("SELECT entity_id, parent_id FROM $catalog_category_entity ORDER BY parent_id");
        while ($row = mysqli_fetch_array($categories)) {
            $parent_id = $row['parent_id'];
            $entity_id = $row['entity_id'];

            $path = $this->culc_path($parent_id, $entity_id);

            $this->db_do("
                UPDATE $catalog_category_entity
                             SET path = '$path'
                             WHERE entity_id = $entity_id");
        }

        if (UPDATE_CATEGORY_DATA) {
            Mage::log("Update category_data \n", null, "importstatus.log");

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                            $name_attrid,
                    0,
                    scm.shop_entity_id,
                    c.category_name
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.category_name";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $name_attrid,
                    1,
                    scm.shop_entity_id,
                    c.category_name
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.category_name";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_is_active,
                    0,
                    scm.shop_entity_id,
                    1
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = 1";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_is_active,
                    1,
                    scm.shop_entity_id,
                    1
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = 1";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_include_in_menu,
                    0,
                    scm.shop_entity_id,
                    c.include_in_menu
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.include_in_menu";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $is_anchor_attrid,
                    1,
                    scm.shop_entity_id,
                    c.is_anchor
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.is_anchor";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $is_anchor_attrid,
                    0,
                    scm.shop_entity_id,
                    c.is_anchor
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.is_anchor";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $image_attrid,
                    0,
                    scm.shop_entity_id,
                    c.categories_image
                    FROM $categories_temp c
                    JOIN $stINch_categories_mapping scm
                        ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.categories_image";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetaTitleAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaTitle
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                     value = c.MetaTitle";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetadescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaDescription
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                     value = c.MetaDescription";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryDescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.Description
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                     value = c.Description";
            $this->db_do($q);
        } else {
            Mage::log("Insert ignore category_data \n", null, "importstatus.log");

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                            $name_attrid,
                    0,
                    scm.shop_entity_id,
                    c.category_name
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_is_active,
                    0,
                    scm.shop_entity_id,
                    1
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_include_in_menu,
                    0,
                    scm.shop_entity_id,
                    c.include_in_menu
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $is_anchor_attrid,
                    0,
                    scm.shop_entity_id,
                    c.is_anchor
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $image_attrid,
                    0,
                    scm.shop_entity_id,
                    c.categories_image
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);
            //STP
            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetaTitleAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaTitle
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
               ";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetadescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaDescription
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
            ";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryDescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.Description
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
            ";
            $this->db_do($q);
        }

        // Ayman : Keep old sinch categories
        // $this->delete_old_sinch_categories_from_shop();

        $this->db_do("DROP TABLE IF EXISTS $stINch_categories");
        $this->db_do("RENAME TABLE $categories_temp TO $stINch_categories");
    }

    function culc_path($parent_id, $ent_id)
    {
        $path = '';
        $cat_id = $parent_id;
        $q = "SELECT
                parent_id
            FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . "
            WHERE entity_id=" . $cat_id;
        $quer = $this->db_do($q);
        $row = mysqli_fetch_array($quer);
        while ($row['parent_id']) {
            $path = $row['parent_id'] . '/' . $path;
            $q = "SELECT
                    parent_id
                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . "
                WHERE entity_id=" . $row['parent_id'];
            $quer = $this->db_do($q);
            $row = mysqli_fetch_array($quer);

        }
        if ($cat_id) {
            $path .= $cat_id . "/";
        }

        if ($path) {
            return ($path . $ent_id);
        } else {
            return ($ent_id);
        }
    }

    private function delete_old_sinch_categories_from_shop()
    {
        $q = "DELETE cat FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity_varchar') . " cat
            JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping') . " scm
                ON cat.entity_id=scm.shop_entity_id
            WHERE
                (scm.shop_store_category_id is not null) AND
                (scm.store_category_id is null)";
        $this->db_do($q);

        $q = "DELETE cat FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity_int') . " cat
            JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping') . " scm
                ON cat.entity_id=scm.shop_entity_id
            WHERE
                (scm.shop_store_category_id is not null) AND
                (scm.store_category_id is null)";
        $this->db_do($q);

        $q = "DELETE cat FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . " cat
            JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping') . " scm
                ON cat.entity_id=scm.shop_entity_id
            WHERE
                (scm.shop_store_category_id is not null) AND
                (scm.store_category_id is null)";
        $this->db_do($q);
    }


    private function rewriteMultistoreCategories($coincidence, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                                                 $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $im_type,
                                                 $name_attrid, $attr_display_mode, $attr_url_key, $attr_include_in_menu, $attr_is_active, $image_attrid, $is_anchor_attrid,
                                                 $stINch_categories_mapping_temp, $stINch_categories_mapping, $stINch_categories, $categories_temp)
    {
        Mage::log("rewriteMultistoreCategories RUN\n", null, "importstatus.log");

        Mage::log("    truncateAllCateriesAndCreateRoot start...", null, "importstatus.log");
        $this->truncateAllCateriesAndCreateRoot($catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
            $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $name_attrid, $attr_display_mode, $attr_url_key, $attr_include_in_menu, $attr_is_active);
        Mage::log(" done.\n", null, "importstatus.log");

        Mage::log("    createDefaultCategories start...", null, "importstatus.log");
        $this->createDefaultCategories($coincidence, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
            $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $name_attrid, $attr_display_mode, $attr_url_key, $attr_is_active, $attr_include_in_menu);
        Mage::log(" done.\n", null, "importstatus.log");

        Mage::log("    mapSinchCategoriesMultistore start...", null, "importstatus.log");
        $this->mapSinchCategoriesMultistore($stINch_categories_mapping_temp, $stINch_categories_mapping, $catalog_category_entity, $catalog_category_entity_varchar, $categories_temp, $im_type, $_categoryEntityTypeId, $name_attrid);
        Mage::log(" done.\n", null, "importstatus.log");

        Mage::log("    addCategoryDataMultistore start...", null, "importstatus.log");
        $this->addCategoryDataMultistore($categories_temp, $stINch_categories_mapping_temp, $stINch_categories_mapping, $stINch_categories, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
            $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $im_type,
            $name_attrid, $attr_is_active, $attr_include_in_menu, $is_anchor_attrid, $image_attrid);
        Mage::log(" done.\n", null, "importstatus.log");

        Mage::log("rewriteMultistoreCategories DONE\n", null, "importstatus.log");
    }


    private function truncateAllCateriesAndCreateRoot($catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                                                      $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $name_attrid, $attr_display_mode, $attr_url_key, $attr_include_in_menu, $attr_is_active)
    {
        $this->db_do('SET foreign_key_checks=0');


        $this->db_do("TRUNCATE $catalog_category_entity");
        $this->db_do("TRUNCATE $catalog_category_entity_varchar");
        $this->db_do("TRUNCATE $catalog_category_entity_int");


        $this->db_do("INSERT $catalog_category_entity
                    (entity_id, entity_type_id, attribute_set_id, parent_id, created_at, updated_at,
                    path, position, level, children_count, store_category_id, parent_store_category_id)
                VALUES
                    (1, $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, 0, '0000-00-00 00:00:00', NOW(), '1', 0, 0, 1, NULL, NULL)");


        $this->db_do("INSERT $catalog_category_entity_varchar
                    (value_id, entity_type_id, attribute_id, store_id, entity_id, value)
                VALUES
                    (1, $_categoryEntityTypeId, $name_attrid, 0, 1, 'Root Catalog'),
                    (2, $_categoryEntityTypeId, $name_attrid, 1, 1, 'Root Catalog'),
                    (3, $_categoryEntityTypeId, $attr_url_key, 0, 1, 'root-catalog')");


        $this->db_do("INSERT $catalog_category_entity_int
                    (value_id, entity_type_id, attribute_id, store_id, entity_id, value)
                VALUES
                    (1, $_categoryEntityTypeId, $attr_include_in_menu, 0, 1, 1)");
    }

    private function createDefaultCategories($coincidence, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                                             $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $name_attrid, $attr_display_mode, $attr_url_key, $attr_is_active, $attr_include_in_menu)
    {
        $i = 3;

        foreach ($coincidence as $key => $item) {
            $this->db_do("INSERT $catalog_category_entity
                        (entity_id, entity_type_id, attribute_set_id, parent_id, created_at, updated_at,
                        path, position, level, children_count, store_category_id, parent_store_category_id)
                    VALUES
                        ($i, $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, 1, now(), now(), '1/$i', 1, 1, 1, NULL, NULL)");


            $this->db_do("INSERT $catalog_category_entity_varchar
                        (entity_type_id, attribute_id, store_id, entity_id, value)
                    VALUES
                        ($_categoryEntityTypeId, $name_attrid,       0, $i, '$key'),
                        ($_categoryEntityTypeId, $name_attrid,       1, $i, '$key'),
                        ($_categoryEntityTypeId, $attr_display_mode, 1, $i, '$key'),
                        ($_categoryEntityTypeId, $attr_url_key,      0, $i, '$key')");


            $this->db_do("INSERT $catalog_category_entity_int
                        (entity_type_id, attribute_id, store_id, entity_id, value)
                    VALUES
                        ($_categoryEntityTypeId, $attr_is_active,       0, $i, 1),
                        ($_categoryEntityTypeId, $attr_is_active,       1, $i, 1),
                        ($_categoryEntityTypeId, $attr_include_in_menu, 0, $i, 1),
                        ($_categoryEntityTypeId, $attr_include_in_menu, 1, $i, 1)");
            $i++;
        }
    }

    private function mapSinchCategoriesMultistore($stINch_categories_mapping_temp, $stINch_categories_mapping, $catalog_category_entity, $catalog_category_entity_varchar, $categories_temp, $im_type, $_categoryEntityTypeId, $name_attrid, $mapping_again = false)
    {
        Mage::log("\n\n\n\n==========================================================================\nmapSinchCategoriesMultistore start... \n", null, "importstatus.log");

        $this->createMappingSinchTables($stINch_categories_mapping_temp, $stINch_categories_mapping);

        // backup Category ID in REWRITE mode
        if ($im_type == "REWRITE" || (UPDATE_CATEGORY_DATA && $im_type == "MERGE")) {
            if ($mapping_again) {
                $query = "
                    INSERT IGNORE INTO $stINch_categories_mapping_temp
                        (
                            shop_entity_id,
                            shop_entity_type_id,
                            shop_attribute_set_id,
                            shop_parent_id,
                            shop_store_category_id,
                            shop_parent_store_category_id
                        )
                    (SELECT
                        entity_id,
                        entity_type_id,
                        attribute_set_id,
                        parent_id,
                        store_category_id,
                        parent_store_category_id
                    FROM $catalog_category_entity)";
                $this->db_do($query);


                $query = "
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $categories_temp c
                        ON cmt.shop_store_category_id = c.store_category_id
                    SET
                        cmt.store_category_id             = c.store_category_id,
                        cmt.parent_store_category_id      = c.parent_store_category_id,
                        cmt.category_name                 = c.category_name,
                        cmt.order_number                  = c.order_number,
                        cmt.products_within_this_category = c.products_within_this_category";
                $this->db_do($query);


                $query = "
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $catalog_category_entity cce
                        ON cmt.parent_store_category_id = cce.store_category_id
                    SET cmt.shop_parent_id = cce.entity_id";
                $this->db_do($query);


                $query = "
                    SELECT DISTINCT
                        c.RootName, cce.entity_id
                    FROM $categories_temp c
                    JOIN $catalog_category_entity_varchar ccev
                        ON c.RootName = ccev.value
                        AND ccev.entity_type_id = $_categoryEntityTypeId
                        AND ccev.attribute_id = $name_attrid
                        AND ccev.store_id = 0
                    JOIN $catalog_category_entity cce
                        ON ccev.entity_id = cce.entity_id";
                $root_categories = $this->db_do($query);

                while ($root_cat = mysqli_fetch_array($root_categories)) {
                    $root_id = $root_cat['entity_id'];
                    $root_name = $root_cat['RootName'];

                    $query = "
                        UPDATE $stINch_categories_mapping_temp cmt
                        JOIN $categories_temp c
                            ON cmt.shop_store_category_id = c.store_category_id
                        SET
                            cmt.shop_parent_id = $root_id,
                            cmt.shop_parent_store_category_id = $root_id,
                            cmt.parent_store_category_id = $root_id,
                            c.parent_store_category_id = $root_id
                        WHERE RootName = '$root_name'
                            AND cmt.shop_parent_id = 0";
                    $this->db_do($query);
                }
            } else {
                $catalog_category_entity_backup = Mage::getSingleton('core/resource')->getTableName('sinch_category_backup');
                if (!$this->_checkDataExist($catalog_category_entity_backup)) {
                    $catalog_category_entity_backup = $catalog_category_entity;
                }
                $query = "
                    INSERT IGNORE INTO $stINch_categories_mapping_temp
                        (
                            shop_entity_id,
                            shop_entity_type_id,
                            shop_attribute_set_id,
                            shop_parent_id,
                            shop_store_category_id,
                            shop_parent_store_category_id
                        )
                    (SELECT
                        entity_id,
                        entity_type_id,
                        attribute_set_id,
                        parent_id,
                        store_category_id,
                        parent_store_category_id
                    FROM $catalog_category_entity_backup)";
                $this->db_do($query);


                $query = "
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $categories_temp c
                        ON cmt.shop_store_category_id = c.store_category_id
                    SET
                        cmt.store_category_id             = c.store_category_id,
                        cmt.parent_store_category_id      = c.parent_store_category_id,
                        cmt.category_name                 = c.category_name,
                        cmt.order_number                  = c.order_number,
                        cmt.products_within_this_category = c.products_within_this_category";
                $this->db_do($query);


                $query = "
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $catalog_category_entity_backup cce
                        ON cmt.parent_store_category_id = cce.store_category_id
                    SET cmt.shop_parent_id = cce.entity_id";
                $this->db_do($query);


                $query = "
                    SELECT DISTINCT
                        c.RootName, cce.entity_id
                    FROM $categories_temp c
                    JOIN $catalog_category_entity_varchar ccev
                        ON c.RootName = ccev.value
                        AND ccev.entity_type_id = $_categoryEntityTypeId
                        AND ccev.attribute_id = $name_attrid
                        AND ccev.store_id = 0
                    JOIN $catalog_category_entity_backup cce
                        ON ccev.entity_id = cce.entity_id";
                $root_categories = $this->db_do($query);

                while ($root_cat = mysqli_fetch_array($root_categories)) {
                    $root_id = $root_cat['entity_id'];
                    $root_name = $root_cat['RootName'];

                    $query = "
                        UPDATE $stINch_categories_mapping_temp cmt
                        JOIN $categories_temp c
                            ON cmt.shop_store_category_id = c.store_category_id
                        SET
                            cmt.shop_parent_id = $root_id,
                            cmt.shop_parent_store_category_id = $root_id,
                            cmt.parent_store_category_id = $root_id,
                            c.parent_store_category_id = $root_id
                        WHERE RootName = '$root_name'
                            AND cmt.shop_parent_id = 0";
                    $this->db_do($query);
                }
            }
            // (end) backup Category ID in REWRITE mode
        } else {
            $query = "
                INSERT IGNORE INTO $stINch_categories_mapping_temp
                    (shop_entity_id, shop_entity_type_id, shop_attribute_set_id, shop_parent_id, shop_store_category_id, shop_parent_store_category_id)
                (SELECT entity_id, entity_type_id, attribute_set_id, parent_id, store_category_id, parent_store_category_id
                FROM $catalog_category_entity)";
            $this->db_do($query);

            $query = "
                UPDATE $stINch_categories_mapping_temp cmt
                JOIN $categories_temp c
                    ON cmt.shop_store_category_id = c.store_category_id
                SET
                    cmt.store_category_id             = c.store_category_id,
                    cmt.parent_store_category_id      = c.parent_store_category_id,
                    cmt.category_name                 = c.category_name,
                    cmt.order_number                  = c.order_number,
                    cmt.products_within_this_category = c.products_within_this_category";
            $this->db_do($query);


            $query = "
                UPDATE $stINch_categories_mapping_temp cmt
                JOIN $catalog_category_entity cce
                    ON cmt.parent_store_category_id = cce.store_category_id
                SET cmt.shop_parent_id = cce.entity_id";
            $this->db_do($query);


            $query = "
                SELECT DISTINCT
                    c.RootName, cce.entity_id
                FROM $categories_temp c
                JOIN $catalog_category_entity_varchar ccev
                    ON c.RootName = ccev.value
                    AND ccev.entity_type_id = $_categoryEntityTypeId
                    AND ccev.attribute_id = $name_attrid
                    AND ccev.store_id = 0
                JOIN $catalog_category_entity cce
                    ON ccev.entity_id = cce.entity_id";
            $root_categories = $this->db_do($query);

            while ($root_cat = mysqli_fetch_array($root_categories)) {
                $root_id = $root_cat['entity_id'];
                $root_name = $root_cat['RootName'];

                $query = "
                    UPDATE $stINch_categories_mapping_temp cmt
                    JOIN $categories_temp c
                        ON cmt.shop_store_category_id = c.store_category_id
                    SET
                        cmt.shop_parent_id = $root_id,
                        cmt.shop_parent_store_category_id = $root_id,
                        cmt.parent_store_category_id = $root_id,
                        c.parent_store_category_id = $root_id
                    WHERE RootName = '$root_name'
                        AND cmt.shop_parent_id = 0";
                $this->db_do($query);
            }
        }

        // added for mapping new sinch categories in merge && !UPDATE_CATEGORY_DATA mode
        if ((UPDATE_CATEGORY_DATA && $im_type == "MERGE") || ($im_type == "REWRITE")) $where = '';
        else $where = 'WHERE cce.parent_id = 0 AND cce.store_category_id IS NOT NULL';

        $query = "
            UPDATE $stINch_categories_mapping_temp cmt
            JOIN $catalog_category_entity cce
                ON cmt.shop_entity_id = cce.entity_id
            SET cce.parent_id = cmt.shop_parent_id
            $where";
        $this->db_do($query);
        $this->_LOG("Execute function mapSinchCategoriesMultistore");
        $query = "DROP TABLE IF EXISTS $stINch_categories_mapping";
        $this->db_do($query);

        $query = "RENAME TABLE $stINch_categories_mapping_temp TO $stINch_categories_mapping";
        $this->db_do($query);

        Mage::log("\nmapSinchCategoriesMultistore done... \n==========================================================================\n\n\n\n", null, "importstatus.log");
    }

    private function createMappingSinchTables($stINch_categories_mapping_temp, $stINch_categories_mapping)
    {
        $this->db_do("DROP TABLE IF EXISTS $stINch_categories_mapping_temp");
        $this->db_do("
            CREATE TABLE $stINch_categories_mapping_temp
                (
                    shop_entity_id                INT(11) UNSIGNED NOT NULL,
                    shop_entity_type_id           INT(11),
                    shop_attribute_set_id         INT(11),
                    shop_parent_id                INT(11),
                    shop_store_category_id        INT(11),
                    shop_parent_store_category_id INT(11),
                    store_category_id             INT(11),
                    parent_store_category_id      INT(11),
                    category_name                 VARCHAR(255),
                    order_number                  INT(11),
                    products_within_this_category INT(11),

                    KEY shop_entity_id (shop_entity_id),
                    KEY shop_parent_id (shop_parent_id),
                    KEY store_category_id (store_category_id),
                    KEY parent_store_category_id (parent_store_category_id),
                    UNIQUE KEY(shop_entity_id)
                )");


        $this->db_do("CREATE TABLE IF NOT EXISTS $stINch_categories_mapping LIKE $stINch_categories_mapping_temp");
    }

    private function addCategoryDataMultistore($categories_temp, $stINch_categories_mapping_temp, $stINch_categories_mapping, $stINch_categories, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                                               $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $im_type,
                                               $name_attrid, $attr_is_active, $attr_include_in_menu, $is_anchor_attrid, $image_attrid)
    {
        Mage::log("\n\n\n\n*************************************************************\nmapSinchCategoriesMultistore start... \n", null, "importstatus.log");
        if (UPDATE_CATEGORY_DATA) {
            $ignore = '';
            $on_diplicate_key_update = "
                ON DUPLICATE KEY UPDATE
                    updated_at = now(),
                    store_category_id = c.store_category_id,
                    level = c.level,
                    children_count = c.children_count,
                    position = c.order_number,
                    parent_store_category_id = c.parent_store_category_id";
        } else {
            $ignore = 'IGNORE';
            $on_diplicate_key_update = '';
        }

        $query = "
            INSERT $ignore INTO $catalog_category_entity
                (
                    entity_type_id,
                    attribute_set_id,
                    created_at,
                    updated_at,
                    level,
                    children_count,
                    entity_id,
                    position,
                    parent_id,
                    store_category_id,
                    parent_store_category_id
                )
            (SELECT
                $_categoryEntityTypeId,
                $_categoryDefault_attribute_set_id,
                NOW(),
                NOW(),
                c.level,
                c.children_count,
                scm.shop_entity_id,
                c.order_number,
                scm.shop_parent_id,
                c.store_category_id,
                c.parent_store_category_id
                FROM $categories_temp c
                LEFT JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
            ) $on_diplicate_key_update";
        $this->db_do($query);

        $this->mapSinchCategoriesMultistore($stINch_categories_mapping_temp, $stINch_categories_mapping, $catalog_category_entity, $catalog_category_entity_varchar, $categories_temp, $im_type, $_categoryEntityTypeId, $name_attrid, true);

        $categories = $this->db_do("SELECT entity_id, parent_id FROM $catalog_category_entity ORDER BY parent_id");
        while ($row = mysqli_fetch_array($categories)) {
            $parent_id = $row['parent_id'];
            $entity_id = $row['entity_id'];

            $path = $this->culcPathMultistore($parent_id, $entity_id, $catalog_category_entity);

            $this->db_do("
                UPDATE $catalog_category_entity
                SET path = '$path'
                WHERE entity_id = $entity_id");
        }

        if (UPDATE_CATEGORY_DATA) {
            Mage::log("Update category_data \n", null, "importstatus.log");

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                            $name_attrid,
                    0,
                    scm.shop_entity_id,
                    c.category_name
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.category_name";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $name_attrid,
                    1,
                    scm.shop_entity_id,
                    c.category_name
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.category_name";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_is_active,
                    0,
                    scm.shop_entity_id,
                    1
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = 1";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_is_active,
                    1,
                    scm.shop_entity_id,
                    1
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = 1";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_include_in_menu,
                    0,
                    scm.shop_entity_id,
                    c.include_in_menu
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.include_in_menu";
            $this->db_do($q);


            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $is_anchor_attrid,
                    1,
                    scm.shop_entity_id,
                    c.is_anchor
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.is_anchor";
            $this->db_do($q);


            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $is_anchor_attrid,
                    0,
                    scm.shop_entity_id,
                    c.is_anchor
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.is_anchor";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $image_attrid,
                    0,
                    scm.shop_entity_id,
                    c.categories_image
                    FROM $categories_temp c
                    JOIN $stINch_categories_mapping scm
                        ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.categories_image";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetaTitleAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaTitle
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                     value = c.MetaTitle";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetadescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaDescription
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                     value = c.MetaDescription";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryDescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.Description
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                     value = c.Description";
            $this->db_do($q);

        } else {
            Mage::log("Insert ignore category_data \n", null, "importstatus.log");

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                            $name_attrid,
                    0,
                    scm.shop_entity_id,
                    c.category_name
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);


            $q = "
                INSERT IGNORE INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_is_active,
                    0,
                    scm.shop_entity_id,
                    1
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);


            $q = "
                INSERT IGNORE INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_include_in_menu,
                    0,
                    scm.shop_entity_id,
                    c.include_in_menu
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);


            $q = "
                INSERT IGNORE INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $is_anchor_attrid,
                    0,
                    scm.shop_entity_id,
                    c.is_anchor
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $image_attrid,
                    0,
                    scm.shop_entity_id,
                    c.categories_image
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetaTitleAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaTitle
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
               ";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetadescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaDescription
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
            ";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryDescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.Description
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
            ";
            $this->db_do($q);

        }

        // Ayman : Keep old sinch categories
        // $this->delete_old_sinch_categories_from_shop();
        $this->db_do("DROP TABLE IF EXISTS $stINch_categories\n\n");
        $this->db_do("RENAME TABLE $categories_temp TO $stINch_categories");
    }

    function culcPathMultistore($parent_id, $ent_id, $catalog_category_entity)
    {
        $path = '';
        $cat_id = $parent_id;

        $q = "
            SELECT
                parent_id
            FROM $catalog_category_entity
            WHERE entity_id = $cat_id";
        $quer = $this->db_do($q);
        $row = mysqli_fetch_array($quer);
        while ($row['parent_id']) {
            $path = $row['parent_id'] . '/' . $path;
            $parent_id = $row['parent_id'];

            $q = "
                SELECT
                    parent_id
                FROM $catalog_category_entity
                WHERE entity_id = $parent_id";
            $quer = $this->db_do($q);
            $row = mysqli_fetch_array($quer);
        }

        if ($cat_id) $path .= $cat_id . "/";

        if ($path) $path .= $ent_id;
        else $path = $ent_id;

        return $path;
    }

    private function mergeMultistoreCategories($coincidence, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                                               $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $im_type,
                                               $name_attrid, $attr_display_mode, $attr_url_key, $attr_include_in_menu, $attr_is_active, $image_attrid, $is_anchor_attrid,
                                               $stINch_categories_mapping_temp, $stINch_categories_mapping, $stINch_categories, $categories_temp)
    {
        Mage::log("mergeMultistoreCategories RUN\n", null, "importstatus.log");

        $this->createNewDefaultCategories($coincidence, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
            $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $name_attrid, $attr_display_mode, $attr_url_key, $attr_is_active, $attr_include_in_menu);

        $this->mapSinchCategoriesMultistoreMerge($stINch_categories_mapping_temp, $stINch_categories_mapping, $catalog_category_entity, $catalog_category_entity_varchar, $categories_temp, $im_type, $_categoryEntityTypeId, $name_attrid);

        $this->addCategoryDataMultistoreMerge($categories_temp, $stINch_categories_mapping_temp, $stINch_categories_mapping, $stINch_categories, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
            $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $im_type,
            $name_attrid, $attr_is_active, $attr_include_in_menu, $is_anchor_attrid, $image_attrid);

        Mage::log("\n\n\nmergeMultistoreCategories DONE\n", null, "importstatus.log");
    }

    private function createNewDefaultCategories($coincidence, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                                                $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $name_attrid, $attr_display_mode, $attr_url_key, $attr_is_active, $attr_include_in_menu)
    {
        Mage::log("\n\n    ==========================================================================\n    createNewDefaultCategories start... \n", null, "importstatus.log");

        $old_cats = array();
        $query = $this->db_do("
            SELECT
                cce.entity_id,
                ccev.value AS category_name
            FROM $catalog_category_entity cce
            JOIN $catalog_category_entity_varchar ccev
                ON cce.entity_id = ccev.entity_id
                AND ccev.store_id = 0
                AND cce.entity_type_id = ccev.entity_type_id
                AND ccev.attribute_id = 41
            WHERE parent_id = 1"); // 41 - category name
        while ($row = mysqli_fetch_array($query)) $old_cats[] = $row['category_name'];

        $query = $this->db_do("SELECT MAX(entity_id) AS max_entity_id FROM $catalog_category_entity");
        $max_entity_id = mysqli_fetch_array($query);

        $i = $max_entity_id[max_entity_id] + 1;

        foreach ($coincidence as $key => $item) {
            Mage::log("\n    coincidence: key = [$key]\n", null, "importstatus.log");

            if (in_array($key, $old_cats)) {
                Mage::log("    CONTINUE: key = [$key]   item = [$item]\n", null, "importstatus.log");
                continue;
            } else {
                Mage::log("    CREATE NEW CATEGORY: key = [$key]   item = [$item]\n", null, "importstatus.log");
            }

            $this->db_do("INSERT $catalog_category_entity
                        (entity_id, entity_type_id, attribute_set_id, parent_id, created_at, updated_at,
                        path, position, level, children_count, store_category_id, parent_store_category_id)
                    VALUES
                        ($i, $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, 1, now(), now(), '1/$i', 1, 1, 1, NULL, NULL)");

            $this->db_do("INSERT $catalog_category_entity_varchar
                        (entity_type_id, attribute_id, store_id, entity_id, value)
                    VALUES
                        ($_categoryEntityTypeId, $name_attrid,       0, $i, '$key'),
                        ($_categoryEntityTypeId, $name_attrid,       1, $i, '$key'),
                        ($_categoryEntityTypeId, $attr_display_mode, 1, $i, '$key'),
                        ($_categoryEntityTypeId, $attr_url_key,      0, $i, '$key')");


            $this->db_do("INSERT $catalog_category_entity_int
                        (entity_type_id, attribute_id, store_id, entity_id, value)
                    VALUES
                        ($_categoryEntityTypeId, $attr_is_active,       0, $i, 1),
                        ($_categoryEntityTypeId, $attr_is_active,       1, $i, 1),
                        ($_categoryEntityTypeId, $attr_include_in_menu, 0, $i, 1),
                        ($_categoryEntityTypeId, $attr_include_in_menu, 1, $i, 1)");
            $i++;
        }

        Mage::log("\n    createNewDefaultCategories done... \n    ==========================================================================\n", null, "importstatus.log");
    }

    private function mapSinchCategoriesMultistoreMerge($stINch_categories_mapping_temp, $stINch_categories_mapping, $catalog_category_entity, $catalog_category_entity_varchar, $categories_temp, $im_type, $_categoryEntityTypeId, $name_attrid)
    {
        Mage::log("\n\n\    ==========================================================================\n    mapSinchCategoriesMultistore start... \n", null, "importstatus.log");

        $this->createMappingSinchTables($stINch_categories_mapping_temp, $stINch_categories_mapping);

        $query = "
            INSERT IGNORE INTO $stINch_categories_mapping_temp
                (shop_entity_id, shop_entity_type_id, shop_attribute_set_id, shop_parent_id, shop_store_category_id, shop_parent_store_category_id)
            (SELECT entity_id, entity_type_id, attribute_set_id, parent_id, store_category_id, parent_store_category_id
            FROM $catalog_category_entity)";
        $this->db_do($query);

        $query = "
            UPDATE $stINch_categories_mapping_temp cmt
            JOIN $categories_temp c
                ON cmt.shop_store_category_id = c.store_category_id
            SET
                cmt.store_category_id             = c.store_category_id,
                cmt.parent_store_category_id      = c.parent_store_category_id,
                cmt.category_name                 = c.category_name,
                cmt.order_number                  = c.order_number,
                cmt.products_within_this_category = c.products_within_this_category";
        $this->db_do($query);

        $query = "
            UPDATE $stINch_categories_mapping_temp cmt
            JOIN $catalog_category_entity cce
                ON cmt.parent_store_category_id = cce.store_category_id
            SET cmt.shop_parent_id = cce.entity_id";
        $this->db_do($query);

        $query = "
            SELECT DISTINCT
                c.RootName, cce.entity_id
            FROM $categories_temp c
            JOIN $catalog_category_entity_varchar ccev
                ON c.RootName = ccev.value
                AND ccev.entity_type_id = $_categoryEntityTypeId
                AND ccev.attribute_id = $name_attrid
                AND ccev.store_id = 0
            JOIN $catalog_category_entity cce
                ON ccev.entity_id = cce.entity_id";
        $root_categories = $this->db_do($query);

        while ($root_cat = mysqli_fetch_array($root_categories)) {
            $root_id = $root_cat['entity_id'];
            $root_name = $root_cat['RootName'];

            $query = "
                UPDATE $stINch_categories_mapping_temp cmt
                JOIN $categories_temp c
                    ON cmt.shop_store_category_id = c.store_category_id
                SET
                    cmt.shop_parent_id = $root_id,
                    cmt.shop_parent_store_category_id = $root_id,
                    cmt.parent_store_category_id = $root_id,
                    c.parent_store_category_id = $root_id
                WHERE RootName = '$root_name'
                    AND cmt.shop_parent_id = 0";
            $this->db_do($query);
        }

        // added for mapping new sinch categories in merge && !UPDATE_CATEGORY_DATA mode
        if ((UPDATE_CATEGORY_DATA && $im_type == "MERGE") || ($im_type == "REWRITE")) $where = '';
        else $where = 'WHERE cce.parent_id = 0 AND cce.store_category_id IS NOT NULL';

        $query = "
            UPDATE $stINch_categories_mapping_temp cmt
            JOIN $catalog_category_entity cce
                ON cmt.shop_entity_id = cce.entity_id
            SET cce.parent_id = cmt.shop_parent_id
            $where";
        $this->db_do($query);

        $query = "DROP TABLE IF EXISTS $stINch_categories_mapping";
        $this->db_do($query);

        $query = "RENAME TABLE $stINch_categories_mapping_temp TO $stINch_categories_mapping";
        $this->db_do($query);

        Mage::log("\n    mapSinchCategoriesMultistore done... \n    ==========================================================================\n\n\n\n", null, "importstatus.log");
    }


    private function addCategoryDataMultistoreMerge($categories_temp, $stINch_categories_mapping_temp, $stINch_categories_mapping, $stINch_categories, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int,
                                                    $_categoryEntityTypeId, $_categoryDefault_attribute_set_id, $im_type,
                                                    $name_attrid, $attr_is_active, $attr_include_in_menu, $is_anchor_attrid, $image_attrid)
    {
        Mage::log("\n\n\n\n    *************************************************************\n    addCategoryDataMultistoreMerge start... \n", null, "importstatus.log");

        if (UPDATE_CATEGORY_DATA) {
            $ignore = '';
            $on_diplicate_key_update = "
                ON DUPLICATE KEY UPDATE
                    updated_at = now(),
                    store_category_id = c.store_category_id,
                    level = c.level,
                    children_count = c.children_count,
                    position = c.order_number,
                    parent_store_category_id = c.parent_store_category_id";
        } else {
            $ignore = 'IGNORE';
            $on_diplicate_key_update = '';
        }

        $query = "
            INSERT $ignore INTO $catalog_category_entity
                (
                    entity_type_id,
                    attribute_set_id,
                    created_at,
                    updated_at,
                    level,
                    children_count,
                    entity_id,
                    position,
                    parent_id,
                    store_category_id,
                    parent_store_category_id
                )
            (SELECT
                $_categoryEntityTypeId,
                $_categoryDefault_attribute_set_id,
                NOW(),
                NOW(),
                c.level,
                c.children_count,
                scm.shop_entity_id,
                c.order_number,
                scm.shop_parent_id,
                c.store_category_id,
                c.parent_store_category_id
                FROM $categories_temp c
                LEFT JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
            ) $on_diplicate_key_update";
        $this->db_do($query);

        $this->mapSinchCategoriesMultistoreMerge($stINch_categories_mapping_temp, $stINch_categories_mapping, $catalog_category_entity, $catalog_category_entity_varchar, $categories_temp, $im_type, $_categoryEntityTypeId, $name_attrid);

        $categories = $this->db_do("SELECT entity_id, parent_id FROM $catalog_category_entity ORDER BY parent_id");
        while ($row = mysqli_fetch_array($categories)) {
            $parent_id = $row['parent_id'];
            $entity_id = $row['entity_id'];

            $path = $this->culcPathMultistore($parent_id, $entity_id, $catalog_category_entity);

            $this->db_do("
                UPDATE $catalog_category_entity
                SET path = '$path'
                WHERE entity_id = $entity_id");
        }

        if (UPDATE_CATEGORY_DATA) {
            Mage::log("Update category_data \n", null, "importstatus.log");

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                            $name_attrid,
                    0,
                    scm.shop_entity_id,
                    c.category_name
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.category_name";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $name_attrid,
                    1,
                    scm.shop_entity_id,
                    c.category_name
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.category_name";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_is_active,
                    0,
                    scm.shop_entity_id,
                    1
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = 1";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_is_active,
                    1,
                    scm.shop_entity_id,
                    1
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = 1";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_include_in_menu,
                    0,
                    scm.shop_entity_id,
                    c.include_in_menu
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.include_in_menu";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $is_anchor_attrid,
                    1,
                    scm.shop_entity_id,
                    c.is_anchor
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.is_anchor";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $is_anchor_attrid,
                    0,
                    scm.shop_entity_id,
                    c.is_anchor
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.is_anchor";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $image_attrid,
                    0,
                    scm.shop_entity_id,
                    c.categories_image
                    FROM $categories_temp c
                    JOIN $stINch_categories_mapping scm
                        ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                    value = c.categories_image";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetaTitleAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaTitle
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                     value = c.MetaTitle";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetadescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaDescription
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                     value = c.MetaDescription";
            $this->db_do($q);

            $q = "
                INSERT INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryDescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.Description
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
                ON DUPLICATE KEY UPDATE
                     value = c.Description";
            $this->db_do($q);
        } else {
            Mage::log("Insert ignore category_data \n", null, "importstatus.log");

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                            $name_attrid,
                    0,
                    scm.shop_entity_id,
                    c.category_name
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_is_active,
                    0,
                    scm.shop_entity_id,
                    1
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $attr_include_in_menu,
                    0,
                    scm.shop_entity_id,
                    c.include_in_menu
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_int
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $is_anchor_attrid,
                    0,
                    scm.shop_entity_id,
                    c.is_anchor
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                        entity_type_id,
                        attribute_id,
                        store_id,
                        entity_id,
                        value
                    )
                (SELECT
                    $_categoryEntityTypeId,
                    $image_attrid,
                    0,
                    scm.shop_entity_id,
                    c.categories_image
                FROM $categories_temp c
                JOIN $stINch_categories_mapping scm
                    ON c.store_category_id = scm.store_category_id
                )";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetaTitleAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaTitle
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
               ";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryMetadescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.MetaDescription
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
            ";
            $this->db_do($q);

            $q = "
                INSERT IGNORE INTO $catalog_category_entity_varchar
                    (
                     entity_type_id,
                     attribute_id,
                     store_id,
                     entity_id,
                     value
                    )
                (SELECT
                     $this->_categoryEntityTypeId,
                     $this->_categoryDescriptionAttrId,
                     0,
                     scm.shop_entity_id,
                     c.Description
                 FROM $categories_temp c
                 JOIN $stINch_categories_mapping scm
                     ON c.store_category_id = scm.store_category_id
                )
            ";
            $this->db_do($q);
        }

        $this->db_do("DROP TABLE IF EXISTS $stINch_categories\n\n");
        $this->db_do("RENAME TABLE $categories_temp TO $stINch_categories");

        $this->deleteOldSinchCategoriesFromShopMerge($stINch_categories_mapping, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int);

        Mage::log("\n    addCategoryDataMultistoreMerge done... \n    *************************************************************\n", null, "importstatus.log");
    }

    private function deleteOldSinchCategoriesFromShopMerge($stINch_categories_mapping, $catalog_category_entity, $catalog_category_entity_varchar, $catalog_category_entity_int)
    {
        Mage::log("\n\n\n\n    +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n    deleteOldSinchCategoriesFromShopMerge start... \n", null, "importstatus.log");

        $query = "DROP TABLE IF EXISTS delete_cats";
        $this->db_do($query);

        $delete_cats = Mage::getSingleton('core/resource')->getTableName('delete_cats');
        $stINch_categories = Mage::getSingleton('core/resource')->getTableName('stINch_categories');

        $query = "
CREATE TABLE $delete_cats

SELECT entity_id
FROM $catalog_category_entity cce
WHERE cce.entity_id NOT IN
    (
    SELECT cce2.entity_id
    FROM $catalog_category_entity cce2
    JOIN $stINch_categories sc
        ON cce2.store_category_id = sc.store_category_id
    )
    AND cce.store_category_id IS NOT NULL
;";
        $this->db_do($query);


        $query = "DELETE cce FROM $catalog_category_entity cce JOIN $delete_cats dc USING(entity_id)";
        $this->db_do($query);

        $query = "DROP TABLE IF EXISTS $delete_cats";
        $this->db_do($query);
        Mage::log("\n    deleteOldSinchCategoriesFromShopMerge done... \n    +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n\n\n", null, "importstatus.log");
    }

    private function _set_default_root_category()
    {
        $q = "UPDATE " . Mage::getSingleton('core/resource')->getTableName('core_store_group') . " csg
            LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . " cce
            ON csg.root_category_id = cce.entity_id
            SET csg.root_category_id=(SELECT entity_id FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . " WHERE parent_id = 1 LIMIT 1)
            WHERE csg.root_category_id > 0 AND cce.entity_id IS NULL";
        $this->db_do($q);
    }

    function ParseCategoryFeatures()
    {

        $parse_file = $this->varDir . FILE_CATEGORIES_FEATURES;
        if (filesize($parse_file) || $this->_ignore_category_features) {
            $this->_LOG("Start parse " . FILE_CATEGORIES_FEATURES);
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('categories_features_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('categories_features_temp') . " (
                                category_feature_id int(11),
                                store_category_id int(11),
                                feature_name varchar(50),
                                display_order_number int(11),
                                KEY(store_category_id),
                                KEY(category_feature_id)
                          )
                        ");

            if (!$this->_ignore_category_features) {
                $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                              INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('categories_features_temp') . "
                              FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                              OPTIONALLY ENCLOSED BY '\"'
                              LINES TERMINATED BY \"\r\n\"
                              IGNORE 1 LINES ");
            }
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_categories_features'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('categories_features_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_categories_features'));

            $this->_LOG("Finish parse " . FILE_CATEGORIES_FEATURES);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(' ');
    }

    function ParseDistributors()
    {
        $parse_file = $this->varDir . FILE_DISTRIBUTORS;
        if (filesize($parse_file)) {
            $this->_LOG("Start parse " . FILE_DISTRIBUTORS);
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('distributors_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('distributors_temp') . "(
                              distributor_id int(11),
                              distributor_name varchar(255),
                              website varchar(255),
                              KEY(distributor_id)
                          )
                        ");

            $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                          INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('distributors_temp') . "
                          FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                          OPTIONALLY ENCLOSED BY '\"'
                          LINES TERMINATED BY \"\r\n\"
                          IGNORE 1 LINES ");

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('distributors_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors'));

            $this->_LOG("Finish parse " . FILE_DISTRIBUTORS);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(' ');
    }

    function ParseDistributorsStockAndPrice()
    {
        $parse_file = $this->varDir . FILE_DISTRIBUTORS_STOCK_AND_PRICES;
        if (filesize($parse_file)) {
            $this->_LOG("Start parse " . FILE_DISTRIBUTORS_STOCK_AND_PRICES);

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('distributors_stock_and_price_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('distributors_stock_and_price_temp') . "(
                          `store_product_id` int(11) DEFAULT NULL,
                          `distributor_id` int(11) DEFAULT NULL,
                          `stock` int(11) DEFAULT NULL,
                          `cost` decimal(15,4) DEFAULT NULL,
                          `distributor_sku` varchar(255) DEFAULT NULL,
                          `distributor_category` varchar(50) DEFAULT NULL,
                          `eta` varchar(50) DEFAULT NULL,
                          UNIQUE KEY `product_distri` (store_product_id, distributor_id)
                          )");

            $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                          INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('distributors_stock_and_price_temp') . "
                          FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                          OPTIONALLY ENCLOSED BY '\"'
                          LINES TERMINATED BY \"\r\n\"
                          IGNORE 1 LINES ");

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('distributors_stock_and_price_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price'));

            $this->_LOG("Finish parse " . FILE_DISTRIBUTORS_STOCK_AND_PRICES);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(' ');
    }

    function ParseProductContracts()
    {
        $parse_file = $this->varDir . FILE_PRODUCT_CONTRACTS;
        if (filesize($parse_file)) {
            $this->_LOG("Start parse " . FILE_PRODUCT_CONTRACTS);

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('product_contracts_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('product_contracts_temp') . "(
                          `store_product_id` int(11) DEFAULT NULL,
                          `contract_id` varchar(50) DEFAULT NULL,
                          KEY `store_product_id` (store_product_id)
                          )");

            $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                          INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('product_contracts_temp') . "
                          FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                          OPTIONALLY ENCLOSED BY '\"'
                          LINES TERMINATED BY \"\r\n\"
                          IGNORE 1 LINES ");

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_product_contracts'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('product_contracts_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_product_contracts'));

            $this->_LOG("Finish parse " . FILE_PRODUCT_CONTRACTS);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(' ');
    }

    function ParseEANCodes()
    {

        $parse_file = $this->varDir . FILE_EANCODES;
        if (filesize($parse_file)) {
            $this->_LOG("Start parse " . FILE_EANCODES);

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('ean_codes_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('ean_codes_temp') . "(
                           product_id int(11),
                           ean_code varchar(255),
                           KEY(product_id)
                          )");

            $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                          INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('ean_codes_temp') . "
                          FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                          OPTIONALLY ENCLOSED BY '\"'
                          LINES TERMINATED BY \"\r\n\"
                          IGNORE 1 LINES ");

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_ean_codes'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('ean_codes_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_ean_codes'));

            $this->_LOG("Finish parse " . FILE_EANCODES);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(' ');
    }

    function ParseManufacturers()
    {
        $parse_file = $this->varDir . FILE_MANUFACTURERS;
        if (filesize($parse_file)) {
            $this->_LOG("Start parse " . FILE_MANUFACTURERS);
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('manufacturers_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('manufacturers_temp') . "(
                                      sinch_manufacturer_id int(11),
                                      manufacturer_name varchar(255),
                                      manufacturers_image varchar(255),
                                      shop_option_id int(11),
                                      KEY(sinch_manufacturer_id),
                                      KEY(shop_option_id),
                                      KEY(manufacturer_name)
                          )");

            $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                          INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('manufacturers_temp') . "
                          FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                          OPTIONALLY ENCLOSED BY '\"'
                          LINES TERMINATED BY \"\r\n\"
                          IGNORE 1 LINES ");

            # Moe - Uche : Original code commented out
            # Reason: to prevent deleting our manufacturer values from the manufacturer attribute
            // $q = "DELETE aov
            //     FROM " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option') . " ao
            //     JOIN " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option_value') . " aov
            //         ON ao.option_id=aov.option_id left
            //     JOIN " . Mage::getSingleton('core/resource')->getTableName('manufacturers_temp') . " mt
            //         ON aov.value=mt.manufacturer_name
            //     WHERE
            //         ao.attribute_id=" . $this->attributes['manufacturer'] . " AND
            //         mt.manufacturer_name is null";
            // $this->db_do($q);

            // $q = "DELETE ao
            //     FROM " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option') . " ao
            //     LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option_value') . " aov
            //         ON ao.option_id=aov.option_id
            //     WHERE
            //         attribute_id=" . $this->attributes['manufacturer'] . " AND
            //         aov.option_id is null";
            // $this->db_do($q);

            $q = "SELECT
                    m.sinch_manufacturer_id,
                    m.manufacturer_name,
                    m.manufacturers_image
                FROM " . Mage::getSingleton('core/resource')->getTableName('manufacturers_temp') . " m
                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option_value') . " aov
                    ON m.manufacturer_name=aov.value
                WHERE aov.value  IS NULL";
            $quer = $this->db_do($q);

            while ($row = mysqli_fetch_array($quer)) {
                $q0 = "INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option') . "
                        (attribute_id)
                     VALUES(" . $this->attributes['manufacturer'] . ")";
                $quer0 = $this->db_do($q0);

                $q2 = "INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option_value') . "(
                        option_id,
                        value
                     )(
                       SELECT
                        max(option_id) as option_id,
                        " . "'" . mysqli_real_escape_string($this->db, $row['manufacturer_name']) . "'
                       FROM " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option') . "
                       WHERE attribute_id=" . $this->attributes['manufacturer'] . "
                     )
                    ";
                $quer2 = $this->db_do($q2);
                //                $option['attribute_id'] = $this->attributes['manufacturer'];
                //                $option['value'][$row['sinch_manufacturer_id']][0] = $row['manufacturer_name'];
            }

            $q = "UPDATE " . Mage::getSingleton('core/resource')->getTableName('manufacturers_temp') . " mt
                JOIN  " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option_value') . " aov
                    ON mt.manufacturer_name=aov.value
                JOIN " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option') . " ao
                    ON ao.option_id=aov.option_id
                SET mt.shop_option_id=aov.option_id
                WHERE ao.attribute_id=" . $this->attributes['manufacturer'];
            $this->db_do($q);

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_manufacturers'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('manufacturers_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_manufacturers'));
            $this->_LOG("Finish parse " . FILE_MANUFACTURERS);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(' ');
    }

    function ParseRelatedProducts()
    {

        $parse_file = $this->varDir . FILE_RELATED_PRODUCTS;
        if (filesize($parse_file) || $this->_ignore_product_related) {
            $this->_LOG("Start parse " . FILE_RELATED_PRODUCTS);
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('related_products_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('related_products_temp') . "(
                                 sinch_product_id int(11),
                                 related_sinch_product_id int(11),
                                 store_product_id int(11) default null,
                                 store_related_product_id int(11) default null,
                                 entity_id int(11),
                                 related_entity_id int(11),
                                 KEY(sinch_product_id),
                                 KEY(related_sinch_product_id),
                                 KEY(store_product_id)
                                     )DEFAULT CHARSET=utf8");
            if (!$this->_ignore_product_related) {
                $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                              INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('related_products_temp') . "
                              FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                              OPTIONALLY ENCLOSED BY '\"'
                              LINES TERMINATED BY \"\r\n\"
                              IGNORE 1 LINES ");
            }
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_related_products'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('related_products_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_related_products'));

            $this->_LOG("Finish parse " . FILE_RELATED_PRODUCTS);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(" ");
    }

    function ParseProductFeatures()
    {
        $parse_file = $this->varDir . FILE_PRODUCT_FEATURES;
        if (filesize($parse_file) || $this->_ignore_product_features) {
            $this->_LOG("Start parse " . FILE_PRODUCT_FEATURES);

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('product_features_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('product_features_temp') . "(
                            product_feature_id int(11),
                            sinch_product_id int(11),
                            restricted_value_id int(11),
                            KEY(sinch_product_id),
                            KEY(restricted_value_id)
                          )
                        ");
            if (!$this->_ignore_product_features) {
                $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                              INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('product_features_temp') . "
                              FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                              OPTIONALLY ENCLOSED BY '\"'
                              LINES TERMINATED BY \"\r\n\"
                              IGNORE 1 LINES ");
            }
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_product_features'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('product_features_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_product_features'));

            $this->_LOG("Finish parse " . FILE_PRODUCT_FEATURES);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(" ");
    }

    function ParseProductCategories()
    {
        $parse_file = $this->varDir . FILE_PRODUCT_CATEGORIES;
        if (filesize($parse_file)) {
            $this->_LOG("Start parse " . FILE_PRODUCT_CATEGORIES);

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('product_categories_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('product_categories_temp') . "(
                          store_product_id int(11),
                          store_category_id int(11),
                          key(store_product_id),
                          key(store_category_id)
                          )");

            $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                          INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('product_categories_temp') . "
                          FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                          OPTIONALLY ENCLOSED BY '\"'
                          LINES TERMINATED BY \"\r\n\"
                          IGNORE 1 LINES ");

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_product_categories'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('product_categories_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_product_categories'));

            $this->_LOG("Finish parse " . FILE_PRODUCT_CATEGORIES);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(' ');
    }

    function ParseProducts($coincidence)
    {
        Mage::log("\nParseProducts 1\n", null, "importstatus.log");
        $dataConf = Mage::getStoreConfig('sinchimport_root/sinch_ftp');
        $replace_merge_product = $dataConf['replace_products'];

        $parse_file = $this->varDir . FILE_PRODUCTS;
        //$parse_file = $this->varDir . FILE_PRODUCTS_TEST;
        if (filesize($parse_file)) {
            $this->_LOG("Start parse " . FILE_PRODUCTS);
            Mage::log("\nParseProducts 2\n", null, "importstatus.log");

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('products_temp'));
            if ($this->product_file_format == "NEW") {
                $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . "(
                             store_product_id int(11),
                             product_sku varchar(255),
                             product_name varchar(255),
                             sinch_manufacturer_id int(11),
                             main_image_url varchar(255),
                             thumb_image_url varchar(255),
                             specifications text,
                             description text,
                             search_cache text,
                             description_type varchar(50),
                             medium_image_url varchar(255),
                             Title varchar(255),
                             Weight decimal(15,4),
                             Family varchar(255),
                             Reviews varchar(255),
                             pdf_url varchar(512),
                             product_short_description text,
                             products_date_added datetime default NULL,
                             products_last_modified datetime default NULL,
                             availability_id_in_stock int(11) default '1',
                             availability_id_out_of_stock int(11) default '2',
                             products_locate varchar(30) default NULL,
                             products_ordered int(11) NOT NULL default '0',
                             products_url varchar(255) default NULL,
                             products_viewed int(5) default '0',
                             products_seo_url varchar(100) NOT NULL,
                             manufacturer_name varchar(255) default NULL,
                             sinch_product_id int(11),
                             store_category_id int(11),
                             KEY(store_product_id),
                             KEY(sinch_manufacturer_id),
                             KEY(store_category_id)
                          )DEFAULT CHARSET=utf8
                        ");
            } elseif ($this->product_file_format == "OLD") {
                $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . "(
                              store_category_product_id int(11),
                              store_product_id int(11),
                              sinch_product_id int(11),
                              product_sku varchar(255),
                              product_name varchar(255),
                              sinch_manufacturer_id int(11),
                              store_category_id int(11),
                              main_image_url varchar(255),
                              thumb_image_url varchar(255),
                              specifications text,
                              description text,
                              search_cache text,
                              spec_characte_u_count int(11),
                              description_type varchar(50),
                              medium_image_url varchar(255),
                              products_date_added datetime default NULL,
                              products_last_modified datetime default NULL,
                              availability_id_in_stock int(11) default '1',
                              availability_id_out_of_stock int(11) default '2',
                              products_locate varchar(30) default NULL,
                              products_ordered int(11) NOT NULL default '0',
                              products_url varchar(255) default NULL,
                              products_viewed int(5) default '0',
                              products_seo_url varchar(100) NOT NULL,
                              manufacturer_name varchar(255) default NULL,
                              KEY(store_category_product_id),
                              KEY(store_product_id),
                              KEY(sinch_manufacturer_id),
                              KEY(store_category_id)
                           )DEFAULT CHARSET=utf8
                         ");

            }
            Mage::log("\nParseProducts 3\n", null, "importstatus.log");
            $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                          INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . "
                          FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                          OPTIONALLY ENCLOSED BY '\"'
                          LINES TERMINATED BY \"\r\n\"
                          IGNORE 1 LINES ");

            if ($this->product_file_format == "NEW") {
                $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . "
                          SET sinch_product_id=store_product_id
                         ");
                $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . "
                          SET product_name = Title WHERE Title != ''
                        ");
                $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " pt
                    JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_product_categories') . " spc
                    SET pt.store_category_id=spc.store_category_id
                    WHERE pt.store_product_id=spc.store_product_id
                    ");
                $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . "
                          SET main_image_url = medium_image_url WHERE main_image_url = ''
                         ");
            }

            Mage::log("\nParseProducts 4\n", null, "importstatus.log");

            Mage::log("\nParseProducts 5\n", null, "importstatus.log");
            $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . "
                          SET products_date_added=now(), products_last_modified=now()");
            Mage::log("\nParseProducts 6\n", null, "importstatus.log");
            $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " p
                          JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_manufacturers') . " m
                            ON p.sinch_manufacturer_id=m.sinch_manufacturer_id
                          SET p.manufacturer_name=m.manufacturer_name");
            Mage::log("\nParseProducts 7\n", null, "importstatus.log");
            if ($this->current_import_status_statistic_id) {
                $res = $this->db_do("SELECT COUNT(*) AS cnt
                                     FROM " . Mage::getSingleton('core/resource')->getTableName('products_temp'));
                $row = mysqli_fetch_assoc($res);
                $this->db_do("UPDATE " . $this->import_status_statistic_table . "
                              SET number_of_products=" . $row['cnt'] . "
                              WHERE id=" . $this->current_import_status_statistic_id);
            }

            if ($replace_merge_product == "REWRITE") {
                $this->db_do("DELETE FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity'));

                $this->db_do("SET FOREIGN_KEY_CHECKS=0");
                $this->db_do("TRUNCATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity'));
                $this->db_do("TRUNCATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar'));
                $this->db_do("TRUNCATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int'));
                $this->db_do("TRUNCATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_text'));
                $this->db_do("TRUNCATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_decimal'));
                $this->db_do("SET FOREIGN_KEY_CHECKS=1");
            }

            // Modify catalog_product_entity_varchar - Kai
            $this->db_do("ALTER TABLE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " MODIFY value VARCHAR(512)");

            Mage::log("\nParseProducts 8\n", null, "importstatus.log");
            $this->addProductsWebsite();
            $this->mapSinchProducts($replace_merge_product);
            Mage::log("\nParseProducts 9\n", null, "importstatus.log");

            if (count($coincidence) == 1) {
                $this->replaceMagentoProducts();
            } else {
                Mage::log("\n\n\n\n\n\n$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$ [" . $this->im_type . "] $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$\n\n\n\n", null, "importstatus.log");


                switch ($this->im_type) {
                    case "REWRITE":
                        $this->replaceMagentoProductsMultistore($coincidence);
                        break;
                    case "MERGE":
                        $this->replaceMagentoProductsMultistoreMERGE($coincidence);
                        break;
                }
            }
            Mage::log("\nParseProducts 10\n", null, "importstatus.log");

            // Kai
            $this->mapSinchProducts($replace_merge_product, true);
            $this->addManufacturer_attribute();
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_products'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_products'));
            $this->_LOG("Finish parse " . FILE_PRODUCTS);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(" ");
        Mage::log("\nParseProducts 11\n", null, "importstatus.log");
    }


    public function addProductsWebsite()
    {
        $this->db_do(" DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('products_website_temp'));

        $this->db_do("
                CREATE TABLE `" . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . "` (
                    `id` int(10) unsigned NOT NULL auto_increment,
                    store_product_id int(11),
                    sinch_product_id int(11),
                    `website` int(11) default NULL,
                    `website_id` int(11) default NULL,
                    PRIMARY KEY  (`id`),
                    KEY store_product_id (`store_product_id`)
                )
                ");
        $result = $this->db_do("SELECT
                                    website_id,
                                    store_id as website
                                FROM " . Mage::getSingleton('core/resource')->getTableName('core_store') . "
                                WHERE code!='admin'
                              "); //  where code!='admin' was adder for editing Featured products;
        while ($row = mysqli_fetch_assoc($result)) {
            $sql = "INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " (
                        store_product_id,
                        sinch_product_id,
                        website,
                        website_id
                    )(
                      SELECT
                        distinct
                        store_product_id,
                        sinch_product_id,
                        {$row['website']},
                        {$row['website_id']}
                      FROM " . Mage::getSingleton('core/resource')->getTableName('products_temp') . "
                    )";
            $result2 = $this->db_do($sql);
        }
    }

    public function mapSinchProducts($mode = 'MERGE', $mapping_again = false)
    {
        $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping_temp'));
        $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping_temp') . " (
                      entity_id int(11) unsigned NOT NULL,
                      manufacturer_option_id int(11),
                      manufacturer_name varchar(255),
                      shop_store_product_id int(11),
                      shop_sinch_product_id int(11),
                      sku varchar(64) default NULL,
                      store_product_id int(11),
                      sinch_product_id int(11),
                      product_sku varchar(255),
                      sinch_manufacturer_id int(11),
                      sinch_manufacturer_name varchar(255),
                      KEY entity_id (entity_id),
                      KEY manufacturer_option_id (manufacturer_option_id),
                      KEY manufacturer_name (manufacturer_name),
                      KEY store_product_id (store_product_id),
                      KEY sinch_product_id (sinch_product_id),
                      KEY sku (sku),
                      UNIQUE KEY(entity_id)
                          )
                          ");
        $this->db_do("CREATE TABLE IF NOT EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping') . "
                      LIKE " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping_temp'));
        $productEntityTable = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity');

        // backup Product ID in REWRITE mode
        if ($mode == 'REWRITE' && !$mapping_again) {
            $catalog_product_entity_backup = Mage::getSingleton('core/resource')->getTableName('sinch_product_backup');
            if ($this->_checkDataExist($catalog_product_entity_backup)) {
                $productEntityTable = $catalog_product_entity_backup;
            }
        }
        // (end) backup Product ID in REWRITE mode

        $result = $this->db_do("
                                INSERT ignore INTO " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping_temp') . " (
                                    entity_id,
                                    sku,
                                    shop_store_product_id,
                                    shop_sinch_product_id
                                )(SELECT
                                    entity_id,
                                    sku,
                                    store_product_id,
                                    sinch_product_id
                                  FROM " . $productEntityTable . "
                                 )
                              ");

        $this->addManufacturers(1);

        $q = "UPDATE " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping_temp') . " pmt
            JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_index_eav') . " cpie
                ON pmt.entity_id=cpie.entity_id
            JOIN " . Mage::getSingleton('core/resource')->getTableName('eav_attribute_option_value') . " aov
                ON cpie.value=aov.option_id
            SET
                manufacturer_option_id=cpie.value,
                manufacturer_name=aov.value
            WHERE cpie.attribute_id=" . $this->attributes['manufacturer'];
        $this->db_do($q);

        $q = "UPDATE " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping_temp') . " pmt
            JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " p
                ON pmt.sku=p.product_sku
            SET
                pmt.store_product_id=p.store_product_id,
                pmt.sinch_product_id=p.sinch_product_id,
                pmt.product_sku=p.product_sku,
                pmt.sinch_manufacturer_id=p.sinch_manufacturer_id,
                pmt.sinch_manufacturer_name=p.manufacturer_name";

        $this->db_do($q);

        $q = "UPDATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
            JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping_temp') . " pmt
                ON cpe.entity_id=pmt.entity_id
            SET cpe.store_product_id=pmt.store_product_id,
                cpe.sinch_product_id=pmt.sinch_product_id
            WHERE
                cpe.sinch_product_id IS NULL
                AND pmt.sinch_product_id IS NOT NULL
                AND cpe.store_product_id IS NULL
                AND pmt.store_product_id IS NOT NULL";
        $this->db_do($q);

        $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping'));
        $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping_temp') . "
                      TO " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping'));
    }

    function addManufacturers($delete_eav = null)
    {
        # Moe - Uche : Original code commented out
        # Reason: to make sure manufacturer value is not made blank for our products
        // // this cleanup is not needed due to foreign keys
        // if (!$delete_eav) {
        //     $result = $this->db_do("
        //                             DELETE FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_index_eav') . "
        //                             WHERE attribute_id = " . $this->_getProductAttributeId('manufacturer')
        //     );
        // }
        $this->addManufacturer_attribute();

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_index_eav') . " (
                                    entity_id,
                                    attribute_id,
                                    store_id,
                                    value
                                )(
                                  SELECT
                                    a.entity_id,
                                    " . $this->_getProductAttributeId('manufacturer') . ",
                                    w.website,
                                    mn.shop_option_id
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_manufacturers') . " mn
                                    ON b.sinch_manufacturer_id=mn.sinch_manufacturer_id
                                  WHERE mn.shop_option_id IS NOT NULL
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = mn.shop_option_id
                              ");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_index_eav') . " (
                                    entity_id,
                                    attribute_id,
                                    store_id,
                                    value
                                )(
                                  SELECT
                                    a.entity_id,
                                    " . $this->_getProductAttributeId('manufacturer') . ",
                                    0,
                                    mn.shop_option_id
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_manufacturers') . " mn
                                    ON b.sinch_manufacturer_id=mn.sinch_manufacturer_id
                                  WHERE mn.shop_option_id IS NOT NULL
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = mn.shop_option_id
                              ");
    }

    private function _getProductAttributeId($attributeCode)
    {
        return $this->_getAttributeId($attributeCode, 'catalog_product');
    }

    private function addManufacturer_attribute()
    {
        # Moe - Uche : changed: value = pm.manufacturer_option_id --> value = IFNULL(pm.manufacturer_option_id, value)
        # Reason: to prevent our out of stock products manufacturer attribute value going blank
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('manufacturer') . ",
                                    0,
                                    a.entity_id,
                                    pm.manufacturer_option_id
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping') . " pm
                                    ON a.entity_id = pm.entity_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = IFNULL(pm.manufacturer_option_id, value)
                              ");
    }

    public function replaceMagentoProducts()
    {
        $connection = Mage::getModel('core/resource')->getConnection('core_write');

        $result = $this->db_do("DELETE cpe
                                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping') . " pm
                                    ON cpe.entity_id=pm.entity_id
                                WHERE pm.shop_store_product_id IS NOT NULL
                                    AND pm.store_product_id IS NULL
                              ");

        //Inserting new products and updating old others.
        $this->_getProductDefaulAttributeSetId();
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " (
                                    entity_id,
                                    entity_type_id,
                                    attribute_set_id,
                                    type_id,
                                    sku,
                                    updated_at,
                                    has_options,
                                    store_product_id,
                                    sinch_product_id
                                )(SELECT
                                     pm.entity_id,
                                     " . $this->_getProductEntityTypeId() . ",
                                     $this->defaultAttributeSetId,
                                     'simple',
                                     a.product_sku,
                                     NOW(),
                                     0,
                                     a.store_product_id,
                                     a.sinch_product_id
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " a
                                  LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping') . " pm
                                     ON a.store_product_id=pm.store_product_id
                                     AND a.sinch_product_id=pm.sinch_product_id
                                  WHERE pm.entity_id IS NOT NULL
                                )
                                ON DUPLICATE KEY UPDATE
                                    sku= a.product_sku,
                                    store_product_id=a.store_product_id,
                                    sinch_product_id=a.sinch_product_id
                              ");
        //                            store_product_id=a.store_product_id,
        //                            sinch_product_id=a.sinch_product_id

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " (
                                    entity_id,
                                    entity_type_id,
                                    attribute_set_id,
                                    type_id,
                                    sku,
                                    updated_at,
                                    has_options,
                                    store_product_id,
                                    sinch_product_id
                                )(SELECT
                                     pm.entity_id,
                                     " . $this->_getProductEntityTypeId() . ",
                                     $this->defaultAttributeSetId,
                                     'simple',
                                     a.product_sku,
                                     NOW(),
                                     0,
                                     a.store_product_id,
                                     a.sinch_product_id
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " a
                                  LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping') . " pm
                                     ON a.store_product_id=pm.store_product_id
                                     AND a.sinch_product_id=pm.sinch_product_id
                                  WHERE pm.entity_id IS NULL
                                )
                                ON DUPLICATE KEY UPDATE
                                    sku= a.product_sku,
                                    store_product_id=a.store_product_id,
                                    sinch_product_id=a.sinch_product_id
                              ");

        //Set enabled
        $result = $this->db_do("DELETE cpei
                                FROM  " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int') . " cpei
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                    ON cpei.entity_id=cpe.entity_id
                                WHERE cpe.entity_id IS NULL");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                 )(
                                    SELECT
                                        " . $this->_getProductEntityTypeId() . ",
                                        " . $this->_getProductAttributeId('status') . ",
                                        w.website,
                                        a.entity_id,
                                        1
                                    FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                    INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                        ON a.store_product_id=w.store_product_id
                                 )
                                 ON DUPLICATE KEY UPDATE
                                    value=1
                              ");
        # Moe : Changed value=1 to value=value
        # Reason: So our products status does not get changed
        // set status = 1 for all stores
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('status') . ",
                                    0,
                                    a.entity_id,
                                    1
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                )
                                ON DUPLICATE KEY UPDATE
                                    value=value
                              ");

        //Unifying products with categories.
        $result = $this->db_do("DELETE ccp
                                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " ccp
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                    ON ccp.product_id=cpe.entity_id
                                WHERE cpe.entity_id IS NULL");

        /*echo("\n\n\nUPDATE IGNORE " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " ccp
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . " cce
                                    ON ccp.category_id=cce.entity_id
                                SET ccp.category_id=" . $this->_root_cat . "
                                WHERE cce.entity_id IS NULL");*/
        $result = $this->db_do("UPDATE IGNORE " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " ccp
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . " cce
                                    ON ccp.category_id=cce.entity_id
                                SET ccp.category_id=" . $this->_root_cat . "
                                WHERE cce.entity_id IS NULL");
        //echo("\ndone\n");


        /*echo("\n\n\nDELETE ccp FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " ccp
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . " cce
                                    ON ccp.category_id=cce.entity_id
                                WHERE cce.entity_id IS NULL");*/
        $result = $this->db_do("DELETE ccp FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " ccp
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . " cce
                                    ON ccp.category_id=cce.entity_id
                                WHERE cce.entity_id IS NULL");
        //echo("\ndone\n");


        $this->db_do(" DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . "_for_delete_temp");
        // TEMPORARY
        $this->db_do("
                CREATE TABLE `" . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . "_for_delete_temp` (
                    `category_id` int(10) unsigned NOT NULL default '0',
                    `product_id` int(10) unsigned NOT NULL default '0',
                    `store_product_id` int(10) NOT NULL default '0',
                    `store_category_id` int(10) NOT NULL default '0',
                    `new_category_id` int(10) NOT NULL default '0',
                    UNIQUE KEY `UNQ_CATEGORY_PRODUCT` (`category_id`,`product_id`),
                    KEY `CATALOG_CATEGORY_PRODUCT_CATEGORY` (`category_id`),
                    KEY `CATALOG_CATEGORY_PRODUCT_PRODUCT` (`product_id`),
                    KEY `CATALOG_NEW_CATEGORY_PRODUCT_CATEGORY` (`new_category_id`)
                    )

                ");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . "_for_delete_temp (
                                    category_id,
                                    product_id,
                                    store_product_id
                                )(SELECT
                                    ccp.category_id,
                                    ccp.product_id,
                                    cpe.store_product_id
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " ccp
                                  JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                    ON ccp.product_id=cpe.entity_id
                                             WHERE store_product_id is not null
                                )
                              ");

        $result = $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . "_for_delete_temp ccpfd
                                JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " p
                                    ON ccpfd.store_product_id=p.store_product_id
                                SET ccpfd.store_category_id=p.store_category_id
                                WHERE ccpfd.store_product_id!=0
                              ");

        $result = $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . "_for_delete_temp ccpfd
                                JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping') . " scm
                                    ON ccpfd.store_category_id=scm.store_category_id
                                SET ccpfd.new_category_id=scm.shop_entity_id
                                WHERE ccpfd.store_category_id!=0
                              ");

        $result = $this->db_do("DELETE FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . "_for_delete_temp
                                WHERE category_id=new_category_id");

        # Moe : Commented below block out
        # Reason: So does not remove existing selected categories
        // $result = $this->db_do("
        //                         DELETE ccp
        //                         FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " ccp
        //                         JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . "_for_delete_temp ccpfd
        //                             ON ccp.product_id=ccpfd.product_id
        //                             AND ccp.category_id=ccpfd.category_id
        //                       ");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " (
                                    category_id,
                                    product_id
                                )(SELECT
                                    scm.shop_entity_id,
                                    cpe.entity_id
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                  JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " p
                                    ON cpe.store_product_id=p.store_product_id
                                  JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping') . " scm
                                    ON p.store_category_id=scm.store_category_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    product_id = cpe.entity_id
                              ");


        //add multi categories
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . "
                                (category_id,  product_id)
                                (SELECT
                                 scm.shop_entity_id,
                                 cpe.entity_id
                                 FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                 JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " p
                                 ON cpe.store_product_id = p.store_product_id
                                 JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_product_categories') . " spc
                                 ON p.store_product_id=spc.store_product_id
                                 JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping') . " scm
                                 ON spc.store_category_id = scm.store_category_id
                                )
                                ON DUPLICATE KEY UPDATE
                                product_id = cpe.entity_id
                                ");


        //Indexing products and categories in the shop
        $result = $this->db_do("DELETE ccpi
                                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product_index') . " ccpi
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                    ON ccpi.product_id=cpe.entity_id
                                WHERE cpe.entity_id IS NULL");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product_index') . " (
                                    category_id,
                                    product_id,
                                    position,
                                    is_parent,
                                    store_id,
                                    visibility
                                )(
                                  SELECT
                                    a.category_id,
                                    a.product_id,
                                    a.position,
                                    1,
                                    b.store_id,
                                    4
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('core_store') . " b
                                )
                                ON DUPLICATE KEY UPDATE
                                    visibility = 4
                              ");

        $result = $this->db_do("
                                INSERT ignore INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product_index') . " (
                                    category_id,
                                    product_id,
                                    position,
                                    is_parent,
                                    store_id,
                                    visibility
                                )(
                                  SELECT
                                    " . $this->_root_cat . ",
                                    a.product_id,
                                    a.position,
                                    1,
                                    b.store_id,
                                    4
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('core_store') . " b
                                )
                                ON DUPLICATE KEY UPDATE
                                    visibility = 4
                              ");

        //Set product name for specific web sites
        $result = $this->db_do("DELETE cpev
                                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " cpev
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                ON cpev.entity_id=cpe.entity_id
                                WHERE cpe.entity_id IS NULL");
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('name') . ",
                                    w.website,
                                    a.entity_id,
                                    b.product_name
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id= b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.product_name
                              ");

        // product name for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('name') . ",
                                    0,
                                    a.entity_id,
                                    b.product_name
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.product_name
                              ");

        $this->dropHTMLentities($this->_getProductEntityTypeId(), $this->_getProductAttributeId('name'));
        $this->addDescriptions();
        $this->cleanProductDistributors();
        if (!$this->_ignore_product_contracts) {
            $this->cleanProductContracts();
        }
        if ($this->product_file_format == "NEW") {
            $this->addReviews();
            $this->addWeight();
            $this->addSearchCache();
            $this->addPdfUrl();
            $this->addShortDescriptions();
            $this->addProductDistributors();
            if (!$this->_ignore_product_contracts) {
                $this->addProductContracts();
            }
        }
        $this->addEAN();
        $this->addSpecification();
        $this->addManufacturers();

        //Enabling product index.
        $result = $this->db_do("DELETE cpei
                                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_enabled_index') . " cpei
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                    ON cpei.product_id=cpe.entity_id
                                WHERE cpe.entity_id IS NULL");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_enabled_index') . " (
                                    product_id,
                                    store_id,
                                    visibility
                                )(
                                  SELECT
                                    a.entity_id,
                                    w.website,
                                    4
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    visibility = 4
                              ");
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_enabled_index') . " (
                                    product_id,
                                    store_id,
                                    visibility
                                )(
                                  SELECT
                                    a.entity_id,
                                    0,
                                    4
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    visibility = 4
                              ");

        # Uche : Changed value = 4 to value = value on duplicate
        # Reason : To maintain product visibility value or our items
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('visibility') . ",
                                    w.website,
                                    a.entity_id,
                                    4
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                  ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                value = value
                              "); #1
        # Uche : Changed value = 4 to value = value on duplicate
        # Reason : To maintain product visibility value or our items
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('visibility') . ",
                                    0,
                                    a.entity_id,
                                    4
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = value
                              "); #2

        $result = $this->db_do("DELETE cpw
                                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_website') . " cpw
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                    ON cpw.product_id=cpe.entity_id
                                WHERE cpe.entity_id IS NULL");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_website') . " (
                                    product_id,
                                    website_id
                                )(
                                  SELECT a.entity_id, w.website_id
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                      ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    product_id=a.entity_id,
                                    website_id=w.website_id
                              ");

        //Adding tax class "Taxable Goods"
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('tax_class_id') . ",
                                    w.website,
                                    a.entity_id,
                                    2
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                  ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = 2
                ");
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('tax_class_id') . ",
                                    0,
                                    a.entity_id,
                                    2
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = 2
                              ");

        // Load url Image
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('image') . ",
                                    w.store_id,
                                    a.entity_id,
                                    b.main_image_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('core_store') . " w
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.main_image_url
                              ");

        // image for specific web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('image') . ",
                                    0,
                                    a.entity_id,
                                    b.main_image_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.main_image_url
                              ");

        // small_image for specific web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('small_image') . ",
                                    w.store_id,
                                    a.entity_id,
                                    b.medium_image_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('core_store') . " w
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.medium_image_url
                                ");

        // small_image for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('small_image') . ",
                                    0,
                                    a.entity_id,
                                    b.medium_image_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('core_store') . " w
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.medium_image_url
                              ");


        // thumbnail for specific web site
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('thumbnail') . ",
                                    w.store_id,
                                    a.entity_id,
                                    b.thumb_image_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('core_store') . " w
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.thumb_image_url
                              ");

        // thumbnail for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('thumbnail') . ",
                                    0,
                                    a.entity_id,
                                    b.thumb_image_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('core_store') . " w
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.thumb_image_url

                ");

        /*STP DELETE
        //Refresh fulltext search
        $result = $this->db_do("DROP TABLE IF EXISTS ".Mage::getSingleton('core/resource')->getTableName('catalogsearch_fulltext')."_tmp");
        $result = $this->db_do("CREATE TEMPORARY TABLE IF NOT EXISTS
                               ".Mage::getSingleton('core/resource')->getTableName('catalogsearch_fulltext')."_tmp
                               LIKE ".Mage::getSingleton('core/resource')->getTableName('catalogsearch_fulltext'));

        $result = $this->db_do("
                                INSERT INTO ".Mage::getSingleton('core/resource')->getTableName('catalogsearch_fulltext')."_tmp (
                                    product_id,
                                    store_id,
                                    data_index
                                )(
                                  SELECT
                                    a.entity_id,
                                    w.website,
                                    CONCAT_WS(' ', a.sku, f.search_cache, c.value, e.value)
                                  FROM ".Mage::getSingleton('core/resource')->getTableName('catalog_product_entity')." a
                                  INNER JOIN ".Mage::getSingleton('core/resource')->getTableName('products_website_temp')." w
                                    ON a.store_product_id=w.store_product_id
                                  LEFT JOIN ".Mage::getSingleton('core/resource')->getTableName('catalog_category_product')." b
                                    ON a.entity_id = b.product_id
                                  LEFT JOIN ".Mage::getSingleton('core/resource')->getTableName('catalog_category_entity_varchar')." c
                                    ON b.category_id = c.entity_id
                                    AND c.attribute_id = " . $this->_getCategoryAttributeId('name'). "
                                  LEFT JOIN ".Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar')." e
                                    ON a.entity_id = e.entity_id
                                    AND e.attribute_id = " . $this->_getProductAttributeId('name'). "
                                  LEFT JOIN ".Mage::getSingleton('core/resource')->getTableName('catalog_product_website')." j
                                    ON a.entity_id = j.product_id
                                  LEFT JOIN ".Mage::getSingleton('core/resource')->getTableName('products_temp')." f
                                    ON a.store_product_id = f.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                data_index = CONCAT_WS(' ', a.sku, f.search_cache, c.value, e.value)
                              ");

        $result = $this->db_do("
                                INSERT INTO ".Mage::getSingleton('core/resource')->getTableName('catalogsearch_fulltext')."_tmp (
                                    product_id,
                                    store_id,
                                    data_index
                                )(
                                  SELECT
                                    a.entity_id,
                                    w.website,
                                    CONCAT_WS(' ', a.sku, f.search_cache, c.value, e.value)
                                  FROM ".Mage::getSingleton('core/resource')->getTableName('catalog_product_entity')." a
                                  INNER JOIN ".Mage::getSingleton('core/resource')->getTableName('products_website_temp')." w
                                    ON a.store_product_id=w.store_product_id
                                  LEFT JOIN ".Mage::getSingleton('core/resource')->getTableName('catalog_category_product')." b
                                    ON a.entity_id = b.product_id
                                  LEFT JOIN ".Mage::getSingleton('core/resource')->getTableName('catalog_category_entity_varchar')." c
                                    ON b.category_id = c.entity_id
                                    AND c.attribute_id = " . $this->_getCategoryAttributeId('name'). "
                                  LEFT JOIN ".Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar')." e
                                    ON a.entity_id = e.entity_id
                                    AND e.attribute_id = " . $this->_getProductAttributeId('name'). "
                                  LEFT JOIN ".Mage::getSingleton('core/resource')->getTableName('products_temp')." f
                                    ON a.store_product_id = f.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    data_index = CONCAT_WS(' ', a.sku, f.search_cache, c.value, e.value)
                              ");

        $result = $this->db_do("DELETE cf
                                FROM ".Mage::getSingleton('core/resource')->getTableName('catalogsearch_fulltext')." cf
                                LEFT JOIN ".Mage::getSingleton('core/resource')->getTableName('catalog_product_entity')." cpe
                                    ON cf.product_id=cpe.entity_id
                                WHERE cpe.entity_id is null");

        $result = $this->db_do("
                                INSERT INTO ".Mage::getSingleton('core/resource')->getTableName('catalogsearch_fulltext')." (
                                    product_id,
                                    store_id,
                                    data_index
                                )(
                                  SELECT
                                    a.product_id,
                                    a.store_id,
                                    a.data_index
                                  FROM ".Mage::getSingleton('core/resource')->getTableName('catalogsearch_fulltext')."_tmp a
                                  WHERE product_id = a.product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    data_index = a.data_index
                              ");

        $this->db_do("UPDATE ".Mage::getSingleton('core/resource')->getTableName('catalogsearch_query')." SET is_processed = 0");
        //INNER JOIN eav_attribute_option_value d ON a.vendor_id = d.option_id
        //TODO add something else
STP DELETE*/
        $this->addRelatedProducts();
    }

    private function _getProductDefaulAttributeSetId()
    {
        if (!$this->defaultAttributeSetId) {
            $sql = "
                SELECT entity_type_id, default_attribute_set_id
                FROM " . Mage::getSingleton('core/resource')->getTableName('eav_entity_type') . "
                WHERE entity_type_code = 'catalog_product'
                LIMIT 1
                ";
            $result = $this->db_do($sql);
            if ($row = mysqli_fetch_assoc($result)) {

                $this->defaultAttributeSetId = $row['default_attribute_set_id'];
            }
        }
        return $this->defaultAttributeSetId;
    }


    function dropHTMLentities($entity_type_id, $attribute_id)
    {
        // product name for all web sites
        $result = $this->db_do("
                                SELECT value, entity_id
                                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . "
                                WHERE entity_type_id=" . $entity_type_id . "
                                    AND attribute_id=" . $attribute_id
        );
        while ($row = mysqli_fetch_array($result)) {
            $value = $this->valid_char($row['value']);
            if ($value != '' and $value != $row['value']) {
                $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . "
                              SET value='" . mysqli_real_escape_string($this->db, $value) . "'
                              WHERE entity_id=" . $row['entity_id'] . "
                              AND entity_type_id=" . $entity_type_id . "
                              AND attribute_id=" . $attribute_id);
            }
        }
    }

    function valid_char($string)
    {
        $string = preg_replace('/&#8482;/', ' ', $string);
        $string = preg_replace('/&reg;/', ' ', $string);
        $string = preg_replace('/&asymp;/', ' ', $string);
        $string = preg_replace('/&quot;/', ' ', $string);
        $string = preg_replace('/&prime;/', ' ', $string);
        $string = preg_replace('/&deg;/', ' ', $string);
        $string = preg_replace('/&plusmn;/', ' ', $string);
        $string = preg_replace('/&micro;/', ' ', $string);
        $string = preg_replace('/&sup2;/', ' ', $string);
        $string = preg_replace('/&sup3;/', ' ', $string);
        return $string;
    }

    function addDescriptions()
    {
        // product description for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_text') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('description') . ",
                                    w.website,
                                    a.entity_id,
                                    b.description
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.description
                              ");

        // product description for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_text') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('description') . ",
                                    0,
                                    a.entity_id,
                                    b.description
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.description
                              ");
    }

    function cleanProductDistributors()
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . "
                    SET value = ''
                    WHERE entity_type_id=" . $this->_getProductEntityTypeId() . " AND attribute_id=" . $this->_getProductAttributeId('supplier_' . $i));
        }
    }

    function cleanProductContracts()
    {
        if ($contractIdAttr = $this->_getProductAttributeId('contract_id')) {
            $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . "
                SET value = ''
                WHERE entity_type_id=" . $this->_getProductEntityTypeId() . " AND attribute_id=" . $contractIdAttr);
        }
    }

    function addReviews()
    {
        // product reviews  for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_text') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('reviews') . ",
                                    w.website,
                                    a.entity_id,
                                    b.Reviews
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.Reviews
                              ");

        // product Reviews for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_text') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('reviews') . ",
                                    0,
                                    a.entity_id,
                                    b.Reviews
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.Reviews
                              ");
    }

    function addWeight()
    {
        $mult = 0.001;
        # Moe - Uche : changed b.Weight to b.Weight*{$mult}
        # Reason: Website and other platform uses kilograms, so here we convert grams to kilograms
        // product weight for specific web site
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_decimal') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('weight') . ",
                                    w.website,
                                    a.entity_id,
                                    b.Weight*{$mult}
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.Weight*{$mult}
                              ");
        // product weight for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_decimal') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('weight') . ",
                                    0,
                                    a.entity_id,
                                    b.Weight*{$mult}
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.Weight*{$mult}


                              ");
    }

    function addSearchCache()
    {
        // product search_cache for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_text') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('sinch_search_cache') . ",
                                    w.website,
                                    a.entity_id,
                                    b.search_cache
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.search_cache
                              ");

        // product search_cache for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_text') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('sinch_search_cache') . ",
                                    0,
                                    a.entity_id,
                                    b.search_cache
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.search_cache
                              ");
    }

    function addPdfUrl()
    {
        // product PDF Url for all web sites
        $result = $this->db_do("
                                UPDATE " . Mage::getSingleton('core/resource')->getTableName('products_temp') . "
                                SET pdf_url = CONCAT(
                                                        '<a href=\"#\" onclick=\"popWin(',
                                                        \"'\",
                                                        pdf_url,
                                                        \"'\",
                                                        \", 'pdf', 'width=500,height=800,left=50,top=50, location=no,status=yes,scrollbars=yes,resizable=yes'); return false;\",
                                                        '\"',
                                                        '>',
                                                        pdf_url,
                                                        '</a>')
                                WHERE pdf_url != ''
        ");
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('pdf_url') . ",
                                    w.website,
                                    a.entity_id,
                                    b.pdf_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.pdf_url
                              ");
        // product  PDF url for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('pdf_url') . ",
                                    0,
                                    a.entity_id,
                                    b.pdf_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.pdf_url
                              ");
    }

    function addShortDescriptions()
    {
        // product short description for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('short_description') . ",
                                    w.website,
                                    a.entity_id,
                                    b.product_short_description
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.product_short_description
                              ");
        // product short description for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('short_description') . ",
                                    0,
                                    a.entity_id,
                                    b.product_short_description
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.product_short_description
                              ");
    }

    function addProductDistributors()
    {
        $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary'));
        $this->db_do("CREATE TABLE IF NOT EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary') . "
                      LIKE " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price'));
        $this->db_do("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary') . " SELECT * FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price'));
        for ($i = 1; $i <= 5; $i++) {
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary_supplier'));
            $this->db_do("CREATE TABLE IF NOT EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary_supplier') . "
                      LIKE " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price'));
            $this->db_do("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary_supplier') . " SELECT * FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary') . " GROUP BY store_product_id");

            // product Distributors for all web sites
            $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('supplier_' . $i) . ",
                                    w.website,
                                    a.entity_id,
                                    d.distributor_name
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary_supplier') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors') . " d
                                    ON b.distributor_id = d.distributor_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = d.distributor_name
                              ");
            // product Distributors for all web sites
            $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('supplier_' . $i) . ",
                                    0,
                                    a.entity_id,
                                    d.distributor_name
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary_supplier') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors') . " d
                                    ON b.distributor_id = d.distributor_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = d.distributor_name
                              ");

            $this->db_do("DELETE sdsapt FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary') . " sdsapt JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price_temporary_supplier') . " sdsapts ON sdsapt.store_product_id = sdsapts.store_product_id AND sdsapt.distributor_id = sdsapts.distributor_id");
        }
    }

    function addProductContracts()
    {
        $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_product_contracts_temporary'));
        $this->db_do("CREATE TABLE IF NOT EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_product_contracts_temporary') . "(
                          `store_product_id` int(11) DEFAULT NULL,
                          `contract_id_str` varchar(255) DEFAULT NULL,
                          KEY `store_product_id` (store_product_id)
            )
    ");
        $this->db_do("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('stINch_product_contracts_temporary') . " SELECT store_product_id, group_concat(contract_id) FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_product_contracts') . " GROUP BY store_product_id");
        // product Distributors for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('contract_id') . ",
                                    w.website,
                                    a.entity_id,
                                    b.contract_id_str
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_product_contracts_temporary') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.contract_id_str
                              ");
        // product Distributors for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('contract_id') . ",
                                    0,
                                    a.entity_id,
                                    b.contract_id_str
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_product_contracts_temporary') . " b
                                    ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.contract_id_str
                              ");
    }

    function addEAN()
    {
        //gather EAN codes for each product
        $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('EANs_temp'));
        $this->db_do("
                      CREATE TEMPORARY TABLE " . Mage::getSingleton('core/resource')->getTableName('EANs_temp') . " (
                        sinch_product_id int(11),
                        store_product_id int(11),
                        EANs text,
                        KEY `sinch_product_id` (`sinch_product_id`),
                        KEY `store_product_id` (`store_product_id`)
                     )
                ");
        $this->db_do("
                      INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('EANs_temp') . " (
                            sinch_product_id,
                            EANs
                      )(SELECT
                            sec.product_id,
                        GROUP_CONCAT(DISTINCT ean_code ORDER BY ean_code DESC SEPARATOR ', ') AS eans
                        FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_ean_codes') . " sec
                        GROUP BY sec.product_id
                      )
                    ");
        $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('EANs_temp') . " e
                          JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " p
                            ON e.sinch_product_id=p.sinch_product_id
                          SET e.store_product_id=p.store_product_id");
        // product EANs for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('ean') . ",
                                    w.website,
                                    a.entity_id,
                                    e.EANs
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('EANs_temp') . " e
                                    ON a.store_product_id = e.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = e.EANs
                ");

        // product EANs for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('ean') . ",
                                    0,
                                    a.entity_id,
                                    e.EANs
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('EANs_temp') . " e
                                    ON a.store_product_id = e.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = e.EANs
                              ");

    }

    function addSpecification()
    {
        // product specification for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_text') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('specification') . ",
                                    w.website,
                                    a.entity_id,
                                    b.specifications
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                    ON a.store_product_id = b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.specifications
                ");
        // product specification  for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_text') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('specification') . ",
                                    0,
                                    a.entity_id,
                                    b.specifications
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " b
                                      ON a.store_product_id = b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.specifications
                              ");
    }

    function addRelatedProducts()
    {

        $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('stINch_related_products') . " rpt
                      JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " p
                        ON rpt.sinch_product_id=p.sinch_product_id
                      JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                        ON p.store_product_id=cpe.store_product_id
                      SET rpt.store_product_id=p.store_product_id, rpt.entity_id=cpe.entity_id");

        $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('stINch_related_products') . " rpt
                      JOIN " . Mage::getSingleton('core/resource')->getTableName('products_temp') . " p
                        ON rpt.related_sinch_product_id=p.sinch_product_id
                      JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                        ON p.store_product_id=cpe.store_product_id
                      SET rpt.store_related_product_id=p.store_product_id, rpt.related_entity_id=cpe.entity_id");

        $result = $this->db_do("SELECT
                                    link_type_id,
                                    code
                                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link_type')
        );
        $link_type = array();
        while ($row = mysqli_fetch_array($result)) {
            $link_type[$row['code']] = $row['link_type_id'];
        }

        // delete old data
        $this->db_do("SET FOREIGN_KEY_CHECKS=0");
        $this->db_do("TRUNCATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link'));
        $this->db_do("TRUNCATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link_attribute_int'));
        $this->db_do("SET FOREIGN_KEY_CHECKS=1");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link') . " (
                                    product_id,
                                    linked_product_id,
                                    link_type_id
                                )(
                                  SELECT
                                    entity_id,
                                    related_entity_id,
                                    " . $link_type['relation'] . "
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_related_products') . "
                                  WHERE store_product_id IS NOT NULL
                                  AND store_related_product_id IS NOT NULL
                                )
                                ON DUPLICATE KEY UPDATE
                                    product_id = entity_id,
                                    linked_product_id = related_entity_id
                ");
        $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link_attribute_int') . "_tmp");

        $this->db_do("CREATE TEMPORARY TABLE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link_attribute_int') . "_tmp (
                        `value_id` int(11) default NULL,
                        `product_link_attribute_id` smallint(6) unsigned default NULL,
                        `link_id` int(11) unsigned default NULL,
                        `value` int(11) NOT NULL default '0',
                         KEY `FK_INT_PRODUCT_LINK_ATTRIBUTE` (`product_link_attribute_id`),
                         KEY `FK_INT_PRODUCT_LINK` (`link_id`)
                      )
                    ");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link_attribute_int') . "_tmp(
                                    product_link_attribute_id,
                                    link_id,
                                    value
                                )(
                                  SELECT
                                    2,
                                    cpl.link_id,
                                    0
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link') . " cpl
                                )
                              ");

        $result = $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link_attribute_int') . "_tmp ct
                                JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link_attribute_int') . " c
                                    ON ct.link_id=c.link_id
                                SET ct.value_id=c.value_id
                                WHERE c.product_link_attribute_id=2
                              ");

        $result = $this->db_do("
                                    INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link_attribute_int') . " (
                                        value_id,
                                        product_link_attribute_id,
                                        link_id,
                                        value
                                    )(
                                      SELECT
                                        value_id,
                                        product_link_attribute_id,
                                        link_id,
                                        value
                                      FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_link_attribute_int') . "_tmp ct
                                    )
                                    ON DUPLICATE KEY UPDATE
                                        link_id=ct.link_id

                                  ");
    }

    public function replaceMagentoProductsMultistore($coincidence)
    {
        Mage::log("\n     replaceMagentoProductsMultistore 1\n", null, "importstatus.log");

        $connection = Mage::getModel('core/resource')->getConnection('core_write');

        $products_temp = Mage::getSingleton('core/resource')->getTableName('products_temp');
        $products_website_temp = Mage::getSingleton('core/resource')->getTableName('products_website_temp');
        $catalog_product_entity = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity');
        $catalog_product_entity_int = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int');
        $catalog_product_entity_varchar = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar');
        $catalog_category_product = Mage::getSingleton('core/resource')->getTableName('catalog_category_product');
        $stINch_products_mapping = Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping');
        $catalog_category_entity = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity');
        $stINch_categories_mapping = Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping');
        $catalog_category_product_index = Mage::getSingleton('core/resource')->getTableName('catalog_category_product_index');
        $core_store = Mage::getSingleton('core/resource')->getTableName('core_store');
        $catalog_product_enabled_index = Mage::getSingleton('core/resource')->getTableName('catalog_product_enabled_index');
        $catalog_product_website = Mage::getSingleton('core/resource')->getTableName('catalog_product_website');
        //$catalogsearch_fulltext = Mage::getSingleton('core/resource')->getTableName('catalogsearch_fulltext');
        //$catalogsearch_query = Mage::getSingleton('core/resource')->getTableName('catalogsearch_query');
        $catalog_category_entity_varchar = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity_varchar');

        $_getProductEntityTypeId = $this->_getProductEntityTypeId();
        $_defaultAttributeSetId = $this->_getProductDefaulAttributeSetId();

        $attr_atatus = $this->_getProductAttributeId('status');
        $attr_name = $this->_getProductAttributeId('name');
        $attr_visibility = $this->_getProductAttributeId('visibility');
        $attr_tax_class_id = $this->_getProductAttributeId('tax_class_id');
        $attr_image = $this->_getProductAttributeId('image');
        $attr_small_image = $this->_getProductAttributeId('small_image');
        $attr_thumbnail = $this->_getProductAttributeId('thumbnail');

        $cat_attr_name = $this->_getCategoryAttributeId('name');
        Mage::log("\n     replaceMagentoProductsMultistore 2\n", null, "importstatus.log");

        //clear products, inserting new products and updating old others.
        $query = "
            DELETE cpe
            FROM $catalog_product_entity cpe
            JOIN $stINch_products_mapping pm
                ON cpe.entity_id = pm.entity_id
            WHERE pm.shop_store_product_id IS NOT NULL
                AND pm.store_product_id IS NULL";
        $result = $this->db_do($query);


        Mage::log("\n     replaceMagentoProductsMultistore 3\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_product_entity
                (entity_id, entity_type_id, attribute_set_id, type_id, sku, updated_at, has_options, store_product_id, sinch_product_id)
            (SELECT
                pm.entity_id,
                $_getProductEntityTypeId,
                $_defaultAttributeSetId,
                'simple',
                a.product_sku,
                NOW(),
                0,
                a.store_product_id,
                a.sinch_product_id
            FROM $products_temp a
            LEFT JOIN $stINch_products_mapping pm
                ON a.store_product_id = pm.store_product_id
                AND a.sinch_product_id = pm.sinch_product_id
            WHERE pm.entity_id IS NULL
            )
            ON DUPLICATE KEY UPDATE
                sku = a.product_sku,
                store_product_id = a.store_product_id,
                sinch_product_id = a.sinch_product_id");
        // store_product_id = a.store_product_id,
        // sinch_product_id = a.sinch_product_id

        $result = $this->db_do("
            INSERT INTO $catalog_product_entity
                (entity_id, entity_type_id, attribute_set_id, type_id, sku, updated_at, has_options, store_product_id, sinch_product_id)
            (SELECT
                pm.entity_id,
                $_getProductEntityTypeId,
                $_defaultAttributeSetId,
                'simple',
                a.product_sku,
                NOW(),
                0,
                a.store_product_id,
                a.sinch_product_id
            FROM $products_temp a
            LEFT JOIN $stINch_products_mapping pm
                ON a.store_product_id = pm.store_product_id
                AND a.sinch_product_id = pm.sinch_product_id
            WHERE pm.entity_id IS NOT NULL
            )
            ON DUPLICATE KEY UPDATE
                sku = a.product_sku,
                store_product_id = a.store_product_id,
                sinch_product_id = a.sinch_product_id");
        // store_product_id = a.store_product_id,
        // sinch_product_id = a.sinch_product_id

        Mage::log("\n     replaceMagentoProductsMultistore 4\n", null, "importstatus.log");

        //Set enabled
        $result = $this->db_do("
            DELETE cpei
            FROM $catalog_product_entity_int cpei
            LEFT JOIN $catalog_product_entity cpe
                ON cpei.entity_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_atatus,
                w.website,
                a.entity_id,
                1
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = 1");

        Mage::log("\n     replaceMagentoProductsMultistore 5\n", null, "importstatus.log");

        // set status = 1 for all stores
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_atatus,
                0,
                a.entity_id,
                1
            FROM $catalog_product_entity a
            )
            ON DUPLICATE KEY UPDATE
                value = 1");

        Mage::log("\n     replaceMagentoProductsMultistore 6\n", null, "importstatus.log");

        //Unifying products with categories.
        $result = $this->db_do("
            DELETE ccp
            FROM $catalog_category_product ccp
            LEFT JOIN $catalog_product_entity cpe
                ON ccp.product_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        Mage::log("\n     replaceMagentoProductsMultistore 7\n", null, "importstatus.log");

        $root_cats = Mage::getSingleton('core/resource')->getTableName('root_cats');

        $result = $this->db_do("DROP TABLE IF EXISTS $root_cats");
        $result = $this->db_do("
CREATE TABLE $root_cats
SELECT
    entity_id,
    path,
    SUBSTRING(path, LOCATE('/', path)+1) AS short_path,
    LOCATE('/', SUBSTRING(path, LOCATE('/', path)+1)) AS end_pos,
    SUBSTRING(SUBSTRING(path, LOCATE('/', path)+1), 1, LOCATE('/', SUBSTRING(path, LOCATE('/', path)+1))-1) as root_cat
FROM $catalog_category_entity
");
        $result = $this->db_do("UPDATE $root_cats SET root_cat = entity_id WHERE CHAR_LENGTH(root_cat) = 0");

        Mage::log("\n     replaceMagentoProductsMultistore 8\n", null, "importstatus.log");

        $result = $this->db_do("
            UPDATE IGNORE $catalog_category_product ccp
            LEFT JOIN $catalog_category_entity cce
                ON ccp.category_id = cce.entity_id
            JOIN $root_cats rc
                ON cce.entity_id = rc.entity_id
            SET ccp.category_id = rc.root_cat
            WHERE cce.entity_id IS NULL");


        Mage::log("\n     replaceMagentoProductsMultistore 9\n", null, "importstatus.log");


        $result = $this->db_do("
            DELETE ccp
            FROM $catalog_category_product ccp
            LEFT JOIN $catalog_category_entity cce
                ON ccp.category_id = cce.entity_id
            WHERE cce.entity_id IS NULL");

        Mage::log("\n     replaceMagentoProductsMultistore 10\n", null, "importstatus.log");

        $catalog_category_product_for_delete_temp = $catalog_category_product . "_for_delete_temp";

        // TEMPORARY
        $this->db_do(" DROP TABLE IF EXISTS $catalog_category_product_for_delete_temp");
        $this->db_do("
            CREATE TABLE $catalog_category_product_for_delete_temp
            (
                `category_id`       int(10) unsigned NOT NULL default '0',
                `product_id`        int(10) unsigned NOT NULL default '0',
                `store_product_id`  int(10) NOT NULL default '0',
                `store_category_id` int(10) NOT NULL default '0',
                `new_category_id`   int(10) NOT NULL default '0',

                UNIQUE KEY `UNQ_CATEGORY_PRODUCT` (`category_id`,`product_id`),
                KEY `CATALOG_CATEGORY_PRODUCT_CATEGORY` (`category_id`),
                KEY `CATALOG_CATEGORY_PRODUCT_PRODUCT` (`product_id`),
                KEY `CATALOG_NEW_CATEGORY_PRODUCT_CATEGORY` (`new_category_id`)
            )");

        Mage::log("\n     replaceMagentoProductsMultistore 11\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_category_product_for_delete_temp
                (category_id, product_id, store_product_id)
            (SELECT
                ccp.category_id,
                ccp.product_id,
                cpe.store_product_id
            FROM $catalog_category_product ccp
            JOIN $catalog_product_entity cpe
                ON ccp.product_id = cpe.entity_id
            WHERE store_product_id IS NOT NULL)");

        Mage::log("\n     replaceMagentoProductsMultistore 12\n", null, "importstatus.log");

        $result = $this->db_do("
            UPDATE $catalog_category_product_for_delete_temp ccpfd
            JOIN $products_temp p
                ON ccpfd.store_product_id = p.store_product_id
            SET ccpfd.store_category_id = p.store_category_id
            WHERE ccpfd.store_product_id != 0");

        Mage::log("\n     replaceMagentoProductsMultistore 13\n", null, "importstatus.log");

        $result = $this->db_do("
            UPDATE $catalog_category_product_for_delete_temp ccpfd
            JOIN $stINch_categories_mapping scm
                ON ccpfd.store_category_id = scm.store_category_id
            SET ccpfd.new_category_id = scm.shop_entity_id
            WHERE ccpfd.store_category_id != 0");

        Mage::log("\n     replaceMagentoProductsMultistore 14\n", null, "importstatus.log");

        $result = $this->db_do("DELETE FROM $catalog_category_product_for_delete_temp WHERE category_id = new_category_id");


        $result = $this->db_do("
            DELETE ccp
            FROM $catalog_category_product ccp
            JOIN $catalog_category_product_for_delete_temp ccpfd
                ON ccp.product_id = ccpfd.product_id
                AND ccp.category_id = ccpfd.category_id");

        Mage::log("\n     replaceMagentoProductsMultistore 15\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_category_product
                (category_id,  product_id)
            (SELECT
                scm.shop_entity_id,
                cpe.entity_id
            FROM $catalog_product_entity cpe
            JOIN $products_temp p
                ON cpe.store_product_id = p.store_product_id
            JOIN $stINch_categories_mapping scm
                ON p.store_category_id = scm.store_category_id
            )
            ON DUPLICATE KEY UPDATE
                product_id = cpe.entity_id");


        Mage::log("\n     replaceMagentoProductsMultistore 15.1 (add multi categories)\n", null, "importstatus.log");

        $result = $this->db_do("
        INSERT INTO $catalog_category_product
        (category_id,  product_id)
        (SELECT
         scm.shop_entity_id,
         cpe.entity_id
         FROM $catalog_product_entity cpe
         JOIN $products_temp p
         ON cpe.store_product_id = p.store_product_id
         JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_product_categories') . " spc
         ON p.store_product_id=spc.store_product_id
         JOIN $stINch_categories_mapping scm
         ON spc.store_category_id = scm.store_category_id
        )
        ON DUPLICATE KEY UPDATE
        product_id = cpe.entity_id
        ");

        Mage::log("\n     replaceMagentoProductsMultistore 16\n", null, "importstatus.log");

        //Indexing products and categories in the shop
        $result = $this->db_do("
            DELETE ccpi
            FROM $catalog_category_product_index ccpi
            LEFT JOIN $catalog_product_entity cpe
                ON ccpi.product_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        Mage::log("\n     replaceMagentoProductsMultistore 16.2\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_category_product_index
                (category_id, product_id, position, is_parent, store_id, visibility)
            (SELECT
                a.category_id,
                a.product_id,
                a.position,
                1,
                b.store_id,
                4
            FROM $catalog_category_product a
            JOIN $core_store b
            )
            ON DUPLICATE KEY UPDATE
                visibility = 4");

        Mage::log("\n     replaceMagentoProductsMultistore 17\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT ignore INTO $catalog_category_product_index
                (category_id, product_id, position, is_parent, store_id, visibility)
            (SELECT
                rc.root_cat,
                a.product_id,
                a.position,
                1,
                b.store_id,
                4
            FROM $catalog_category_product a
            JOIN $root_cats rc
                ON a.category_id = rc.entity_id
            JOIN $core_store b
            )
            ON DUPLICATE KEY UPDATE
                visibility = 4");

        Mage::log("\n     replaceMagentoProductsMultistore 18\n", null, "importstatus.log");

        //Set product name for specific web sites
        $result = $this->db_do("
            DELETE cpev
            FROM $catalog_product_entity_varchar cpev
            LEFT JOIN $catalog_product_entity cpe
                ON cpev.entity_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_name,
                w.website,
                a.entity_id,
                b.product_name
            FROM $catalog_product_entity a
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.product_name");

        Mage::log("\n     replaceMagentoProductsMultistore 19\n", null, "importstatus.log");

        // product name for all web sites
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_name,
                0,
                a.entity_id,
                b.product_name
            FROM $catalog_product_entity a
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.product_name");

        Mage::log("\n     replaceMagentoProductsMultistore 20\n", null, "importstatus.log");

        $this->dropHTMLentities($this->_getProductEntityTypeId(), $this->_getProductAttributeId('name'));
        $this->addDescriptions();
        $this->cleanProductDistributors();
        if ($this->product_file_format == "NEW") {
            $this->addReviews();
            $this->addWeight();
            $this->addSearchCache();
            $this->addPdfUrl();
            $this->addShortDescriptions();
            $this->addProductDistributors();
        }
        $this->addEAN();
        $this->addSpecification();
        $this->addManufacturers();

        Mage::log("\n     replaceMagentoProductsMultistore 21\n", null, "importstatus.log");

        //Enabling product index.
        $result = $this->db_do("
            DELETE cpei
            FROM $catalog_product_enabled_index cpei
            LEFT JOIN $catalog_product_entity cpe
                ON cpei.product_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        Mage::log("\n     replaceMagentoProductsMultistore 22\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_product_enabled_index
                (product_id, store_id, visibility)
            (SELECT
                a.entity_id,
                w.website,
                4
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                visibility = 4");

        Mage::log("\n     replaceMagentoProductsMultistore 23\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_product_enabled_index
                (product_id, store_id, visibility)
            (SELECT
                a.entity_id,
                0,
                4
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                visibility = 4");

        Mage::log("\n     replaceMagentoProductsMultistore 24\n", null, "importstatus.log");
        # Uche : Changed value = 4 to value = value on duplicate
        # Reason : To maintain product visibility value or our items
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_visibility,
                w.website,
                a.entity_id,
                4
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = value"); #3

        Mage::log("\n     replaceMagentoProductsMultistore 25\n", null, "importstatus.log");
        # Uche : Changed value = 4 to value = value on duplicate
        # Reason : To maintain product visibility value or our items
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id,  attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_visibility,
                0,
                a.entity_id,
                4
            FROM $catalog_product_entity a
            )
            ON DUPLICATE KEY UPDATE
                value = value");#4

        Mage::log("\n     replaceMagentoProductsMultistore 26\n", null, "importstatus.log");

        $result = $this->db_do("
            DELETE cpw
            FROM $catalog_product_website cpw
            LEFT JOIN $catalog_product_entity cpe
                ON cpw.product_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        Mage::log("\n     replaceMagentoProductsMultistore 27\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_product_website
                (product_id, website_id)
            (SELECT
                a.entity_id,
                w.website_id
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                product_id = a.entity_id,
                website_id = w.website_id");

        Mage::log("\n     replaceMagentoProductsMultistore 28\n", null, "importstatus.log");

        //Adding tax class "Taxable Goods"
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_tax_class_id,
                w.website,
                a.entity_id,
                2
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = 2");

        Mage::log("\n     replaceMagentoProductsMultistore 29\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_tax_class_id,
                0,
                a.entity_id,
                2
            FROM $catalog_product_entity a
            )
            ON DUPLICATE KEY UPDATE
                value = 2");

        Mage::log("\n     replaceMagentoProductsMultistore 30\n", null, "importstatus.log");

        // Load url Image
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_image,
                w.store_id,
                a.entity_id,
                b.main_image_url
            FROM $catalog_product_entity a
            JOIN $core_store w
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.main_image_url");

        Mage::log("\n     replaceMagentoProductsMultistore 31\n", null, "importstatus.log");

        // image for specific web sites
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_image,
                0,
                a.entity_id,
                b.main_image_url
            FROM $catalog_product_entity a
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.main_image_url");

        Mage::log("\n     replaceMagentoProductsMultistore 32\n", null, "importstatus.log");

        // small_image for specific web sites
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_small_image,
                w.store_id,
                a.entity_id,
                b.medium_image_url
            FROM $catalog_product_entity a
            JOIN $core_store w
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.medium_image_url");

        Mage::log("\n     replaceMagentoProductsMultistore 33\n", null, "importstatus.log");

        // small_image for all web sites
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id,  attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_small_image,
                0,
                a.entity_id,
                b.medium_image_url
            FROM $catalog_product_entity a
            JOIN $core_store w
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.medium_image_url");

        Mage::log("\n     replaceMagentoProductsMultistore 34\n", null, "importstatus.log");

        // thumbnail for specific web site
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_thumbnail,
                w.store_id,
                a.entity_id,
                b.thumb_image_url
            FROM $catalog_product_entity a
            JOIN $core_store w
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.thumb_image_url");

        Mage::log("\n     replaceMagentoProductsMultistore 35\n", null, "importstatus.log");

        // thumbnail for all web sites
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_thumbnail,
                0,
                a.entity_id,
                b.thumb_image_url
            FROM $catalog_product_entity a
            JOIN $core_store w
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.thumb_image_url");

        Mage::log("\n     replaceMagentoProductsMultistore 36\n", null, "importstatus.log");

        $this->addRelatedProducts();
        Mage::log("\n     replaceMagentoProductsMultistore 41\n", null, "importstatus.log");
    }


    public function replaceMagentoProductsMultistoreMERGE($coincidence)
    {
        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 1\n", null, "importstatus.log");

        $connection = Mage::getModel('core/resource')->getConnection('core_write');

        $products_temp = Mage::getSingleton('core/resource')->getTableName('products_temp');
        $products_website_temp = Mage::getSingleton('core/resource')->getTableName('products_website_temp');
        $catalog_product_entity = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity');
        $catalog_product_entity_int = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int');
        $catalog_product_entity_varchar = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar');
        $catalog_category_product = Mage::getSingleton('core/resource')->getTableName('catalog_category_product');
        $stINch_products_mapping = Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping');
        $stINch_products = Mage::getSingleton('core/resource')->getTableName('stINch_products');
        $catalog_category_entity = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity');
        $stINch_categories_mapping = Mage::getSingleton('core/resource')->getTableName('stINch_categories_mapping');
        $catalog_category_product_index = Mage::getSingleton('core/resource')->getTableName('catalog_category_product_index');
        $core_store = Mage::getSingleton('core/resource')->getTableName('core_store');
        $catalog_product_enabled_index = Mage::getSingleton('core/resource')->getTableName('catalog_product_enabled_index');
        $catalog_product_website = Mage::getSingleton('core/resource')->getTableName('catalog_product_website');
        //$catalogsearch_fulltext = Mage::getSingleton('core/resource')->getTableName('catalogsearch_fulltext');
        //$catalogsearch_query = Mage::getSingleton('core/resource')->getTableName('catalogsearch_query');
        $catalog_category_entity_varchar = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity_varchar');

        $_getProductEntityTypeId = $this->_getProductEntityTypeId();
        $_defaultAttributeSetId = $this->_getProductDefaulAttributeSetId();

        $attr_atatus = $this->_getProductAttributeId('status');
        $attr_name = $this->_getProductAttributeId('name');
        $attr_visibility = $this->_getProductAttributeId('visibility');
        $attr_tax_class_id = $this->_getProductAttributeId('tax_class_id');
        $attr_image = $this->_getProductAttributeId('image');
        $attr_small_image = $this->_getProductAttributeId('small_image');
        $attr_thumbnail = $this->_getProductAttributeId('thumbnail');

        $cat_attr_name = $this->_getCategoryAttributeId('name');
        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 2\n", null, "importstatus.log");

        //clear products, inserting new products and updating old others.
        $query = "
            DELETE cpe
            FROM $catalog_product_entity cpe
            JOIN $stINch_products_mapping pm
                ON cpe.entity_id = pm.entity_id
            WHERE pm.shop_store_product_id IS NOT NULL
                AND pm.store_product_id IS NULL";
        $result = $this->db_do($query);

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 3\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_product_entity
                (entity_id, entity_type_id, attribute_set_id, type_id, sku, updated_at, has_options, store_product_id, sinch_product_id)
            (SELECT
                pm.entity_id,
                $_getProductEntityTypeId,
                $_defaultAttributeSetId,
                'simple',
                a.product_sku,
                NOW(),
                0,
                a.store_product_id,
                a.sinch_product_id
            FROM $products_temp a
            LEFT JOIN $stINch_products_mapping pm
                ON a.store_product_id = pm.store_product_id
                AND a.sinch_product_id = pm.sinch_product_id
            WHERE pm.entity_id IS NOT NULL
            )
            ON DUPLICATE KEY UPDATE
                sku = a.product_sku,
                store_product_id = a.store_product_id,
                sinch_product_id = a.sinch_product_id");
        // store_product_id = a.store_product_id,
        // sinch_product_id = a.sinch_product_id

        $result = $this->db_do("
            INSERT INTO $catalog_product_entity
                (entity_id, entity_type_id, attribute_set_id, type_id, sku, updated_at, has_options, store_product_id, sinch_product_id)
            (SELECT
                pm.entity_id,
                $_getProductEntityTypeId,
                $_defaultAttributeSetId,
                'simple',
                a.product_sku,
                NOW(),
                0,
                a.store_product_id,
                a.sinch_product_id
            FROM $products_temp a
            LEFT JOIN $stINch_products_mapping pm
                ON a.store_product_id = pm.store_product_id
                AND a.sinch_product_id = pm.sinch_product_id
            WHERE pm.entity_id IS NULL
            )
            ON DUPLICATE KEY UPDATE
                sku = a.product_sku,
                store_product_id = a.store_product_id,
                sinch_product_id = a.sinch_product_id");
        // store_product_id = a.store_product_id,
        // sinch_product_id = a.sinch_product_id

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 4\n", null, "importstatus.log");

        //Set enabled
        $result = $this->db_do("
            DELETE cpei
            FROM $catalog_product_entity_int cpei
            LEFT JOIN $catalog_product_entity cpe
                ON cpei.entity_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_atatus,
                w.website,
                a.entity_id,
                1
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = 1");


        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 5\n", null, "importstatus.log");


        // set status = 1 for all stores
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_atatus,
                0,
                a.entity_id,
                1
            FROM $catalog_product_entity a
            )
            ON DUPLICATE KEY UPDATE
                value = 1");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 6\n", null, "importstatus.log");

        //Unifying products with categories.
        $result = $this->db_do("
            DELETE ccp
            FROM $catalog_category_product ccp
            LEFT JOIN $catalog_product_entity cpe
                ON ccp.product_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 7\n", null, "importstatus.log");

        $root_cats = Mage::getSingleton('core/resource')->getTableName('root_cats');

        $result = $this->db_do("DROP TABLE IF EXISTS $root_cats");
        $result = $this->db_do("
CREATE TABLE $root_cats
SELECT
    entity_id,
    path,
    SUBSTRING(path, LOCATE('/', path)+1) AS short_path,
    LOCATE('/', SUBSTRING(path, LOCATE('/', path)+1)) AS end_pos,
    SUBSTRING(SUBSTRING(path, LOCATE('/', path)+1), 1, LOCATE('/', SUBSTRING(path, LOCATE('/', path)+1))-1) as root_cat
FROM $catalog_category_entity
");
        $result = $this->db_do("UPDATE $root_cats SET root_cat = entity_id WHERE CHAR_LENGTH(root_cat) = 0");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 8\n", null, "importstatus.log");

        $result = $this->db_do("
            UPDATE IGNORE $catalog_category_product ccp
            LEFT JOIN $catalog_category_entity cce
                ON ccp.category_id = cce.entity_id
            JOIN $root_cats rc
                ON cce.entity_id = rc.entity_id
            SET ccp.category_id = rc.root_cat
            WHERE cce.entity_id IS NULL");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 9\n", null, "importstatus.log");

        $result = $this->db_do("
            DELETE ccp
            FROM $catalog_category_product ccp
            LEFT JOIN $catalog_category_entity cce
                ON ccp.category_id = cce.entity_id
            WHERE cce.entity_id IS NULL");

        $stinch_products_delete = Mage::getSingleton('core/resource')->getTableName('stinch_products_delete');

        $result = $this->db_do("DROP TABLE IF EXISTS $stinch_products_delete");
        $result = $this->db_do("
CREATE TABLE $stinch_products_delete
SELECT cpe.entity_id
FROM $catalog_product_entity cpe
WHERE cpe.entity_id NOT IN
(
SELECT cpe2.entity_id
FROM $catalog_product_entity cpe2
JOIN $stINch_products sp
    ON cpe2.sinch_product_id = sp.sinch_product_id
)");

        $result = $this->db_do("DELETE cpe FROM $catalog_product_entity cpe JOIN $stinch_products_delete spd USING(entity_id)");

        $result = $this->db_do("DROP TABLE IF EXISTS $stinch_products_delete");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 15\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_category_product
                (category_id,  product_id)
            (SELECT
                scm.shop_entity_id,
                cpe.entity_id
            FROM $catalog_product_entity cpe
            JOIN $products_temp p
                ON cpe.store_product_id = p.store_product_id
            JOIN $stINch_categories_mapping scm
                ON p.store_category_id = scm.store_category_id
            )
            ON DUPLICATE KEY UPDATE
                product_id = cpe.entity_id");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 15.1 (add multi categories)\n", null, "importstatus.log");

        $result = $this->db_do("
        INSERT INTO $catalog_category_product
        (category_id,  product_id)
        (SELECT
         scm.shop_entity_id,
         cpe.entity_id
         FROM $catalog_product_entity cpe
         JOIN $products_temp p
         ON cpe.store_product_id = p.store_product_id
         JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_product_categories') . " spc
         ON p.store_product_id=spc.store_product_id
         JOIN $stINch_categories_mapping scm
         ON spc.store_category_id = scm.store_category_id
        )
        ON DUPLICATE KEY UPDATE
        product_id = cpe.entity_id
        ");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 16\n", null, "importstatus.log");

        //Indexing products and categories in the shop
        $result = $this->db_do("
            DELETE ccpi
            FROM $catalog_category_product_index ccpi
            LEFT JOIN $catalog_product_entity cpe
                ON ccpi.product_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 16.2\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_category_product_index
                (category_id, product_id, position, is_parent, store_id, visibility)
            (SELECT
                a.category_id,
                a.product_id,
                a.position,
                1,
                b.store_id,
                4
            FROM $catalog_category_product a
            JOIN $core_store b
            )
            ON DUPLICATE KEY UPDATE
                visibility = 4");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 17\n", null, "importstatus.log");
        $root_cats = Mage::getSingleton('core/resource')->getTableName('root_cats');
// !!! $this->_root_cat
        $result = $this->db_do("
            INSERT ignore INTO $catalog_category_product_index
                (category_id, product_id, position, is_parent, store_id, visibility)
            (SELECT
                rc.root_cat,
                a.product_id,
                a.position,
                1,
                b.store_id,
                4
            FROM $catalog_category_product a
            JOIN $root_cats rc
                ON a.category_id = rc.entity_id
            JOIN $core_store b
            )
            ON DUPLICATE KEY UPDATE
                visibility = 4");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 18\n", null, "importstatus.log");

        //Set product name for specific web sites
        $result = $this->db_do("
            DELETE cpev
            FROM $catalog_product_entity_varchar cpev
            LEFT JOIN $catalog_product_entity cpe
                ON cpev.entity_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_name,
                w.website,
                a.entity_id,
                b.product_name
            FROM $catalog_product_entity a
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.product_name");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 19\n", null, "importstatus.log");

        // product name for all web sites
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_name,
                0,
                a.entity_id,
                b.product_name
            FROM $catalog_product_entity a
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.product_name");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 20\n", null, "importstatus.log");

        $this->dropHTMLentities($this->_getProductEntityTypeId(), $this->_getProductAttributeId('name'));
        $this->addDescriptions();
        $this->cleanProductDistributors();
        if (!$this->_ignore_product_contracts) {
            $this->cleanProductContracts();
        }
        if ($this->product_file_format == "NEW") {
            $this->addReviews();
            $this->addWeight();
            $this->addSearchCache();
            $this->addPdfUrl();
            $this->addShortDescriptions();
            $this->addProductDistributors();
            if (!$this->_ignore_product_contracts) {
                $this->addProductContracts();
            }
        }
        $this->addEAN();
        $this->addSpecification();
        $this->addManufacturers();

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 21\n", null, "importstatus.log");

        //Enabling product index.
        $result = $this->db_do("
            DELETE cpei
            FROM $catalog_product_enabled_index cpei
            LEFT JOIN $catalog_product_entity cpe
                ON cpei.product_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");


        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 22\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_product_enabled_index
                (product_id, store_id, visibility)
            (SELECT
                a.entity_id,
                w.website,
                4
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                visibility = 4");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 23\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_product_enabled_index
                (product_id, store_id, visibility)
            (SELECT
                a.entity_id,
                0,
                4
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                visibility = 4");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 24\n", null, "importstatus.log");
        # Uche : Changed value = 4 to value = value on duplicate
        # Reason : To maintain product visibility value or our items
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_visibility,
                w.website,
                a.entity_id,
                4
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = value"); #5

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 25\n", null, "importstatus.log");
        # Uche : Changed value = 4 to value = value on duplicate
        # Reason : To maintain product visibility value or our items
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id,  attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_visibility,
                0,
                a.entity_id,
                4
            FROM $catalog_product_entity a
            )
            ON DUPLICATE KEY UPDATE
                value = value"); #6

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 26\n", null, "importstatus.log");

        $result = $this->db_do("
            DELETE cpw
            FROM $catalog_product_website cpw
            LEFT JOIN $catalog_product_entity cpe
                ON cpw.product_id = cpe.entity_id
            WHERE cpe.entity_id IS NULL");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 27\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_product_website
                (product_id, website_id)
            (SELECT
                a.entity_id,
                w.website_id
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                product_id = a.entity_id,
                website_id = w.website_id");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 28\n", null, "importstatus.log");

        //Adding tax class "Taxable Goods"
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_tax_class_id,
                w.website,
                a.entity_id,
                2
            FROM $catalog_product_entity a
            JOIN $products_website_temp w
                ON a.store_product_id = w.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = 2");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 29\n", null, "importstatus.log");

        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_int
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_tax_class_id,
                0,
                a.entity_id,
                2
            FROM $catalog_product_entity a
            )
            ON DUPLICATE KEY UPDATE
                value = 2");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 30\n", null, "importstatus.log");

        // Load url Image
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_image,
                w.store_id,
                a.entity_id,
                b.main_image_url
            FROM $catalog_product_entity a
            JOIN $core_store w
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.main_image_url");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 31\n", null, "importstatus.log");

        // image for specific web sites
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_image,
                0,
                a.entity_id,
                b.main_image_url
            FROM $catalog_product_entity a
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.main_image_url");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 32\n", null, "importstatus.log");

        // small_image for specific web sites
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_small_image,
                w.store_id,
                a.entity_id,
                b.medium_image_url
            FROM $catalog_product_entity a
            JOIN $core_store w
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.medium_image_url");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 33\n", null, "importstatus.log");

        // small_image for all web sites
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id,  attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_small_image,
                0,
                a.entity_id,
                b.medium_image_url
            FROM $catalog_product_entity a
            JOIN $core_store w
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.medium_image_url");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 34\n", null, "importstatus.log");

        // thumbnail for specific web site
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_thumbnail,
                w.store_id,
                a.entity_id,
                b.thumb_image_url
            FROM $catalog_product_entity a
            JOIN $core_store w
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.thumb_image_url");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 35\n", null, "importstatus.log");

        // thumbnail for all web sites
        $result = $this->db_do("
            INSERT INTO $catalog_product_entity_varchar
                (entity_type_id, attribute_id, store_id, entity_id, value)
            (SELECT
                $_getProductEntityTypeId,
                $attr_thumbnail,
                0,
                a.entity_id,
                b.thumb_image_url
            FROM $catalog_product_entity a
            JOIN $core_store w
            JOIN $products_temp b
                ON a.store_product_id = b.store_product_id
            )
            ON DUPLICATE KEY UPDATE
                value = b.thumb_image_url");

        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 36\n", null, "importstatus.log");

        $this->addRelatedProducts();
        Mage::log("\n     replaceMagentoProductsMultistoreMERGE 41\n", null, "importstatus.log");
    }


    function ParseProductsPicturesGallery()
    {
        $parse_file = $this->varDir . FILE_PRODUCTS_PICTURES_GALLERY;
        if (filesize($parse_file)) {
            $this->_LOG("Start parse " . FILE_PRODUCTS_PICTURES_GALLERY);
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('products_pictures_gallery_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('products_pictures_gallery_temp') . " (
                sinch_product_id int(11),
                                 image_url varchar(255),
                                 thumb_image_url varchar(255),
                                 store_product_id int(11),
                                 key(sinch_product_id),
                                 key(store_product_id)
                                     )");

            $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                                                   INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('products_pictures_gallery_temp') . "
                                                   FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                                                   OPTIONALLY ENCLOSED BY '\"'
                                                   LINES TERMINATED BY \"\r\n\"
                                                   IGNORE 1 LINES ");

            $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('products_pictures_gallery_temp') . " ppgt
                          JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products') . " sp
                            ON ppgt.sinch_product_id=sp.sinch_product_id
                          JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                            ON sp.store_product_id=cpe.store_product_id
                          SET ppgt.store_product_id=sp.store_product_id");

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_products_pictures_gallery'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('products_pictures_gallery_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_products_pictures_gallery'));

            $this->_LOG("Finish parse" . FILE_PRODUCTS_PICTURES_GALLERY);
        } else {
            $this->_LOG("Wrong file" . $parse_file);
        }
        $this->_LOG(" ");
    }

    function ParseRestrictedValues()
    {
        $parse_file = $this->varDir . FILE_RESTRICTED_VALUES;
        if (filesize($parse_file) || $this->_ignore_restricted_values) {
            $this->_LOG("Start parse " . FILE_RESTRICTED_VALUES);
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('restricted_values_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('restricted_values_temp') . " (
                            restricted_value_id int(11),
                            category_feature_id int(11),
                            text text,
                            display_order_number int(11),
                            KEY(restricted_value_id),
                            KEY(category_feature_id)
                          )");
            if (!$this->_ignore_restricted_values) {
                $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                              INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('restricted_values_temp') . "
                              FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                              OPTIONALLY ENCLOSED BY '\"'
                              LINES TERMINATED BY \"\r\n\"
                              IGNORE 1 LINES ");
            }
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_restricted_values'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('restricted_values_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_restricted_values'));

            $this->_LOG("Finish parse " . FILE_RESTRICTED_VALUES);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(" ");
    }

    function ParseStockAndPrices()
    {
        $parse_file = $this->varDir . FILE_STOCK_AND_PRICES;

        if (filesize($parse_file)) {
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp_before'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp_before') . " (
                                 store_product_id int(11),
                                 stock int(11),
                                 price varchar(255),
                                 cost varchar(255),
                                 distributor_id int(11),
                                 KEY(store_product_id),
                                 KEY(distributor_id)
                          )");

            $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                          INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp_before') . "
                          FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                          OPTIONALLY ENCLOSED BY '\"'
                          LINES TERMINATED BY \"\r\n\"
                          IGNORE 1 LINES ");

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp') . " (
                                 store_product_id int(11),
                                 stock int(11),
                                 price decimal(15,4),
                                 cost decimal(15,4),
                                 distributor_id int(11),
                                 KEY(store_product_id),
                                 KEY(distributor_id)
                          )");

            $this->db_do("
                        INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp') . " (
                             store_product_id,
                             stock,
                             price,
                             cost,
                             distributor_id
                        )(
                          SELECT
                             store_product_id,
                             stock,
                             REPLACE(price, ',', '.'),
                             REPLACE(cost, ',', '.' ),
                             distributor_id
                          FROM " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp_before') . "
                        )
                      ");
            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp_before'));

            $this->replaceMagentoProductsStockPrice();

            $res = $this->db_do("SELECT count(*) as cnt
                                 FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                 INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp') . " b
                                    ON a.store_product_id=b.store_product_id");
            $row = mysqli_fetch_assoc($res);
            $this->db_do("UPDATE " . $this->import_status_statistic_table . "
                          SET number_of_products=" . $row['cnt'] . "
                          WHERE id=" . $this->current_import_status_statistic_id);

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_stock_and_prices'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_stock_and_prices'));

            $this->_LOG("Finish parse" . FILE_RELATED_PRODUCTS);
        } else {
            $this->_LOG("Wrong file" . $parse_file);
        }
        $this->_LOG(" ");
    }

    function replaceMagentoProductsStockPrice()
    {
        //Add stock
        $connection = Mage::getModel('core/resource')->getConnection('core_write');
        $result = $this->db_do("DELETE csi
                                FROM " . Mage::getSingleton('core/resource')->getTableName('cataloginventory_stock_item') . " csi
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                    ON csi.product_id=cpe.entity_id
                                WHERE cpe.entity_id is null");
        //set all sinch products stock=0 before upgrade (nedds for dayly stock&price import)

        $result = $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                JOIN " . Mage::getSingleton('core/resource')->getTableName('cataloginventory_stock_item') . " csi
                                    ON cpe.entity_id=csi.product_id
                                SET
                                    csi.qty=0,
                                    csi.is_in_stock=0
                                WHERE cpe.store_product_id IS NOT NULL");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('cataloginventory_stock_item') . " (
                                    product_id,
                                    stock_id,
                                    qty,
                                    is_in_stock,
                                    manage_stock
                                )(
                                  SELECT
                                    a.entity_id,
                                    1,
                                    b.stock,
                                    IF(b.stock > 0, 1, 0),
                                    1
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp') . " b
                                    ON a.store_product_id=b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    qty=b.stock,
                                    is_in_stock = IF(b.stock > 0, 1, 0),
                                    manage_stock = 1
                              ");


        $result = $this->db_do("DELETE FROM " . Mage::getSingleton('core/resource')->getTableName('cataloginventory_stock_status'));

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('cataloginventory_stock_status') . " (
                                    product_id,
                                    website_id,
                                    stock_id,
                                    qty,
                                    stock_status
                                )(
                                  SELECT
                                    a.product_id,
                                    w.website_id,
                                    1,
                                    a.qty,
                                    IF(qty > 0, 1, 0)
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('cataloginventory_stock_item') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " b
                                    ON a.product_id=b.entity_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON b.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    qty=a.qty,
                                    stock_status = IF(a.qty > 0, 1, 0)
                              ");

        //Add prices
        $result = $this->db_do("DELETE cped
                                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_decimal') . " cped
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                    ON cped.entity_id=cpe.entity_id
                                WHERE cpe.entity_id IS NULL");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_decimal') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('price') . ",
                                    w.website,
                                    a.entity_id,
                                    b.price
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . "   a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp') . " b
                                    ON a.store_product_id=b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.price
                              ");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_decimal') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('price') . ",
                                    0,
                                    a.entity_id,
                                    b.price
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . "  a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp') . " b
                                    ON a.store_product_id=b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.price
                ");
        //Add cost
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_decimal') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('cost') . ",
                                    w.website,
                                    a.entity_id,
                                    b.cost
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . "   a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp') . " b
                                    ON a.store_product_id=b.store_product_id
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                    ON a.store_product_id=w.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.cost
                              ");

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_decimal') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('cost') . ",
                                    0,
                                    a.entity_id,
                                    b.cost
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp') . " b
                                    ON a.store_product_id=b.store_product_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.cost
                              ");

        //make products enable in FO
        $result = $this->db_do("DELETE cpip
                                FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_index_price') . " cpip
                                LEFT JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                                    ON cpip.entity_id=cpe.entity_id
                                WHERE cpe.entity_id IS NULL");

        $q = "SELECT customer_group_id FROM " . Mage::getSingleton('core/resource')->getTableName('customer_group');
        $quer = $this->db_do($q);

        while ($row = mysqli_fetch_assoc($quer)) {
            $result = $this->db_do("
                                    INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_index_price') . " (
                                        entity_id,
                                        customer_group_id,
                                        website_id,
                                        tax_class_id,
                                        price,
                                        final_price,
                                        min_price,
                                        max_price
                                    )(SELECT
                                        a.entity_id,
                                        " . $row['customer_group_id'] . ",
                                        w.website_id,
                                        2,
                                        b.price ,
                                        b.price ,
                                        b.price ,
                                        b.price
                                      FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . "  a
                                      INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stock_and_prices_temp') . " b
                                        ON a.store_product_id=b.store_product_id
                                      INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('products_website_temp') . " w
                                        ON a.store_product_id=w.store_product_id
                                    )
                                    ON DUPLICATE KEY UPDATE
                                        tax_class_id = 2,
                                        price = b.price,
                                        final_price = b.price,
                                        min_price = b.price,
                                        max_price = b.price
                                  ");
        }
    }

    private function _dropFeatureResultTables()
    {
        $dbName = Mage::getConfig()->getResourceConnectionConfig('core_setup')->dbname;
        $filterResultTablePrefix = Mage::getSingleton('core/resource')->getTableName('SinchFilterResult_');

        $resultTables = $this->db_do("SHOW TABLES LIKE '$filterResultTablePrefix%'");

        if (empty($resultTables->num_rows)) {
            return false;
        }

        $dropSqls = array();
        $dropSqls[] = "SET FOREIGN_KEY_CHECKS = 0;";
        $dropSqls[] = "SET GROUP_CONCAT_MAX_LEN=150000;";
        $dropSqls[] = "SET @tbls = (SELECT GROUP_CONCAT(TABLE_NAME)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME LIKE '$filterResultTablePrefix%');";
        $dropSqls[] = "SET @delStmt = CONCAT('DROP TABLE ',  @tbls);";
        $dropSqls[] = "PREPARE stmt FROM @delStmt;";
        $dropSqls[] = "EXECUTE stmt;";
        $dropSqls[] = "DEALLOCATE PREPARE stmt;";
        $dropSqls[] = "SET FOREIGN_KEY_CHECKS = 1;";
        foreach ($dropSqls as $key => $dropSql) {
            $this->db_do($dropSql);
        }

        return true;
    }

    private function _cleanCateoryProductFlatTable()
    {
        $dbConf = Mage::getConfig()->getResourceConnectionConfig('core_setup');
        $q = 'SHOW TABLES LIKE "' . Mage::getSingleton('core/resource')->getTableName('catalog_product_flat_') . '%"';
        $quer = $this->db_do($q);
        $result = false;
        While ($row = mysqli_fetch_array($quer)) {
            if (is_array($row)) {
                $catalog_product_flat = array_pop($row);
                $q = 'DELETE pf1 FROM ' . $catalog_product_flat . ' pf1
                    LEFT JOIN ' . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . ' p
                        ON pf1.entity_id = p.entity_id
                    WHERE p.entity_id IS NULL;';
                $this->db_do($q);
                $this->_LOG('cleaned wrong rows from ' . $catalog_product_flat);
            }
        }
        return $result;

    }

    public function runIndexer()
    {
        $this->db_do("DELETE FROM " . Mage::getSingleton('core/resource')->getTableName('core_url_rewrite'));
        $this->db_do("SET FOREIGN_KEY_CHECKS=0");
        $this->db_do("TRUNCATE TABLE " . Mage::getSingleton('core/resource')->getTableName('core_url_rewrite'));
        $this->db_do("SET FOREIGN_KEY_CHECKS=1");
        $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . "
                      SET value = ''
                      WHERE entity_type_id=" . $this->_getProductEntityTypeId() . " AND attribute_id=" . $this->_getProductAttributeId('url_key'));

        Mage::dispatchEvent('sinch_import_url_rewrite_before');

        exec(PHP_RUN_STRING . ' ' . $this->shellDir . 'indexer.php reindexall');
    }

    public function reindexManufacturers()
    {
        if (!Mage::helper('core')->isModuleEnabled('Magebuzz_Manufacturer')) {
            return false;
        }

        $helper = Mage::helper('manufacturer');
        $manufacturerOptions = $helper->getManufacturerOptions();
        Mage::getModel('manufacturer/manufacturer')->getAllManufacturer($manufacturerOptions);

        foreach ($manufacturerOptions as $manufacturer) {
            if ($manufacturer['value']) {
                $model = Mage::getModel('manufacturer/manufacturer');
                $model->load($manufacturer['value'], 'option_id');
                $identifier = $helper->generateIdentifier($manufacturer['label']);
                $storeId = 0;
                if (!$model->getId()) {
                    $model->setName($manufacturer['label'])
                        ->setOptionId($manufacturer['value'])
                        ->setIdentifier($identifier)
                        ->setStoreId($storeId)
                        ->setStatus(1)
                        ->save();
                } else {
                    $model->setName($manufacturer['label'])
                        ->setIdentifier($identifier)
                        ->setStoreId($storeId)
                        ->setStatus(1)
                        ->save();
                }
            }
        }

        // Reindex url rewrites
        $urls = Mage::getModel('core/url_rewrite')->getCollection()
            ->addFieldToFilter('id_path', array('like' => 'manufacturer%'));
        if (count($urls)) {
            foreach ($urls as $item) {
                $item->delete();
            }
        }
        $collection = Mage::getModel('manufacturer/manufacturer')->getCollection();
        if (count($collection)) {
            $helper = Mage::helper('manufacturer');
            $storeId = 0;
            foreach ($collection as $item) {
                if ($item->getIdentifier()) {
                    $rewriteModel = Mage::getModel('core/url_rewrite');
                    $identifier = $helper->generateIdentifier($item->getName());
                    $id_path = 'manufacturer/' . $item->getId();
                    $request_path = $helper->getConfigTextRouter() . '/' . $identifier;
                    $rewriteModel->loadByRequestPath($request_path);
                    if ($rewriteModel->getId()) {
                        $identifier = $identifier . '-' . $item->getId();
                        $request_path = $request_path . '-' . $item->getId();
                    }
                    $urlRewrite = Mage::getModel('core/url_rewrite');
                    $urlRewrite->setData('id_path', 'manufacturer/' . $item->getId());
                    $urlRewrite->setData('target_path', 'manufacturer/index/view/id/' . $item->getId());
                    $urlRewrite->setStoreId($storeId);
                    $urlRewrite->setData('request_path', $request_path);
                    $urlRewrite->save();

                    $item->setIdentifier($identifier)->save();
                }
            }
        }

        return true;
    }


    function cron_start_full_import()
    {
        # Moe - Uche : Using same method as AjaxController to execute import
        # Reason: Cron dies when executing addAttributeToFilter() function
        // $this->import_run_type = 'CRON';
        // $this->run_sinch_import();

        $dir = dirname(__FILE__);
        $php_run_string_array = explode(';', $this->php_run_strings);
        foreach($php_run_string_array as $php_run_string){
            exec("nohup ".$php_run_string." ".$dir."/../sinch_import_start_ajax.php > /dev/null & echo $!", $out);
            sleep(1);
            if (($out[0] > 0) && !$this->is_imort_not_run()){
                break;
            }
        }
    }


    function cron_start_stock_price_import()
    {
        # Moe - Uche : Using same method as AjaxController to execute import
        # Reason: Cron dies when executing addAttributeToFilter() function
        // $this->import_run_type = 'CRON';
        // $this->run_stock_price_sinch_import();

        $dir = dirname(__FILE__);
        $php_run_string_array = explode(';', $this->php_run_strings);
        foreach($php_run_string_array as $php_run_string){
            exec("nohup ".$php_run_string." ".$dir."/../stock_price_sinch_import_start_ajax.php > /dev/null & echo $!", $out);
            sleep(1);
            if (($out[0] > 0) && !$this->is_imort_not_run()){
                break;
            }
        }
    }

    function run_stock_price_sinch_import()
    {
        $safe_mode_set = ini_get('safe_mode');

        $this->InitImportStatuses('PRICE STOCK');
        if ($safe_mode_set) {
            $this->_LOG('safe_mode is enable. import stoped.');
            $this->set_import_error_reporting_message('Safe_mode is enabled. Please check the documentation on how to fix this. Import stopped.');
            throw new Exception("Safe mode is enabled");
        }
        $store_proc = $this->check_store_procedure_exist();

        if (!$store_proc) {
            $this->_LOG('store prcedure "' . Mage::getSingleton('core/resource')->getTableName('filter_sinch_products_s') . '" is absent in this database. import stoped.');
            $this->set_import_error_reporting_message('Stored procedure "' . Mage::getSingleton('core/resource')->getTableName('filter_sinch_products_s') . '" is absent in this database. Import stopped.');
            throw new Exception("filter_sinch_products_s missing from database");
        }

        $file_privileg = $this->check_db_privileges();

        if (!$file_privileg) {
            $this->_LOG("Loaddata option not set - please check the documentation on how to fix this. You dan't have privileges for LOAD DATA.");
            $this->set_import_error_reporting_message("Loaddata option not set - please check the documentation on how to fix this. Import stopped.");
            throw new Exception("LOAD DATA not enabled");
        }
        $local_infile = $this->check_local_infile();
        if (!$local_infile) {
            $this->_LOG("Loaddata option not set - please check the documentation on how to fix this. Add this string to  'set-variable=local-infile=0' in '/etc/my.cnf'");
            $this->set_import_error_reporting_message("Loaddata option not set - please check the documentation on how to fix this. Import stopped.");
            throw new Exception("LOCAL INFILE not enabled");
        }

        if ($this->is_imort_not_run() && $this->is_full_import_have_been_run()) {
            try {
                $q = "SELECT GET_LOCK('sinchimport', 30)";
                $this->db_do($q);
                $import = $this;
                $import->addImportStatus('Stock Price Start Import');
                Mage::log("Upload Files\n", null, "importstatus.log");
                $this->files = array(
                    FILE_DISTRIBUTORS,
                    FILE_DISTRIBUTORS_STOCK_AND_PRICES,
                    FILE_STOCK_AND_PRICES,
                    FILE_PRICE_RULES,
                    FILE_PRODUCT_CONTRACTS
                );

                $import->UploadFiles();
                $import->addImportStatus('Stock Price Upload Files');

                Mage::log("Parse Distributors\n", null, "importstatus.log");
                $import->ParseDistributors();
                if ($this->product_file_format == "NEW") {
                    $this->ParseDistributorsStockAndPrice();
                    $this->ParseProductContracts();
                }
                $import->addImportStatus('Parse Distributors');

                Mage::log("Parse Stock And Prices\n", null, "importstatus.log");
                $import->ParseStockAndPrices();
                $import->addImportStatus('Stock Price Parse Products');

                Mage::log("Apply Customer Group Price\n", null, "importstatus.log");
                //$import->ParsePriceRules();
                //$import->AddPriceRules();
                //$import->ApplyCustomerGroupPrice();

                Mage::log("Finish Stock & Price Sinch import", null, $this->_logFile);
                Mage::log("Finish Stock & Price Sinch import\n", null, "importstatus.log");

                Mage::log("Start indexing  Stock & Price", null, $this->_logFile);
                Mage::log("Start indexing  Stock & Price\n", null, "importstatus.log");
                $import->_cleanCateoryProductFlatTable();
                $import->runStockPriceIndexer();

                Mage::log("Finish indexing  Stock & Price", null, $this->_logFile);
                $import->addImportStatus('Stock Price Indexing data');
                $import->addImportStatus('Stock Price Finish import', 1);
                Mage::log("Finish indexing  Stock & Price\n", null, "importstatus.log");

                $q = "SELECT RELEASE_LOCK('sinchimport')";
                $this->db_do($q);

                $ftpCred = Mage::getStoreConfig('sinchimport_root/sinch_ftp');
                Mage::dispatchEvent('sinch_import_after', array('ftp_cred' => $ftpCred, 'full_import' => false));
            } catch (Exception $e) {
                $this->set_import_error_reporting_message($e);
            }
        } else {
            if (!$this->is_imort_not_run()) {
                Mage::log("Sinchimport already run", null, $this->_logFile);
                Mage::log("Sinchimport already run\n", null, "importstatus.log");
            } else {
                Mage::log("Full import have never finished with success", null, $this->_logFile);
                Mage::log("Full import have never finished with success\n", null, "importstatus.log");
            }
        }

    }

    function is_full_import_have_been_run()
    {
        $q = "SELECT COUNT(*) AS cnt
            FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_import_status_statistic') . "
            WHERE import_type='FULL' AND global_status_import='Successful'";
        $quer = $this->db_do($q);
        $row = mysqli_fetch_array($quer);
        if ($row['cnt'] > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function runStockPriceIndexer()
    {
        exec(PHP_RUN_STRING . ' ' . $this->shellDir . 'indexer.php --reindex catalog_product_price,cataloginventory_stock');
    }

    function ParsePriceRules()
    {
        $parse_file = $this->varDir . FILE_PRICE_RULES;
        if (filesize($parse_file) || $this->_ignore_price_rules) {
            $this->_LOG("Start parse " . FILE_PRICE_RULES);

            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('price_rules_temp'));
            $this->db_do("CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('price_rules_temp') . "(
                            `id` int(11) NOT NULL,
                            `price_from` decimal(10,2) DEFAULT NULL,
                            `price_to` decimal(10,2) DEFAULT NULL,
                            `category_id` int(10) unsigned DEFAULT NULL,
                            `vendor_id` int(11) DEFAULT NULL,
                            `vendor_product_id` varchar(255) DEFAULT NULL,
                            `customergroup_id` varchar(32) DEFAULT NULL,
                            `marge` decimal(10,2) DEFAULT NULL,
                            `fixed` decimal(10,2) DEFAULT NULL,
                            `final_price` decimal(10,2) DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `price_from` (`price_from`,`price_to`,`vendor_id`,`category_id`,`vendor_product_id`,`customergroup_id`),
                            KEY `vendor_product_id` (`vendor_product_id`),
                            KEY `category_id` (`category_id`)
                          )
                        ");
            if (!$this->_ignore_price_rules) {
                $this->db_do("LOAD DATA LOCAL INFILE '" . $parse_file . "'
                              INTO TABLE " . Mage::getSingleton('core/resource')->getTableName('price_rules_temp') . "
                              FIELDS TERMINATED BY '" . $this->field_terminated_char . "'
                              OPTIONALLY ENCLOSED BY '\"'
                              LINES TERMINATED BY \"\r\n\"
                              IGNORE 1 LINES
                              (id, @vprice_from, @vprice_to, @vcategory_id, @vvendor_id, @vvendor_product_id, @vcustomergroup_id, @vmarge, @vfixed, @vfinal_price)
                              SET price_from         = nullif(@vprice_from,''),
                                  price_to           = nullif(@vprice_to,''),
                                  category_id        = nullif(@vcategory_id,''),
                                  vendor_id          = nullif(@vvendor_id,''),
                                  vendor_product_id  = nullif(@vvendor_product_id,''),
                                  customergroup_id   = nullif(@vcustomergroup_id,''),
                                  marge              = nullif(@vmarge,''),
                                  fixed              = nullif(@vfixed,''),
                                  final_price        = nullif(@vfinal_price,'')
                            ");
            }

            $this->db_do("ALTER TABLE " . Mage::getSingleton('core/resource')->getTableName('price_rules_temp') . "
                          ADD COLUMN `shop_category_id` int(10) unsigned DEFAULT NULL,
                          ADD COLUMN `shop_vendor_id` int(11) DEFAULT NULL,
                          ADD COLUMN `shop_vendor_product_id` varchar(255) DEFAULT NULL,
                          ADD COLUMN `shop_customergroup_id` varchar(32) DEFAULT NULL
                        ");

            $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('price_rules_temp') . " prt
                          JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . " cce
                            ON prt.category_id = cce.store_category_id
                          SET prt.shop_category_id = cce.entity_id");

            $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('price_rules_temp') . " prt
                          JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_manufacturers') . " sicm
                            ON prt.vendor_id = sicm.sinch_manufacturer_id
                          SET prt.shop_vendor_id = sicm.shop_option_id");

            $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('price_rules_temp') . " prt
                          JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping') . " sicpm
                            ON prt.vendor_product_id = sicpm.product_sku
                          SET prt.shop_vendor_product_id = sicpm.sku");

            $this->db_do("UPDATE " . Mage::getSingleton('core/resource')->getTableName('price_rules_temp') . " prt
                          JOIN " . Mage::getSingleton('core/resource')->getTableName('customer_group') . " cg
                            ON prt.customergroup_id = cg.customer_group_id
                          SET prt.shop_customergroup_id = cg.customer_group_id");

            $this->db_do("DELETE FROM " . Mage::getSingleton('core/resource')->getTableName('price_rules_temp') . "
                          WHERE
                            (category_id IS NOT NULL AND shop_category_id IS NULL) OR
                            (vendor_id IS NOT NULL AND shop_vendor_id IS NULL) OR
                            (vendor_product_id IS NOT NULL AND shop_vendor_product_id IS NULL) OR
                            (customergroup_id IS NOT NULL AND shop_customergroup_id IS NULL)
                        ");


            $this->db_do("DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_price_rules'));
            $this->db_do("RENAME TABLE " . Mage::getSingleton('core/resource')->getTableName('price_rules_temp') . "
                          TO " . Mage::getSingleton('core/resource')->getTableName('stINch_price_rules'));

            $this->_LOG("Finish parse " . FILE_PRICE_RULES);
        } else {
            $this->_LOG("Wrong file " . $parse_file);
        }
        $this->_LOG(" ");
    }

    function AddPriceRules()
    {
        if (!$this->check_table_exist('import_pricerules_standards')) {
            return;
        }

        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('import_pricerules') . " (
                                    id,
                                    price_from,
                                    price_to,
                                    vendor_id,
                                    category_id,
                                    vendor_product_id,
                                    customergroup_id,
                                    marge,
                                    fixed,
                                    final_price
                                )(SELECT
                                    id,
                                    price_from,
                                    price_to,
                                    shop_vendor_id,
                                    shop_category_id,
                                    shop_vendor_product_id,
                                    shop_customergroup_id,
                                    marge,
                                    fixed,
                                    final_price
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_price_rules') . " a
                               )
                                ON DUPLICATE KEY UPDATE
                                    id                  = a.id,
                                    price_from          = a.price_from,
                                    price_to            = a.price_to,
                                    vendor_id           = a.shop_vendor_id,
                                    category_id         = a.shop_category_id,
                                    vendor_product_id   = a.shop_vendor_product_id,
                                    customergroup_id    = a.shop_customergroup_id,
                                    marge               = a.marge,
                                    fixed               = a.fixed,
                                    final_price         = a.final_price
                              ");
    }

    private function check_table_exist($table)
    {
        $q = "SHOW TABLES LIKE \"%" . Mage::getSingleton('core/resource')->getTableName($table) . "%\"";
        $quer = $this->db_do($q);
        $i = 0;
        while ($row = mysqli_fetch_array($quer)) {
            $i++;
        }
        return ($i);
    }

    function ApplyCustomerGroupPrice()
    {
        if (!$this->check_table_exist('import_pricerules_standards')) {
            return;
        }
        $this->_getProductsForCustomerGroupPrice();
        $pricerulesArray = $this->_getPricerulesList();
        if (is_array($pricerulesArray)) {
            $this->db_do("TRUNCATE TABLE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_group_price'));
            $this->db_do("TRUNCATE TABLE " . Mage::getSingleton('core/resource')->getTableName('catalog_product_index_group_price'));
        }

        foreach ($pricerulesArray as $pricerule) {
            $this->_LOG("Calculation group price for rule " . $pricerule['id'] . "
                        (\nname          =   " . $pricerule['name'] . "
                         \nfinal_price   =   " . $pricerule['final_price'] . "
                         \nprice_from    =   " . $pricerule['price_from'] . "
                         \nprice_to      =   " . $pricerule['price_to'] . "
                         \nvendor_id     =   " . $pricerule['vendor_id'] . "
                         \ncategory_id   =   " . $pricerule['category_id'] . "
                         \nproduct_entity_id =   " . $pricerule['product_entity_id'] . "
                         \nvendor_product_id =   " . $pricerule['vendor_product_id'] . "
                         \ncustomergroup_id  =   " . $pricerule['customergroup_id'] . "
                         \ndistributor_id    =   " . $pricerule['distributor_id'] . "
                         \nrating            =   " . $pricerule['rating'] . "
                         \nmarge             =   " . $pricerule['marge'] . "
                         \nfixed             =   " . $pricerule['fixed'] . "
                         \nallow_subcat      =   " . $pricerule['allow_subcat'] . "
                         \nstore_id          =   " . $pricerule['store_id'] . "
                        )");

            $vendor_product_id_str = "'" . str_replace(';', "','", $pricerule['vendor_product_id']) . "'";
            $where = "";
            if (empty($pricerule['marge'])) $marge = "NULL";
            else $marge = $pricerule['marge'];

            if (empty($pricerule['fixed'])) $fixed = "NULL";
            else $fixed = $pricerule['fixed'];

            if (empty($pricerule['final_price'])) $final_price = "NULL";
            else $final_price = $pricerule['final_price'];

            if (!empty($pricerule['price_from'])) $where .= " AND a.price > " . $pricerule['price_from'];

            if (!empty($pricerule['price_to'])) $where .= " AND a.price < " . $pricerule['price_to'];

            if (!empty($pricerule['vendor_id'])) $where .= " AND a.manufacturer_id = " . $pricerule['vendor_id'];

            if (!empty($pricerule['product_entity_id'])) $where .= " AND a.product_id = '" . $pricerule['product_entity_id'] . "'";

            if (!empty($pricerule['vendor_product_id'])) $where .= " AND a.sku IN (" . $vendor_product_id_str . ")";

            if (!empty($pricerule['allow_subcat'])) {
                if (!empty($pricerule['category_id'])) {
                    $children_cat = $this->get_all_children_cat($pricerule['category_id']);
                    $where .= " AND a.category_id IN  (" . $children_cat . ")";
                }
            } else {
                if (!empty($pricerule['category_id'])) $where .= " AND a.category_id = " . $pricerule['category_id'];
            }

            $customer_group_id_array = array();
            if (strstr($pricerule['customergroup_id'], ",")) {
                $customer_group_id_array = explode(",", $pricerule['customergroup_id']);
            } else {
                $customer_group_id_array[0] = $pricerule['customergroup_id'];
            }
            foreach ($customer_group_id_array as $customer_group_id) {
                if (isset($customer_group_id) && $customer_group_id >= 0) {
                    $query = "
                    INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_group_price') . "                             (entity_id,
                     all_groups,
                     customer_group_id,
                     value,
                     website_id
                    )
                    (SELECT
                      a.product_id,
                      0,
                      " . $customer_group_id . ",
                      " . Mage::getSingleton('core/resource')->getTableName('func_calc_price') . "(
                                        a.price,
                                      " . $marge . " ,
                                      " . $fixed . ",
                                      " . $final_price . "),
                      0
                     FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_products_for_customer_group_price_temp') . " a
                     WHERE true " . $where . "
                    )
                    ON DUPLICATE KEY UPDATE
                    value =
                        " . Mage::getSingleton('core/resource')->getTableName('func_calc_price') . "(
                                        a.price,
                                        " . $marge . " ,
                                        " . $fixed . ",
                                        " . $final_price . ")
                    ";
                    $this->db_do($query);
                    if (!empty($pricerule['store_id']) && $pricerule['store_id'] > 0) {
                        $query = "
                      INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_index_group_price') . "                             (entity_id,
                              customer_group_id,
                              price,
                              website_id
                              )
                      (SELECT
                       a.product_id,
                       " . $customer_group_id . ",
                       " . Mage::getSingleton('core/resource')->getTableName('func_calc_price') . "(
                                  a.price,
                                  " . $marge . " ,
                                  " . $fixed . ",
                                  " . $final_price . "),
                      " . $pricerule['store_id'] . "
                       FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_products_for_customer_group_price_temp') . " a
                       WHERE true " . $where . "
                      )
                      ON DUPLICATE KEY UPDATE
                      price =
                      " . Mage::getSingleton('core/resource')->getTableName('func_calc_price') . "(
                              a.price,
                              " . $marge . " ,
                              " . $fixed . ",
                              " . $final_price . ")
                      ";
                        $this->db_do($query);
                    }
                }
            }
        }
    }

    function _getProductsForCustomerGroupPrice()
    {
        $this->db_do(" DROP TABLE IF EXISTS " . Mage::getSingleton('core/resource')->getTableName('stINch_products_for_customer_group_price_temp'));
        $this->db_do("
                CREATE TABLE " . Mage::getSingleton('core/resource')->getTableName('stINch_products_for_customer_group_price_temp') . "
                (
                 `category_id`       int(10) unsigned NOT NULL default '0',
                 `product_id`        int(10) unsigned NOT NULL default '0',
                 `store_product_id`  int(10) NOT NULL default '0',
                 `sku` varchar(64) DEFAULT NULL COMMENT 'SKU',
                 `manufacturer_id`  int(10) NOT NULL default '0',
                 `price` decimal(15,4) DEFAULT NULL,
                 UNIQUE KEY `UNQ_CATEGORY_PRODUCT` (`product_id`,`category_id`),
                 KEY `CATALOG_CATEGORY_PRODUCT_CATEGORY` (`category_id`),
                 KEY `CATALOG_CATEGORY_PRODUCT_PRODUCT` (`product_id`)
                )");

        $result = $this->db_do("
                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('stINch_products_for_customer_group_price_temp') . "
                (category_id, product_id, store_product_id, sku)
                (SELECT
                 ccp.category_id,
                 ccp.product_id,
                 cpe.store_product_id,
                 cpe.sku
                 FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_product') . " ccp
                 JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " cpe
                 ON ccp.product_id = cpe.entity_id
                 WHERE cpe.store_product_id IS NOT NULL)");

        $result = $this->db_do("
                 UPDATE  " . Mage::getSingleton('core/resource')->getTableName('stINch_products_for_customer_group_price_temp') . " pfcgpt
                 JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int') . " cpei
                 ON pfcgpt.product_id = cpei.entity_id
                 AND cpei.entity_type_id = " . $this->_getProductEntityTypeId() . "
                 AND cpei.attribute_id = " . $this->_getProductAttributeId('manufacturer') . "
                 SET pfcgpt.manufacturer_id = cpei.value
        ");

        $result = $this->db_do("
                 UPDATE  " . Mage::getSingleton('core/resource')->getTableName('stINch_products_for_customer_group_price_temp') . " pfcgpt
                 JOIN " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_decimal') . " cped
                 ON pfcgpt.product_id = cped.entity_id
                 AND cped.entity_type_id = " . $this->_getProductEntityTypeId() . "
                 AND cped.attribute_id = " . $this->_getProductAttributeId('price') . "
                 SET pfcgpt.price = cped.value
        ");
    }

    protected function _getPricerulesList()
    {
        $rulesArray = array();
        $result = $this->db_do("
            SELECT *
            FROM " . Mage::getSingleton('core/resource')->getTableName('import_pricerules') . "
            ORDER BY rating DESC
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            $rulesArray[$row['id']] = $row;
        }
        return $rulesArray;
    }

    private function get_all_children_cat($entity_id)
    {
        $children_cat = "'" . $entity_id . "'" . $this->get_all_children_cat_recursive($entity_id);
        return ($children_cat);
    }

    private function get_all_children_cat_recursive($entity_id)
    {
        $q = "SELECT entity_id
           FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_category_entity') . "
           WHERE parent_id=" . $entity_id;
        $quer = $this->db_do($q);
        $children_cat = '';
        while ($row = mysqli_fetch_array($quer)) {
            $children_cat .= ", '" . $row['entity_id'] . "'";
            $children_cat .= $this->get_all_children_cat_recursive($row['entity_id']);
        }
        return ($children_cat);
    }

    function getProductDescription($entity_id)
    {
        $this->loadProductParams($entity_id);
        $this->loadProductStarfeatures($entity_id);
        $this->loadGalleryPhotos($entity_id);
        Varien_Profiler::start('Bintime FILE RELATED');
        $this->loadRelatedProducts($entity_id);
        Varien_Profiler::stop('Bintime FILE RELATED');

        return true;
    }

    private function loadProductParams($entity_id)
    {
        $store_product_id = $this->getStoreProductIdByEntity($entity_id);
        if (!$store_product_id) {
            return false;
        }
        $q = "SELECT
                sinch_product_id,
                product_sku,
                product_name,
                sinch_manufacturer_id,
                store_category_id,
                main_image_url,
                thumb_image_url,
                medium_image_url,
                specifications,
                description,
                specifications
            FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_products') . "
            WHERE store_product_id =" . $store_product_id;
        $quer = $this->db_do($q);
        $product = mysqli_fetch_array($quer);

        $this->productDescription = (string)substr($product['description'], 50, 0);
        $this->fullProductDescription = (string)$product['description'];
        $this->lowPicUrl = (string)$product["medium_image_url"];
        $this->highPicUrl = (string)$product["main_image_url"];
        $this->productName = (string)$product["product_name"];
        $this->productId = (string)$product['product_sku'];
        $this->specifications = (string)$product['specifications'];
        $this->sinchProductId = (string)$product['sinch_product_id'];
        if ($product['sinch_manufacturer_id']) {
            $q = "SELECT manufacturer_name
                FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_manufacturers') . "
                WHERE sinch_manufacturer_id=" . $product['sinch_manufacturer_id'];
            $quer = $this->db_do($q);
            $manufacturer = mysqli_fetch_array($quer);
            $this->vendor = (string)$manufacturer['manufacturer_name'];
        }
        $q = "SELECT DISTINCT ean_code
            FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_ean_codes') . " sec
            JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products') . " sp
                ON sec.product_id=sp.sinch_product_id
            WHERE sp.store_product_id=" . $store_product_id;
        $quer = $this->db_do($q);
        while ($row = mysqli_fetch_array($quer)) {
            $EANarr[] = $row['ean_code'];
        }
        $EANstr = '';
        $EANstr = implode(", ", $EANarr);
        $this->EAN = (string)$EANstr;
    }

    private function getStoreProductIdByEntity($entity_id)
    {
        $q = $this->db_do("SELECT store_product_id
                         FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping') . "
                         WHERE entity_id=" . $entity_id, true);
        $res = mysqli_fetch_array($q);
        return ($res['store_product_id']);
    }

    private function loadProductStarfeatures($entity_id)
    {
        $descriptionArray = array();
        $product_info_features = $this->db_do("
                SELECT c.feature_name AS name, b.text AS value
                FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_product_features') . " a
                INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_restricted_values') . " b
                    ON a.restricted_value_id = b.restricted_value_id
                INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_categories_features') . " c
                    ON b.category_feature_id = c.category_feature_id
                WHERE a.sinch_product_id = '" . $this->sinchProductId . "'");
        while ($features = mysqli_fetch_array($product_info_features)) {
            $descriptionArray[$features['name']] = $features['value'];
        }


        $this->productDescriptionList = $descriptionArray;
    }

    /**
     * load Gallery array from XML
     */
    public function loadGalleryPhotos($entity_id)
    {
        $store_product_id = $this->getStoreProductIdByEntity($entity_id);
        if (!$store_product_id) {
            return;
        }
        $q = $this->db_do("SELECT COUNT(*) AS cnt
                         FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_products_pictures_gallery') . "
                         WHERE store_product_id=" . $store_product_id, true);

        $res = mysqli_fetch_array($q);
        if (!$res || !$res['cnt']) {
            return false;
        }
        $q = "SELECT
                image_url as Pic,
                thumb_image_url as ThumbPic
            FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_products_pictures_gallery') . "
            WHERE store_product_id=" . $store_product_id;

        $res = $this->db_do($q, true);

        while ($photo = mysqli_fetch_array($res)) {
            $picHeight = (int)500;
            $picWidth = (int)500;
            $thumbUrl = (string)$photo["ThumbPic"];
            $picUrl = (string)$photo["Pic"];

            array_push($this->galleryPhotos, array(
                'height' => $picHeight,
                'width' => $picWidth,
                'thumb' => $thumbUrl,
                'pic' => $picUrl
            ));
        }
    }

    private function loadRelatedProducts($entity_id)
    {
        $this->sinchProductId;
        if (!$this->sinchProductId) {
            return;
        }
        $q = "SELECT
                st_prod.sinch_product_id,
                st_prod.product_sku,
                st_prod.product_name,
                st_prod.sinch_manufacturer_id,
                st_prod.store_category_id,
                st_prod.main_image_url,
                st_prod.thumb_image_url,
                st_prod.medium_image_url,
                st_prod.specifications,
                st_prod.description,
                st_prod.specifications,
                st_manuf.manufacturer_name,
                st_manuf.manufacturers_image
            FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_related_products') . " st_rel_prod
            JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products') . " st_prod
                ON st_rel_prod.related_sinch_product_id=st_prod.sinch_product_id
            JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_manufacturers') . " st_manuf
                ON st_prod.sinch_manufacturer_id=st_manuf.sinch_manufacturer_id
            WHERE st_rel_prod.sinch_product_id=" . $this->sinchProductId;

        $quer = $this->db_do($q);
        while ($row = mysqli_fetch_array($quer)) {

            $productArray = array();
            $productArray['name'] = (string)$row['product_name'];
            $productArray['thumb'] = (string)$row['thumb_image_url'];
            $mpn = (string)$row['product_sku'];
            $productSupplierId = (int)$row['sinch_manufacturer_id'];
            $productArray['supplier_thumb'] = (string)($row['manufacturers_image']);
            $productArray['supplier_name'] = (string)$row['manufacturer_name'];

            $this->relatedProducts[$mpn] = $productArray;
        }
    }

    public function getProductName()
    {
        return $this->productName;
    }

    public function getProductDescriptionList()
    {
        return $this->productDescriptionList;
    }

    public function getProductSpecifications()
    {
        return $this->specifications;
    }

    public function getShortProductDescription()
    {
        return $this->productDescription;
    }

    public function getFullProductDescription()
    {
        return $this->fullProductDescription;
    }

    public function getLowPicUrl()
    {
        return $this->highPicUrl;
    }

    public function getRelatedProducts()
    {
        return $this->relatedProducts;
    }

    public function getVendor()
    {
        return $this->vendor;
    }

    public function getMPN()
    {
        return $this->productId;
    }

    public function getEAN()
    {
        return $this->EAN;
    }

    public function getGalleryPhotos()
    {
        return $this->galleryPhotos;
    }

    public function reloadProductImage($entity_id)
    {
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('image') . ",
                                    w.store_id,
                                    a.entity_id,
                                    b.main_image_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('core_store') . " w
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products') . " b
                                    ON a.store_product_id = b.store_product_id
                                  WHERE a.entity_id=$entity_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.main_image_url
                              ");

        // image for specific web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('image') . ",
                                    0,
                                    a.entity_id,
                                    b.main_image_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products') . " b
                                    ON a.store_product_id = b.store_product_id
                                  WHERE a.entity_id=$entity_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.main_image_url
                              ");

        // small_image for specific web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('small_image') . ",
                                    w.store_id,
                                    a.entity_id,
                                    b.main_image_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('core_store') . " w
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products') . " b
                                    ON a.store_product_id = b.store_product_id
                                  WHERE a.entity_id=$entity_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.main_image_url
                              ");

        // small_image for all web sites
        $result = $this->db_do("
                                INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar') . " (
                                    entity_type_id,
                                    attribute_id,
                                    store_id,
                                    entity_id,
                                    value
                                )(
                                  SELECT
                                    " . $this->_getProductEntityTypeId() . ",
                                    " . $this->_getProductAttributeId('small_image') . ",
                                    0,
                                    a.entity_id,
                                    b.main_image_url
                                  FROM " . Mage::getSingleton('core/resource')->getTableName('catalog_product_entity') . " a
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('core_store') . " w
                                  INNER JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_products') . " b
                                    ON a.store_product_id = b.store_product_id
                                  WHERE a.entity_id=$entity_id
                                )
                                ON DUPLICATE KEY UPDATE
                                    value = b.main_image_url
                ");
    }

    function valid_utf($string, $new_line = true)
    {
        $string = preg_replace('/™/', '&#8482;', $string);
        $string = preg_replace("/®/", '&reg;', $string);
        $string = preg_replace("/≈/", '&asymp;', $string);
        $string = preg_replace("/" . chr(226) . chr(128) . chr(157) . "/", '&quot;', $string);
        $string = preg_replace("/" . chr(226) . chr(128) . chr(153) . "/", '&prime;', $string);
        $string = preg_replace("/°/", '&deg;', $string);
        $string = preg_replace("/±/", '&plusmn;', $string);
        $string = preg_replace("/µ/", '&micro;', $string);
        $string = preg_replace("/²/", '&sup2;', $string);
        $string = preg_replace("/³/", '&sup3;', $string);
        $string = preg_replace('/\xe2\x80\x93/', '-', $string);
        $string = preg_replace('/\xe2\x80\x99/', '\'', $string);
        $string = preg_replace('/\xe2\x80\x9c/', ' ', $string);
        $string = preg_replace('/\xe2\x80\x9d/', ' ', $string);

        return utf8_decode($string);
    }

    function set_imports_failed()
    {
        $this->db_do("UPDATE " . $this->import_status_statistic_table . "
                      SET global_status_import='Failed'
                      WHERE global_status_import='Run'");
    }

    function getImportStatusHistory()
    {
        $res = "SELECT COUNT(*) FROM " . $this->import_status_statistic_table;
        $cnt_arr = mysqli_fetch_array($this->db_do($res));
        $cnt = $cnt_arr[0];
        $StatusHistory_arr = array();
        if ($cnt > 0) {
            $a = (($cnt > 7) ? ($cnt - 7) : 0);
            $b = $cnt;
            $q = "SELECT
                    id,
                    start_import,
                    finish_import,
                    import_type,
                    number_of_products,
                    global_status_import,
                    detail_status_import
                FROM " . $this->import_status_statistic_table . "
                ORDER BY start_import limit " . $a . ", " . $b;
            $result = $this->db_do($q);
            while ($row = mysqli_fetch_array($result)) {
                $StatusHistory_arr[] = $row;
            }
        }
        return $StatusHistory_arr;
    }

    function getDateOfLatestSuccessImport()
    {
        $q = "SELECT start_import, finish_import
            FROM " . $this->import_status_statistic_table . "
            WHERE global_status_import='Successful'
            ORDER BY id DESC LIMIT 1";
        $imp_date = mysqli_fetch_array($this->db_do($q));
        return $imp_date['start_import'];
    }

    function getDataOfLatestImport()
    {
        $q = "SELECT
                start_import,
                finish_import,
                import_type,
                number_of_products,
                global_status_import,
                detail_status_import,
                number_of_products,
                error_report_message
            FROM " . $this->import_status_statistic_table . "
            ORDER BY id DESC LIMIT 1";
        $imp_status = mysqli_fetch_array($this->db_do($q));
        return $imp_status;
    }

    function getImportStatuses()
    {
        $q = "SELECT id, message, finished
            FROM " . $this->import_status_table . "
            ORDER BY id LIMIT 1";
        $quer = $this->db_do($q);

        $messages = array('message' => '', 'id' => 0, 'finished' => 0);

        if ($row = mysqli_fetch_array($quer)) {
            $messages = array('message' => $row['message'], 'id' => $row['id'], 'finished' => $row['finished']);
            $id = $row['id'];
        }

        if (!empty($id)) {
            $q = "DELETE FROM " . $this->import_status_table . " WHERE id=" . $id;
            $this->db_do($q);
        }

        return $messages;
    }

    public function checkMemory()
    {
        $check_code = 'memory';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);


        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();

        $memInfoContent = file_get_contents("/proc/meminfo");
        if ($memInfoContent === false) {
            return array(
                'error',
                $Caption,
                $CheckValue,
                0,
                $CheckMeasure,
                'Cannot read /proc/meminfo for RAM information',
                'Make sure open_basedir permits access to /proc/meminfo (or is off) and that this is a *nix system'
            );
        }
        $data = explode("\n", $memInfoContent);

        foreach ($data as $line) {
            $lineParts = explode(":", $line);
            if (count($lineParts) < 2) continue;
            list($key, $val) = $lineParts;

            if ($key == 'MemTotal') {
                $val = trim($val);
                $value = (int)substr($val, 0, strpos($val, ' kB'));
                $measure = substr($val, strpos($val, ' kB'));

                $retvalue['memory']['value'] = (integer)(((float)$value) / 1024);
                $retvalue['memory']['measure'] = 'MB';
            }
        }

        $errmsg = '';
        $fixmsg = '';
        if ($retvalue['memory']['value'] <= $CheckValue) {
            $errmsg = sprintf($ErrorMessage, $retvalue['memory']['value']);
            $fixmsg = sprintf($FixMessage, " " . $CheckValue . " " . $CheckMeasure);
            $retvalue['memory']['status'] = 'error';
        } else {
            $retvalue['memory']['status'] = 'OK';
        }

        return array(
            $retvalue['memory']['status'],
            $Caption,
            $CheckValue,
            $retvalue['memory']['value'],
            $CheckMeasure,
            $errmsg,
            $fixmsg
        );
    }

    public function checkLoaddata()
    {
        $check_code = 'loaddata';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $result = $conn->query("SHOW VARIABLES LIKE 'local_infile'");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $value = $row['Value'];

        $errmsg = '';
        $fixmsg = '';
        if ($value != $CheckValue) {
            $errmsg .= $ErrorMessage . " " . $value . " " . $CheckMeasure;
            $fixmsg .= $FixMessage;
            $status = 'error';
        } else {
            $errmsg .= 'none';
            $fixmsg .= 'none';
            $status = 'OK';
        }

        $ret = array();
        array_push($ret, $status, $Caption, $CheckValue, $value, $CheckMeasure, $errmsg, $fixmsg);

        return $ret;
    }

    public function checkPhpsafemode()
    {
        $check_code = 'phpsafemode';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();

        $a = ini_get('safe_mode');
        if ($a) {
            $value = 'ON';
        } else {
            $value = 'OFF';
        }

        $errmsg = '';
        $fixmsg = '';
        if ($value != $CheckValue) {
            $errmsg .= sprintf($ErrorMessage, " " . $value . " " . $CheckMeasure);
            $fixmsg .= sprintf($FixMessage, " " . $CheckValue . " " . $CheckMeasure);
            $status = 'error';
        } else {
            $errmsg .= 'none';
            $fixmsg .= 'none';
            $status = 'OK';
        }

        $ret = array();
        array_push($ret, $status, $Caption, $CheckValue, $value, $CheckMeasure, $errmsg, $fixmsg);

        return $ret;
    }

    public function checkWaittimeout()
    {
        $check_code = 'waittimeout';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $result = $conn->query("SHOW VARIABLES LIKE 'wait_timeout'");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $value = $row['Value'];

        $errmsg = '';
        $fixmsg = '';
        if ($value <= $CheckValue) {
            $errmsg .= $ErrorMessage . " " . $value . " " . $CheckMeasure;
            $fixmsg .= sprintf($FixMessage, " " . $CheckValue);
            $status = 'error';
        } else {
            $errmsg .= 'none';
            $fixmsg .= 'none';
            $status = 'OK';
        }

        $ret = array();
        array_push($ret, $status, $Caption, $CheckValue, $value, $CheckMeasure, $errmsg, $fixmsg);

        return $ret;
    }

    public function checkInnodbbufferpoolsize()
    {
        $check_code = 'innodbbufpool';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $result = $conn->query("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $value = (int)($row['Value'] / (1024 * 1024));

        $errmsg = '';
        $fixmsg = '';
        if ($value < $CheckValue) {
            $errmsg .= sprintf($ErrorMessage, " " . $value . " " . $CheckMeasure);
            $fixmsg .= sprintf($FixMessage, " " . $CheckValue . " " . $CheckMeasure);
            $status = 'error';
        } else {
            $errmsg .= 'none';
            $fixmsg .= 'none';
            $status = 'OK';
        }

        $ret = array();
        array_push($ret, $status, $Caption, $CheckValue, $value, $CheckMeasure, $errmsg, $fixmsg);

        return $ret;
    }

    public function checkPhprunstring()
    {
        $check_code = 'php5run';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();

        $value = trim(PHP_RUN_STRING);
        $errmsg = '';
        $fixmsg = '';
        $status = 'OK';

        if (!defined('PHP_RUN_STRING')) {
            $errmsg .= "You haven't installed PHP CLI";
            $fixmsg .= "Install PHP CLI.";
            $status = 'error';
        }

        return array(
            $status,
            $Caption,
            $CheckValue,
            $value,
            $CheckMeasure,
            $errmsg,
            $fixmsg
        );
    }

    public function checkChmodwgetdatafile()
    {
        $check_code = 'chmodwget';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();

        $datafile_csv = '/usr/bin/wget';

        $value = substr(sprintf('%o', fileperms($datafile_csv)), -4);

        $CheckValue_own = $CheckValue{1};
        $CheckValue_group = $CheckValue{2};
        $CheckValue_other = $CheckValue{3};

        $value_own = $value{1};
        $value_group = $value{2};
        $value_other = $value{3};

        $errmsg = '';
        $fixmsg = '';
        if (($value_own < $CheckValue_own) || ($value_group < $CheckValue_group) || ($value_other < $CheckValue_other)) {
            $errmsg .= $ErrorMessage;
            $fixmsg .= $FixMessage;
            $status = 'error';
        } else {
            $errmsg .= 'none';
            $fixmsg .= 'none';
            $status = 'OK';
        }

        $ret = array();
        array_push($ret, $status, $Caption, $CheckValue, $value, $CheckMeasure, $errmsg, $fixmsg);

        return $ret;
    }

    public function checkChmodwgetcronphpfile()
    {
        $check_code = 'chmodcronphp';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();

        $cronfile_php = Mage::getBaseDir() . '/cron.php';

        $value = substr(sprintf('%o', fileperms($cronfile_php)), -4);

        $CheckValue_own = $CheckValue{1};
        $CheckValue_group = $CheckValue{2};
        $CheckValue_other = $CheckValue{3};

        $value_own = $value{1};
        $value_group = $value{2};
        $value_other = $value{3};

        $errmsg = '';
        $fixmsg = '';
        if (($value_own < $CheckValue_own) || ($value_group < $CheckValue_group) || ($value_other < $CheckValue_other)) {
            $errmsg .= $ErrorMessage;
            $fixmsg .= $FixMessage;
            $status = 'error';
        } else {
            $errmsg .= 'none';
            $fixmsg .= 'none';
            $status = 'OK';
        }

        $ret = array();
        array_push($ret, $status, $Caption, $CheckValue, $value, $CheckMeasure, $errmsg, $fixmsg);

        return $ret;
    }

    public function checkChmodwgetcronshfile()
    {
        $check_code = 'chmodcronsh';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();

        $cronfile_sh = Mage::getBaseDir() . '/cron.sh';

        $value = substr(sprintf('%o', fileperms($cronfile_sh)), -4);

        $CheckValue_own = $CheckValue{1};
        $CheckValue_group = $CheckValue{2};
        $CheckValue_other = $CheckValue{3};

        $value_own = $value{1};
        $value_group = $value{2};
        $value_other = $value{3};

        $errmsg = '';
        $fixmsg = '';
        if (($value_own < $CheckValue_own) || ($value_group < $CheckValue_group) || ($value_other < $CheckValue_other)) {
            $errmsg .= $ErrorMessage;
            $fixmsg .= $FixMessage;
            $status = 'error';
        } else {
            $errmsg .= 'none';
            $fixmsg .= 'none';
            $status = 'OK';
        }

        $ret = array();
        array_push($ret, $status, $Caption, $CheckValue, $value, $CheckMeasure, $errmsg, $fixmsg);

        return $ret;
    }

    public function checkProcedure()
    {
        $check_code = 'routine';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $storedFunctionName = Mage::getSingleton('core/resource')->getTableName('filter_sinch_products_s');
        $result = $conn->query("SHOW PROCEDURE STATUS LIKE '$storedFunctionName'");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $value = $row['Name'];

        $errmsg = '';
        $fixmsg = '';
        if ($value != $CheckValue) {
            $errmsg .= $ErrorMessage;
            $fixmsg .= $FixMessage;
            $status = 'error';
        } else {
            $errmsg .= 'none';
            $fixmsg .= 'none';
            $status = 'OK';
        }

        $ret = array();
        array_push($ret, $status, $Caption, $CheckValue, $value, $CheckMeasure, $errmsg, $fixmsg);

        return $ret;
    }

    public function checkConflictsWithInstalledModules()
    {
        $check_code = 'conflictwithinstalledmodules';

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName('stINch_sinchcheck');
        $result = $conn->query("SELECT * FROM $tableName WHERE check_code = '$check_code'");
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $Caption = $row['caption'];
        $CheckValue = $row['check_value'];
        $CheckMeasure = $row['check_measure'];
        $ErrorMessage = $row['error_msg'];
        $FixMessage = $row['fix_msg'];

        $retvalue = array();
        $retvalue["'$check_code'"] = array();
        $config_file = (Mage::app()->getConfig()->getNode()->asXML());

        $errmsg = $ErrorMessage;
        $fixmsg = $FixMessage;
        $status = 'OK';

        if (!strstr($config_file, '<image>Bintime_Sinchimport_Helper_Image</image>')) {
            $errmsg .= " Can't find <image>Bintime_Sinchimport_Helper_Image</image> in  <helpers><catalog></catalog></helpers>";
            $fixmsg = $FixMessage;
            $status = 'error';
        }

        if (!strstr($config_file, '<product_image>Bintime_Sinchimport_Model_Image</product_image>')) {
            $errmsg .= " Can't find <product_image>Bintime_Sinchimport_Model_Image</product_image> in  <models><catalog></catalog></models>";
            $fixmsg = $FixMessage;
            $status = 'error';
        }

        if (!strstr($config_file, '<category>Bintime_Sinchimport_Model_Category</category>')) {
            $errmsg .= " Can't find <category>Bintime_Sinchimport_Model_Category</category> in  <models><catalog></catalog></models>";
            $fixmsg = $FixMessage;
            $status = 'error';
        }

        if (!strstr($config_file, '<product_compare_list>Bintime_Sinchimport_Block_List</product_compare_list>')) {
            $errmsg .= " Can't find <product_compare_list>Bintime_Sinchimport_Block_List</product_compare_list> in  <blocks><catalog></catalog></blocks>";
            $fixmsg = $FixMessage;
            $status = 'error';
        }

        if (!strstr($config_file, '<product_view_media>Bintime_Sinchimport_Block_Product_View_Media</product_view_media>')) {
            $errmsg .= " Can't find <product_view_media>Bintime_Sinchimport_Block_Product_View_Media</product_view_media> in  <blocks><catalog></catalog></blocks>";
            $fixmsg = $FixMessage;
            $status = 'error';
        }

        if (!strstr($config_file, '<product>Bintime_Sinchimport_Model_Product</product>')) {
            $errmsg .= " Can't find <product>Bintime_Sinchimport_Model_Product</product> in  <models><catalog></catalog></models>";
            $fixmsg = $FixMessage;
            $status = 'error';
        }

        if (!strstr($config_file, '<layer_filter_price>Bintime_Sinchimport_Model_Layer_Filter_Price</layer_filter_price>')) {
            $errmsg .= " Can't find <layer_filter_price>Bintime_Sinchimport_Model_Layer_Filter_Price</layer_filter_price> in  <models><catalog></catalog><models>";
            $fixmsg = $FixMessage;
            $status = 'error';
        }

        if (!strstr($config_file, '<layer_view>Bintime_Sinchimport_Block_Layer_View</layer_view>')) {
            $errmsg .= " Can't find <layer_view>Bintime_Sinchimport_Block_Layer_View</layer_view> in  <blocks><catalog></catalog></blocks>";
            $fixmsg = $FixMessage;
            $status = 'error';
        }

        if (!strstr($config_file, '<layer>Bintime_Sinchimport_Model_Layer</layer>')) {
            $errmsg .= " Can't find <layer>Bintime_Sinchimport_Model_Layer</layer> in  <models><catalog></catalog><models>";
            $fixmsg = $FixMessage;
            $status = 'error';
        }

        if (!strstr($config_file, '<layer_filter_price>Bintime_Sinchimport_Model_Resource_Layer_Filter_Price</layer_filter_price>')) {
            $errmsg .= " Can't find <layer_filter_price>Bintime_Sinchimport_Model_Resource_Layer_Filter_Price</layer_filter_price> in  <models><catalog_resource_eav_mysql4></catalog_resource_eav_mysql4></models>";
            $fixmsg = $FixMessage;
            $status = 'error';
        }

        if ($status == 'OK') {
            $errmsg = 'none';
            $fixmsg = 'none';
        }
        return array(
            $status,
            $Caption,
            $CheckValue,
            '',
            $CheckMeasure,
            $errmsg,
            $fixmsg
        );
    }

    public function getSinchDistribotorsTableHtml($entity_id = null)
    {
        if (!$entity_id) {
            $entity_id = Mage::registry('current_product')->getId();
        }
        if (!$entity_id) {
            return '';
        }

        $distributors_stock_price = $this->getDistributorStockPriceByProductid($entity_id);
        $distributors_table = '
            <table>
                <thead>
                   <tr class="headings">
                     <th>Supplier</th>
                     <th>Stock</th>
                     <th>Price</th>
                   </tr>
                </thead>
                <tbody>';
        $i = 1;
        # Moe : added if loop around the foreach to check if distributors stock price exists
        if ($distributors_stock_price) {
            foreach ($distributors_stock_price as $offer) {
                if ($i > 0) {
                    $class = "even pointer";
                    $i = 0;
                } else {
                    $class = "pointer";
                    $i = 1;
                }
                $distributors_table .= '
                      <tr class="' . $class . '">
                            <td nowrap  style="font-weight: normal">' . $offer['distributor_name'] . '</td>
                            <td style="font-weight: normal">' . $offer['stock'] . '</td>
                            <td style="font-weight: normal">' . Mage::helper('core')->currency($offer['cost']) . '</td>
                       </tr>';
            }            
        }
        $distributors_table .= '
                </tbody>
            </table>
         ';
        return $distributors_table;
    }

    private function getDistributorStockPriceByProductid($entity_id)
    {
        $store_product_id = $this->getStoreProductIdByEntity($entity_id);
        if (!$store_product_id) {
            return;
        }
        $q = "SELECT
                d.distributor_name,
                d.website,
                dsp.stock,
                dsp.cost,
                dsp.distributor_sku,
                dsp.distributor_category,
                dsp.eta
            FROM " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors_stock_and_price') . " dsp
            JOIN " . Mage::getSingleton('core/resource')->getTableName('stINch_distributors') . " d
            ON dsp.distributor_id = d.distributor_id
            WHERE store_product_id =" . $store_product_id;
        $quer = $this->db_do($q);
        $offers = null;
        while ($row = mysqli_fetch_array($quer)) {
            $offers[] = $row;
        }
        return $offers;
    }

    protected function _checkDataExist($table)
    {
        $query = $this->db_do("
            SELECT *
            FROM $table
        ");
        while ($row = mysqli_fetch_array($query)) {
            return true;
        }

        return false;
    }
}
