<?php

class Bintime_Sinchimport_Model_Product extends Mage_Catalog_Model_Product 
{
    public function getIcecatId()
    {
		
	     $resource = $this->getResource();
		  $connection = $resource->getReadConnection();
		  $result = $connection->query("
		      SELECT product_id FROM icecat_products WHERE mpn = '" . $this->getVendorProductId() . "' ");
		  $id = 0;
		  if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
		      $id = $row['product_id'];
		  }
		 // echo 'rewrite!';
        return $id;
    }
    public function getMediaGalleryImages()
    {
      $entity_id=$this->getEntityId();
      $sinch=Mage::getModel('sinchimport/sinch');
      $sinch->loadGalleryPhotos($this->getEntityId());
      $gallery_photos=$sinch->getGalleryPhotos(); 
      if(is_array($gallery_photos)){  
        $images = new Varien_Data_Collection();
        foreach($gallery_photos as $photo){
                $image['file']=$photo['thumb'];
                $image['url']=$photo['pic'];
                $images->addItem(new Varien_Object($image));
        }
        $this->setData('media_gallery_images', $images);            
      }else{
        if(!$this->hasData('media_gallery_images') && is_array($this->getMediaGallery('images'))) {
            $images = new Varien_Data_Collection();
            foreach ($this->getMediaGallery('images') as $image) {
                if ($image['disabled']) {
                    continue;
                }
                $image['url'] = $this->getMediaConfig()->getMediaUrl($image['file']);
                $image['id'] = isset($image['value_id']) ? $image['value_id'] : null;
                $image['path'] = $this->getMediaConfig()->getMediaPath($image['file']);
                $images->addItem(new Varien_Object($image));
            }
            $this->setData('media_gallery_images', $images);
        }

      }  

        return $this->getData('media_gallery_images');        
    }

    function afterCommitCallback(){
        parent::afterCommitCallback();
        Mage::getSingleton('index/indexer')->processEntityAction(
                $this, self::ENTITY, Mage_Index_Model_Event::TYPE_SAVE
                );
        $entity_id=$this->getEntityId();
        $sinch=Mage::getModel('sinchimport/sinch');
        $sinch->reloadProductImage($entity_id);
        return $this;

    }
}
