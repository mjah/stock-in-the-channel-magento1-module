<?php

class Bintime_Sinchimport_Model_Image extends Mage_Catalog_Model_Product_Image {

public function setBaseFile($file)
    {
        $this->_isBaseFilePlaceholder = false;
	if (substr($file,0,4) != 'http') {
		if (($file) && (0 !== strpos($file, '/', 0))) {
		    $file = '/' . $file;
		}
		$baseDir = Mage::getSingleton('catalog/product_media_config')->getBaseMediaPath();
		if ('/no_selection' == $file) {
		    $file = null;
		}
		if ($file) {
		    if ((!file_exists($baseDir . $file)) || !$this->_checkMemory($baseDir . $file)) {
		        $file = null;
		    }
		}
		if (!$file) {
		    // check if placeholder defined in config
		    $isConfigPlaceholder = Mage::getStoreConfig("catalog/placeholder/{$this->getDestinationSubdir()}_placeholder");
		    $configPlaceholder   = '/placeholder/' . $isConfigPlaceholder;
		    if ($isConfigPlaceholder && file_exists($baseDir . $configPlaceholder)) {
		        $file = $configPlaceholder;
		    }
		    else {
		        // replace file with skin or default skin placeholder
		        $skinBaseDir     = Mage::getDesign()->getSkinBaseDir();
		        $skinPlaceholder = "/images/catalog/product/placeholder/{$this->getDestinationSubdir()}.jpg";
		        $file = $skinPlaceholder;
		        if (file_exists($skinBaseDir . $file)) {
		            $baseDir = $skinBaseDir;
		        }
		        else {
		            $baseDir = Mage::getDesign()->getSkinBaseDir(array('_theme' => 'default'));
		            if (!file_exists($baseDir . $file)) {
		                $baseDir = Mage::getDesign()->getSkinBaseDir(array('_theme' => 'default', '_package' => 'base'));
		            }
		        }
		    }
		    $this->_isBaseFilePlaceholder = true;
		}

        	$baseFile = $baseDir . $file;
	}
    else {
    	$baseFile =$file;
    	}
        if ((!$file) AND (!file_exists($baseFile)) AND substr($baseFile,0,4) != 'http') {
            throw new Exception(Mage::helper('catalog')->__('Image file not found'));
        }

        $this->_baseFile = $baseFile;
	 if (substr($baseFile,0,4) != 'http') {
		// build new filename (most important params)
		$path = array(
		    Mage::getSingleton('catalog/product_media_config')->getBaseMediaPath(),
		    'cache',
		    Mage::app()->getStore()->getId(),
		    $path[] = $this->getDestinationSubdir()
		);
		if((!empty($this->_width)) || (!empty($this->_height)))
		    $path[] = "{$this->_width}x{$this->_height}";
		
		// add misk params as a hash
		$miscParams = array(
		        ($this->_keepAspectRatio  ? '' : 'non') . 'proportional',
		        ($this->_keepFrame        ? '' : 'no')  . 'frame',
		        ($this->_keepTransparency ? '' : 'no')  . 'transparency',
		        ($this->_constrainOnly ? 'do' : 'not')  . 'constrainonly',
		        $this->_rgbToString($this->_backgroundColor),
		        'angle' . $this->_angle,
		        'quality' . $this->_quality
		);

		// if has watermark add watermark params to hash
		if ($this->getWatermarkFile()) {
		    $miscParams[] = $this->getWatermarkFile();
		    $miscParams[] = $this->getWatermarkImageOpacity();
		    $miscParams[] = $this->getWatermarkPosition();
		    $miscParams[] = $this->getWatermarkWidth();
		    $miscParams[] = $this->getWatermarkHeigth();
		}

		$path[] = md5(implode('_', $miscParams));
	}
	 else {
        	$path[] = $file;
        }


        // append prepared filename
	if (substr($file,0,4) != 'http') {
		$this->_newFile = implode('/', $path) . $file; // the $file contains heading slash
	}
	else {
		$this->_newFile = $file;
	}
    return $this;
    }
	
	protected function _rgbToString($rgbArray)
    {
        $result = array();
        foreach ($rgbArray as $value) {
            if (null === $value) {
                $result[] = 'null';
            }
            else {
                $result[] = sprintf('%02s', dechex($value));
            }
        }
        return implode($result);
    }
	
}
