<?php
class Bintime_Sinchimport_Block_Adminhtml_Catalog_Product_Sinchdistributors extends Mage_Core_Block_Template implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public function __construct(){
        $this->setTemplate('sinchimport/sinchdistributors.phtml');
        parent::__construct();
    }

    //Label to be shown in the tab
    public function getTabLabel(){
        return Mage::helper('core')->__('Suppliers');
    }

    public function getTabTitle(){
        return Mage::helper('core')->__('Suppliers');
    }

    public function canShowTab(){
        return true;
    }

    public function isHidden(){
        return false;
    }
}
