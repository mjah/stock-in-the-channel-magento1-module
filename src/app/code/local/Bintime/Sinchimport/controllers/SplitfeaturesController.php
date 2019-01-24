<?php 
class Bintime_Sinchimport_SplitfeaturesController extends Mage_Adminhtml_Controller_Action
{
    //      indexAction
    var
    $_logFile;

    public function indexAction(){
            
            $resource = Mage::getResourceModel('sinchimport/layer_filter_feature');
            $resource->splitProductsFeature(null);
            $this->getResponse()->setBody('<h2>done.</h2>');
    }


    protected function _isAllowed() {
        return Mage::getSingleton('admin/session')->isAllowed('system/config/sinchimport_root');
    }
}
