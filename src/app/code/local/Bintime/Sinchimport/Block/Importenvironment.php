<?php




class Bintime_Sinchimport_Block_Importenvironment extends Mage_Adminhtml_Block_System_Config_Form_Field {




	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
		$this->setElement($element);
		$url = $this->getUrl('sinchimport/index');
		$this->setElement($element);

		$html = '';

		//$html .= '<div class="comment"><H3>'.("Your Stock In The Channel Environment Check Summary" ).'</H3></div>';

		//$html .= '<div class="comment"><H3>'.("In order for this extension to work, your server needs to be configured in a particular way and have sufficient memory." ).'</H3></div>';


		$html .= '
            <table class="history">
            <thead>
            <tr>
            <th>Checked</th>
		<th>Necessary, Error And Fix</th>	

            </tr>
            </thead><tbody>';
//		<th>Necessary</th>	
//            <th>Fix</th>

		$errors_count = 0;

		// Memory total
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkMemory();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'blue')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Notice:', 'blue')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'blue')} $fixmsg             </td>  </tr>
				";
		};



		// Mysql parameter LOCAL DATA LOCAL
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkLoaddata();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'red')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Error:', 'red')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'red')} $fixmsg             </td>  </tr>
				";
		};


		// PHP safe mode
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkPhpsafemode();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'red')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Error:', 'red')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'red')} $fixmsg             </td>  </tr>
				";
		};


		// Mysql parameter wait_timeout
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkWaittimeout();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'blue')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Notice:', 'blue')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'blue')} $fixmsg             </td>  </tr>
				";
		};


		// Mysql parameter innodb_buffer_pool_size
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkInnodbbufferpoolsize();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'blue')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Notice:', 'blue')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'blue')} $fixmsg             </td>  </tr>
				";
		};


		// Conflict with installed module 
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkConflictsWithInstalledModules();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'blue')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Notice:', 'blue')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'blue')} $fixmsg             </td>  </tr>
				";
		};

		
		// PHP run string
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkPhprunstring();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'red')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Error:', 'red')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'red')} $fixmsg             </td>  </tr>
				";
		};


		// Chmod wget file
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkChmodwgetdatafile();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'red')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Error:', 'red')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'red')} $fixmsg             </td>  </tr>
				";
		};


		// Chmod wget cron.php file
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkChmodwgetcronphpfile();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'red')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Error:', 'red')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'red')} $fixmsg             </td>  </tr>
				";
		};

		// Chmod wget cron.sh file
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkChmodwgetcronshfile();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'red')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Error:', 'red')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'red')} $fixmsg             </td>  </tr>
				";
		};

		// Mysql stored procedure filter_sinch_products_s
		list($status, $caption, $critical, $value, $measure, $errmsg, $fixmsg) = Mage::getModel('sinchimport/sinch')->checkProcedure();
		if ($status == 'error') $errors_count++;
		if ($status == 'error') { 
			$html .= // $caption: $value $measure
				"
					<tr>  <td nowrap rowspan=4> $caption </td> </tr>
					<tr>  <td nowrap> {$this->_colored('Necessary:', 'red')} $critical $measure  </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Error:', 'red')} $errmsg             </td>  </tr>
					<tr>  <td nowrap> {$this->_colored('Fix:', 'red')} $fixmsg             </td>  </tr>
				";
		};


		$html .= '  
			</tbody>
			</table>';

		$html .= '<div class="comment"><H3>'.("There are $errors_count notices." ).'</H3></div>';

		// all checks
		return $html;

	} // protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)

    protected function _colored($str, $color){
        return "<b><span style='color:{$color}'>{$str}</span></b>";
    }


} // class Bintime_Sinchimport_Block_Importenvironment extends Mage_Adminhtml_Block_System_Config_Form_Field
