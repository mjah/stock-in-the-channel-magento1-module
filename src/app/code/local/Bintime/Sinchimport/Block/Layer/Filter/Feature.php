<?php

class Bintime_Sinchimport_Block_Layer_Filter_Feature extends Mage_Catalog_Block_Layer_Filter_Abstract
{

    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'sinchimport/layer_filter_feature';
    }

    protected function _prepareFilter()
    {
        $this->_filter->setAttributeModel($this->getAttributeModel());
        return $this;
    }
}
