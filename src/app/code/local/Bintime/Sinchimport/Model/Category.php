<?php

class Bintime_Sinchimport_Model_Category extends Mage_Catalog_Model_Category {

    public function getImageUrl()
    {
        $url = false;
        if ($image = $this->getImage()) {
            if (substr($image,0,4) != 'http'){
                $url = Mage::getBaseUrl('media').'catalog/category/'.$image;
            }else{
                $url = $image;
            }
        }
        return $url;
    }


}
