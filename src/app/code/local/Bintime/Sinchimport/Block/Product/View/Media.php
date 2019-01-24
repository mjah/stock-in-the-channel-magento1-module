<?php 
class Bintime_Sinchimport_Block_Product_View_Media extends Mage_Catalog_Block_Product_View_Media
{
    public function getGalleryUrl($image=null)
    {
        if (substr($image['url'],0,4) != 'http') {    
            $params = array('id'=>$this->getProduct()->getId());
            if ($image) {
                $params['image'] = $image->getValueId();
                return $this->getUrl('*/*/gallery', $params);
            }
            return $this->getUrl('*/*/gallery', $params);
        }else{
            return $image['url'];
        }    
     }

}
