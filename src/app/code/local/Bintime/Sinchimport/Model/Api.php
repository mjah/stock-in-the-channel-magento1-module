<?php
class Bintime_Sinchimport_Model_Api extends Mage_Api_Model_Resource_Abstract
{
    public function test($arg)
    {
        return "!!!!!!!!!!Hello World! My argument is : ".$arg;
    }

    public function run_full_import($arg)
    {
        $import=Mage::getModel('sinchimport/sinch');

       if($import->is_imort_not_run()){            
            $dir = dirname(__FILE__);
            exec("nohup ".$import->php_run_string.$dir."/../sinch_import_start_ajax.php > /dev/null & echo $!");    
            sleep(5);
            if($import->is_imort_not_run()){
                $message_arr = $import->getDataOfLatestImport();
                $satus_arr=array('NOT OK', "Last Import finish with ".$message_arr['global_status_import']." at ".$message_arr['finish_import']."; Last status - ".$message_arr['detail_status_import']." ".$message_arr['error_report_message']);
            }else{    
                $satus_arr=array('OK', "Import started");
            }

        }else{
             $message_arr = $import->getDataOfLatestImport();             
             $satus_arr=array('NOT OK', "Import already started at ".$message_arr['start_import']." current_status - ".$message_arr['detail_status_import']);
        }

        return $satus_arr;
    }
    public function run_ps_import($arg)
    {
        $import=Mage::getModel('sinchimport/sinch');

        if($import->is_imort_not_run()){            
            $dir = dirname(__FILE__);
            exec("nohup ".$import->php_run_string.$dir."/../stock_price_sinch_import_start_ajax.php > /dev/null & echo $!");    
            sleep(5);
            if($import->is_imort_not_run()){
                $message_arr = $import->getDataOfLatestImport();
                $satus_arr=array('NOT OK', "Last ".$message_arr['import_type']." import finish with ".$message_arr['global_status_import']."; Last status '".$message_arr['detail_status_import']."' ".$message_arr['error_report_message']);
            }else{    
                $satus_arr=array('OK', "Price Stock Import started");
            }

        }else{
             $message_arr = $import->getDataOfLatestImport();             
             $satus_arr=array('NOT OK', $message_arr['import_type']." import already started at ".$message_arr['start_import']." current_status - '".$message_arr['detail_status_import']."'");
        }

        return $satus_arr;
    }

    public function get_import_status()
    {
        $import=Mage::getModel('sinchimport/sinch');
        $message_arr = $import->getDataOfLatestImport();
        if($import->is_imort_not_run()){
            if($message_arr['global_status_import']=='Successful'){
                $status=$message_arr['detail_status_import'];
            }elseif($message_arr['global_status_import']=='Failed'){
                $status=$message_arr['error_report_message'];
            }
            $satus_arr=array(
                                'is_import_running'     => 'Not Running',
                                'status'                => $status,
                                'last_finish_date'      => $message_arr['finish_import'],
                                'count_of_products'     => $message_arr['number_of_products'],
                                'import_type'           => $message_arr['import_type'],
                                'message'               => "Last ".$message_arr['import_type'].' import finished at '.$message_arr['finish_import'].' with '.$message_arr['global_status_import']."; Last status '".$message_arr['detail_status_import']."' ".$message_arr['error_report_message']  
                                    
                             );
        }else{
            $satus_arr=array(   'is_import_running'     =>'Running', 
                                'status'                => '',
                                'last_finish_date'      => $message_arr['finish_import'],
                                'count_of_products'     => $message_arr['number_of_products'],
                                'import_type'           => $message_arr['import_type'],
                                'message'   =>$message_arr['import_type']." import already started at ".$message_arr['start_import']." current_status - '".$message_arr['detail_status_import']."'");
        }

        return $satus_arr;
    }

}
