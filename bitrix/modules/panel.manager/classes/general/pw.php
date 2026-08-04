<?
//use Bitrix\Sale;

class PWexchange{
	
	function __construct(){
		Cmodule::IncludeModule('main');
		Cmodule::IncludeModule('iblock');
		Cmodule::IncludeModule('catalog');
		Cmodule::IncludeModule('sale');
	}
	function getOrder(){
		//$res = file_get_contents("https://presidentwatches.ru/scripts/tempus/orders.php");

		//$result = json_decode($res, true);
		
		return $result;
	}
	
}

?>