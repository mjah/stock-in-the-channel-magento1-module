<?php
class Bintime_Sinchimport_Block_Startstockpriceimportbutton extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $url = $this->getUrl('sinchimport/index'); //
        $this->setElement($element);

        $html = $this->_appendJs();

        $html .= '<div id="sinchimport_stock_price_status_template" name="sinchimport_stock_price_status_template" style="display:none">';//none
        $html .= $this->_getStatusTemplateHtml();
        $html .= '</div>';

        $start_import_button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setClass('scalable')
            ->setLabel('Force Stock & Prices Import now')
            ->setOnClick("start_stock_price_sinch_import()") //setLocation('$url')
            ->toHtml();
//        $html .= $url;
        $dataConf = Mage::getConfig();//('sinchimport_root');//getConfig()->getNode()->asXML();
    //    $html .= "<pre>".var_export($dataConf, true)."</pre>";
        $import=Mage::getModel('sinchimport/sinch');

        $safe_mode_set = ini_get('safe_mode');
        if($safe_mode_set){
            $html .="<p class='sinch-error'><b>You can't start import (safe_mode is 'On'. set safe_mode = Off in php.ini )<b></p>";
        }elseif(!$import->is_full_import_have_been_run()){
	    $html .="Full import have never finished with success";		
	}else{
            $html .= $start_import_button;    
        }

        $last_import=$import->getDataOfLatestImport();
        $last_imp_status=$last_import['global_status_import'];
        if($last_imp_status=='Failed'){
            $html.='<div id="sinchimport_current_status_message" name="sinchimport_current_status_message" style="display:true"><br><br><hr/><p class="sinch-error">The import has failed. Please ensure that you are using the correct settings. Last step was "'.$last_import['detail_status_import'].'"<br> Error reporting : "'.$last_import['error_report_message'].'"</p></div>';
        }elseif($last_imp_status=='Successful'){
            $html.='<div id="sinchimport_current_status_message" name="sinchimport_current_status_message" style="display:true"><br><br><hr/><p class="sinch-success">'.$last_import['number_of_products'].' products imported succesfully!</p></div>';
        }elseif($last_imp_status=='Run'){
            $html.='<div id="sinchimport_current_status_message" name="sinchimport_current_status_message" style="display:true"><br><br><hr/><p>Import is running now</p></div>';
        }else{
            $html.='<div id="sinchimport_current_status_message" name="sinchimport_current_status_message" style="display:true"></div>';
        }

        return $html;        
    }

    protected function _getStatusTemplateHtml()
    {
        $run_pic=Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)."adminhtml/default/default/images/sinchimport_run.gif";
        $html="
           <ul> 
            <li>
               Start Import
               &nbsp
               <span id='sinchimport_stock_price_start_import'> 
                <img src='".$run_pic."'
                 alt='Sinch Import run' /> 
               </span> 
            </li>   
            <li>
               Download files   
               &nbsp
               <span id='sinchimport_stock_price_upload_files'> 
                <img src='".$run_pic."'
                 alt='Download files' /> 
               </span> 
            </li>   
            <li>
               Parse Stock And Prices   
               &nbsp
               <span id='sinchimport_stock_price_parse_products'>  
                <img src='".$run_pic."'
                 alt='Parse Stock And Prices' /> 
               </span> 
            </li>   
            <li>
               Indexing data   
               &nbsp
               <span id='sinchimport_stock_price_indexing_data'>  
                <img src='".$run_pic."'
                 alt='Indexing data' /> 
               </span> 
            </li>   
            <li>
               Import finished   
               &nbsp
               <span id='sinchimport_stock_price_finish_import'>  
                <img src='".$run_pic."'
                 alt='Import finished' /> 
               </span> 
            </li>   

           </ul>
        ";
        return $html;
    }

    protected function _appendJs()
    {
        $post_url=$this->getUrl('sinchimport/ajax/stockPrice');
        $post_url_upd=$this->getUrl('sinchimport/ajax/UpdateStatus');
        $html = "
        <script>
            function start_stock_price_sinch_import(){
		    set_stock_price_run_icon();	
                    st_pr_status_div=document.getElementById('sinchimport_stock_price_status_template');   
                    curr_status_div=document.getElementById('sinchimport_current_status_message'); 
                    curr_status_div.style.display='none';
                    st_pr_status_div.style.display='';
//                    status_div.innerHTML='';
                    sinch = new Sinch('$post_url','$post_url_upd');
                    sinch.startSinchImport();

                    //
            }

            function set_stock_price_run_icon(){
                run_pic='<img src=\"".Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)."adminhtml/default/default/images/sinchimport_run.gif\""."/>';     
                document.getElementById('sinchimport_stock_price_start_import').innerHTML=run_pic;
                document.getElementById('sinchimport_stock_price_upload_files').innerHTML=run_pic;
                document.getElementById('sinchimport_stock_price_parse_products').innerHTML=run_pic;
                document.getElementById('sinchimport_stock_price_indexing_data').innerHTML=run_pic;
                document.getElementById('sinchimport_stock_price_finish_import').innerHTML=run_pic;
                                
            }   

        </script>
        ";
        return $html;
    }

}
