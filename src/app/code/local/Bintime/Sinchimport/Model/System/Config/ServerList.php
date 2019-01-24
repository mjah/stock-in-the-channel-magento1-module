<?php
/**
 * Class provides data for Magento BO
 *  @author malex <malex@bintime.com>
 *
 */
class Bintime_Sinchimport_Model_System_Config_ServerList
{
	public function toOptionArray()
	{    
		$paramsArray = array(
			'ftp.stockinthechannel.com'   => 'UK - ftp.stockinthechannel.com',
			'ftpus.stockinthechannel.com' => 'USA - ftpus.stockinthechannel.com',
			'ftp.canalstock.es' => 'Spain - ftp.canalstock.es',
			'ftp.canalstock.mx' => 'Mexico - ftp.canalstock.mx',
			'ftp.stockradar.be' => 'Belgium - ftp.stockradar.be',
			'ftpau.stockinthechannel.com' => 'Australia - ftpau.stockinthechannel.com',
			'ftpfr.stockinthechannel.com' => 'France - ftpfr.stockinthechannel.com',
			'ftpit.stockinthechannel.com' => 'Italy - ftpit.stockinthechannel.com',
			'ftpnl.stockinthechannel.com' => 'Holland - ftpnl.stockinthechannel.com',
			'ftpde.stockinthechannel.com' => 'Germany - ftpde.stockinthechannel.com',
			'ftpdemo.stockinthechannel.com' => 'Demo - ftpdemo.stockinthechannel.com'
		);
		return $paramsArray;
	}
}
