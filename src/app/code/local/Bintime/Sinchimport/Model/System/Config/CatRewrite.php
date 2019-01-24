<?php
/**
 * Class provides data for Magento BO
 *  @author Sergey Stepanchuk <info@bintime.com>
 *
 */
class Bintime_Sinchimport_Model_System_Config_CatRewrite
{
    public function toOptionArray()
    {    
    	$paramsArray = array(
    	    'REWRITE' => 'Overwrite',
            'MERGE' => 'Merge',
    	);
        return $paramsArray;
    }
}
