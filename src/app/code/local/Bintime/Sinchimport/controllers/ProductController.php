<?php
require_once 'Mage/Catalog/controllers/ProductController.php';
/**
 * Class rewrites standard product view controller to provide icecat data on the page
 *  @author Sergey Gozhedrianov <info@bintime.com>
 *
 */
class Bintime_Sinchimport_ProductController extends Mage_Catalog_ProductController{
	/**
	 * parent view Action
	 */
	public function viewAction(){
		parent::viewAction();
	}
	
	/**
	 * parent gallery Action
	 */
	public function galleryAction(){
		$this->getRequest()->setRouteName('catalog');
		parent::galleryAction();
	}
	
	/**
	 * parent Image Action
	 */
	public function imageAction()
	{
		$this->getRequest()->setRouteName('catalog');
		parent::imageAction();
	}
	
	/**
	 * before resolving url make sure that route name is correct
	 */
	public function preDispatch()
	{
		parent::preDispatch();
		if ($this->getRequest()->getActionName() == 'view'){
			$this->getRequest()->setRouteName('sinchimport');
		}
	} 
}
