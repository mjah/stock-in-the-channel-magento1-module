<?php

class Bintime_Sinchimport_Block_Layer_View extends Mage_Catalog_Block_Layer_View
{

    protected $filterableFeatures = array();
    
    /**
     * Prepare child blocks
     *
     * @return Mage_Catalog_Block_Layer_View
     */
    protected function _prepareLayout()
    {
        //получение списка фич, по которым строися навигация
        $filterableFeatures = $this->getLayer()->getFilterableFeatures();
        $filterBlockName = 'sinchimport/layer_filter_feature'; //block
        foreach ($filterableFeatures as $feature) {
        	$this->filterableFeatures[] = $feature;
            $featureBlock = $this->getLayout()->createBlock($filterBlockName)
                    ->setLayer($this->getLayer())
                    ->setAttributeModel($feature)
                    ->init();
            $this->setChild('feature_' . $feature['feature_id'] . '_filter',
                            $featureBlock
                           );
        }
/* ------------ */
        return parent::_prepareLayout();
    }

    /**
     * Get all layer filters
     *
     * @return array
     */
    public function getFilters()
    {
        $filters = parent::getFilters();
/* ------------ */     
        foreach ($this->filterableFeatures as $feature) {
            $filters[] = $this->getChild('feature_' . $feature['feature_id'] . '_filter');
        }
/* ------------ */
        
        return $filters;
    }
}
