<?php

class Bintime_Sinchimport_Block_Backupbutton extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
		$this->setElement($element);
		$url = $this->getUrl('sinchimport/backup');
		$this->setElement($element);

		$html = '';

		$start_import_button = $this->getLayout()->createBlock('adminhtml/widget_button')
			->setType('button')
			->setClass('scalable')
			->setLabel('Backup Now')
			->setOnClick("setLocation('$url')")
			->toHtml();

		$html .= $start_import_button;

		return $html;
	}
}
