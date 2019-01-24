<?php 
class Bintime_Sinchimport_IndexController extends Mage_Adminhtml_Controller_Action
{
    var
        $_logFile;

    public function indexAction(){
        $this->_logFile = "Sinch.log";
        Mage::log("Start Sinch import [IndexController.php]", null, $this->_logFile);
        $this->getResponse()->appendBody("Start import <br>");

        $import = Mage::getModel('sinchimport/sinch');
        $import->run_sinch_import();
	
        $this->getResponse()->appendBody("Finish import<br>");

    }

    
    public function stockPriceImportAction(){
	    $this->_logFile = "Sinch.log";
        Mage::log("Start Stock & Price Sinch import", null, $this->_logFile);
        $this->getResponse()->appendBody("Start Stock & Price import <br>");

        $import = Mage::getModel('sinchimport/sinch');
        $import->run_stock_price_sinch_import();
	
        $this->getResponse()->appendBody("Finish Stock & Price import<br>");
    }	


    public function splitFeaturesAction()
    {	
        $resource = Mage::getResourceModel('sinchimport/layer_filter_feature');
        $resource->splitProductsFeature(null);
        $this->getResponse()->setBody('<h2>done.</h2>');
    }


    protected function _isAllowed() {
        return Mage::getSingleton('admin/session')->isAllowed('system/config/sinchimport_root');
    }
}    
