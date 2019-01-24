<?php 
class Bintime_Sinchimport_AjaxController extends Mage_Adminhtml_Controller_Action
{
    var $_logFile;

    public function UpdateStatusAction() {
        $import=Mage::getModel('sinchimport/sinch');
        $message_arr = $import->getImportStatuses();
        if ($message_arr['id']) {
            // TODO use: $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result))
            // JSON
            $this->getResponse()->setBody('{ "message": "'.$message_arr['message'].'", "finished": "'.$message_arr['finished'].'"}');
        } else {
            $this->getResponse()->setBody('{ "message": "", "finished": "0"}');
        }
    }


    public function indexAction() {
	    $sinch=Mage::getModel('sinchimport/sinch');
        $this->_logFile = "Sinch.log";
        Mage::log("Start Sinch import [AjaxController.php]", null, $this->_logFile);
        $this->getResponse()->appendBody("Start import <br>");
        $dir = dirname(__FILE__);
        $php_run_string_array = explode(';', $sinch->php_run_strings);
        foreach($php_run_string_array as $php_run_string){
            exec("nohup ".$php_run_string." ".$dir."/../sinch_import_start_ajax.php > /dev/null & echo $!", $out);
            sleep(1);
            if (($out[0] > 0) && !$sinch->is_imort_not_run()){
                break;
            }
        }
        $this->getResponse()->appendBody("Finish import<br>");

    }


    public function stockPriceAction() {
	    $sinch=Mage::getModel('sinchimport/sinch');
        $this->_logFile="Sinch.log";
        Mage::log("Start Stock & Price Sinch import", null, $this->_logFile);
        $this->getResponse()->appendBody("Start Stock & Price import <br>");
        $dir = dirname(__FILE__);
        $php_run_string_array = explode(';', $sinch->php_run_strings);
        foreach($php_run_string_array as $php_run_string){
            exec("nohup ".$php_run_string." ".$dir."/../stock_price_sinch_import_start_ajax.php > /dev/null & echo $!", $out);
            sleep(1);
            if (($out[0] > 0) && !$sinch->is_imort_not_run()){
                break;
            }
        }
        $this->getResponse()->appendBody("Finish Stock & Price import<br>");
    }


    protected function _isAllowed() {
        return Mage::getSingleton('admin/session')->isAllowed('system/config/sinchimport_root');
    }
}    
