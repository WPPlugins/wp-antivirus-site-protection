<?php

include_once(dirname(__FILE__).'/sgantivirus.class.php');

error_reporting(0);

$action = $_REQUEST['action'];

switch ($action)
{
	// Start Scan AJAX
	case 'StartScan_AJAX':
		SGAntiVirus_module::scan();
		break;
		
	// Get Scan Progress AJAX	
	case 'GetScanProgress_AJAX':
		echo SGAntiVirus_module::readProgress();
		break;
		
	// Get Report AJAX
	case 'GetScanReport_AJAX':
		echo SGAntiVirus_module::getReportText();
		break;
}

exit;

/* Dont remove this code: SiteGuarding_Block_6C33B41CEC02 */
?>