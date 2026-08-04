<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

CModule::IncludeModule('panel.manager');

class checkFBO{
	public function __construct( $cabinet = 'WR' ){
		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$this->CurDB = new DBPanel;

		$this->cabinet = $cabinet;

		$this->bot = new TGNotifier;
		$this->control = [];

		$this->module = 'checkFbo_'.$this->cabinet;
		$strSql = "SELECT * FROM wdhs_wb_main_settings";

		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $this->arSetting[$row['cabinet']] = $row;
		}
		$this->clientId = $this->arSetting[$this->cabinet]['clientId'];
		$this->token = $this->arSetting[$this->cabinet]['api'];
		$this->settings = json_decode($this->arSetting[$this->cabinet]['settings'],true);
		// $this->auth = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("panel.manager");
    }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$this->arLog = array();
		$this->arLog['TIME_START'] = date('H:i:s');
		$date = date('Y-m-d');

		$strSql = "SELECT * FROM wdhs_wb_fbo_correct" ;
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$this->correct[$row['model']] = $row['quantity'];
		}
		$strSql = "SELECT * FROM individual_markups WHERE source = 'wbtl'";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		$this->markups = [];
		while ( $row = $results->Fetch() ){
			$this->markups[$row['model']] = floatval($row['markup']);
		}

		$startT = microtime(true);
		$arStat = [
			'status' => 'IN_PROCESS',
			'status_text' => 'Получаю товары',
			'percent' => '10',
			'time_start' => date('Y.m.d G:i:s')
		];

		$this->updateStatus( $this->module, $arStat );
		$this->getItemsWB_New();
		//$this->getItemsWB();
		$this->getItems();
		$this->updateStatus( $this->module, ['status_text' => 'Получаю себестоимости', 'percent' => '20'] );
		$this->getSebes();
		$this->getCiBrandID();
		// $this->GetTurnover();
		// $this->GetTurnoverBP();
		$this->getTurnoverFromTable();
		$this->updateStatus( $this->module, ['status_text' => 'Проверяю сток ФБО', 'percent' => '50'] );
		$this->checkFBOStock();
		$itemsT = microtime(true);
		$this->updateStatus( $this->module, ['status_text' => 'Сохраняю результат', 'percent' => '90'] );
		$this->PrintResult();

		if ($this->cabinet == 'WR') {
			$this->checkStockDifference();
		}

		$arStat = [
			'status' => 'COMPLETED',
			'status_text' => 'Выполнено',
			'percent' => '100',
			'time_end' => date('Y.m.d G:i:s')
		];
		$this->updateStatus( $this->module, $arStat );
		//$this->db->Update("wdhs_WB_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='price'", $err_mess.__LINE__);

	}

	public function wbTest(){
		$base_url = WB_BASE_URL;
		$path = "/api/v1/supplier/stocks";

		//$data_string = array('dateFrom' => date("Y-m-d\TH:i:s"));

		$author = $this->token;
		$api = new RestClient([
			'base_url' => 'https://discounts-prices-api.wb.ru',
			'curl_options' => array(
					CURLOPT_POST => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => false,
					CURLOPT_RETURNTRANSFER => TRUE,
					CURLOPT_HEADER => TRUE,
					CURLOPT_CUSTOMREQUEST => 'GET',
					CURLOPT_HTTPHEADER => array(
							'Content-Type: application/json',
							'Authorization: ' . $author,
					)
			)
	]);
	$path = '/api/v2/list/goods/filter?limit=10&filterNmID=75491826';
	$str_result = $api->post($path, []);
	//print_r(json_decode($str_result->response,true));
	$arResult = json_decode($str_result->response,true);
	//print_r($arResult);
	// print_r($this->stockFbo);
	// print_r($arResult);
}

	  public function getItemsWB(){
			$base_url = WB_BASE_URL;
			$path = "/api/v1/supplier/stocks";

			//$data_string = array('dateFrom' => date("Y-m-d\TH:i:s"));
			$data_string = array('dateFrom' => '2020-01-01');
			$author = $this->token;
			$api = new RestClient([
				'base_url' => 'https://statistics-api.wildberries.ru',
				'curl_options' => array(
						CURLOPT_POST => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_SSL_VERIFYHOST => false,
						CURLOPT_RETURNTRANSFER => TRUE,
						CURLOPT_HEADER => TRUE,
						CURLOPT_CUSTOMREQUEST => 'GET',
						CURLOPT_HTTPHEADER => array(
								'Content-Type: application/json',
								'Authorization: ' . $author,
						)
				)
		]);
		$path = '/api/v1/supplier/stocks?dateFrom=2020-01-01';
		$str_result = $api->post($path, []);
		//print_r(json_decode($str_result->response,true));
		$arResult = json_decode($str_result->response,true);
		foreach ($arResult as $key => $value) {
			if (isset($this->stockFbo[$value['supplierArticle']])) {
				$this->stockFbo[$value['supplierArticle']] =  intval($this->stockFbo[$value['supplierArticle']]) + intval($value['quantity']);
			} else {
				$this->stockFbo[$value['supplierArticle']] =  intval($value['quantity']);
			}
		}
		// print_r($this->stockFbo);
		//print_r($arResult);
	}

	public function getItemsWB_New(){
		$author = $this->token;
		$ch = curl_init('https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains?groupBySa=true');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: ' . $author,
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		$res = curl_exec($ch);
		curl_close($ch);
		// var_dump($res);
		$res = json_decode($res, true);

		$taskid = $res['data']['taskId'];

		unset($res);
		//$taskid = '8082f045-fec9-491a-b958-5a3ce5bf6fbd';
		sleep(20);

		 $ch = curl_init('https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains/tasks/'.$taskid.'/download');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: ' . $author,
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		$res = curl_exec($ch);
		curl_close($ch);
		// var_dump($res);
		$res = json_decode($res, true);
		// print_r($res);
		// die();
		$arResult = [];
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/getItemsWB.txt", print_r($res, true).PHP_EOL);
		foreach ($res as $key => $value) {
			if (count($value['warehouses']) > 0) {
				foreach ($value['warehouses'] as $wh) {
						if ($wh['warehouseName'] != 'Всего находится на складах' && $wh['warehouseName'] != 'В пути до получателей' && $wh['warehouseName'] != 'В пути возвраты на склад WB') {
						if (isset($this->stockFbo[$value['vendorCode']])) {
							$this->stockFbo[$value['vendorCode']] = intval($this->stockFbo[$value['vendorCode']]) + intval($wh['quantity']);
						} else {
							$this->stockFbo[$value['vendorCode']] = intval($wh['quantity']);
						}
					}
				}
			}
			// print_r($this->stockFbo);
		}

	}

  public function getItems(){
	    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_WBPRICE","PROPERTY_WBARTICLE2","PROPERTY_WBARTICLE3","PROPERTY_TYPEOFSKLAD","PROPERTY_WBTL_PRICE");
	    $arFilter = Array(
	      "IBLOCK_ID" => CProSet::IB_CATALOG,
				"!PROPERTY_WBARTICLE2" => false,
	      //"ID" => 5045,
				//"SECTION_ID" => 558
	      // "ID" => 178901
	    );
			if ($this->cabinet == 'WR') {
				$arFilter["!PROPERTY_WBARTICLE2"] = false;
			} else {
				$arFilter["!PROPERTY_WBARTICLE3"] = false;
			}
			//$arFilter["!ID"] = 14124;
	    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

			if ($this->cabinet == 'WR') {
				while ($el = $result->GetNext()){
				 if (isset($this->stockFbo[$el["PROPERTY_WBARTICLE2_VALUE"]])) {
					 if (empty($el['PROPERTY_WBARTICLE2_VALUE']) or $el['PROPERTY_WBARTICLE2_VALUE'] == '') {
							 $this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
					 }	else if (empty($el['PROPERTY_WBPRICE_VALUE']) or $el['PROPERTY_WBPRICE_VALUE'] == 0) {
							 $this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
					 } else {
						 $arSection = getSectionsElement($el["ID"]);
						 // if ($arSection[1]['ID'] == '558') {
						 $this->items[$el["PROPERTY_WBARTICLE2_VALUE"]] = [
							 "ID" => $el["ID"],
							 "ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
							 "WB_ARTICLE" => $el["PROPERTY_WBARTICLE2_VALUE"],
							 "PRICE" => $el["PROPERTY_WBPRICE_VALUE"],
						 ];
					 }
				 }
			 }
			} else {
				while ($el = $result->GetNext()){
				 if (isset($this->stockFbo[$el["PROPERTY_WBARTICLE3_VALUE"]])) {
					 if (empty($el['PROPERTY_WBARTICLE3_VALUE']) or $el['PROPERTY_WBTL_PRICE_VALUE'] == '') {
							 $this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
					 }	else if (empty($el['PROPERTY_WBPRICE_VALUE']) or $el['PROPERTY_WBTL_PRICE_VALUE'] == 0) {
							 $this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
					 } else {
						 $arSection = getSectionsElement($el["ID"]);
						 // if ($arSection[1]['ID'] == '558') {
						 $this->items[$el["PROPERTY_WBARTICLE3_VALUE"]] = [
							 "ID" => $el["ID"],
							 "ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
							 "WB_ARTICLE" => $el["PROPERTY_WBARTICLE3_VALUE"],
							 "PRICE" => $el["PROPERTY_WBTL_PRICE_VALUE"],
						 ];
					 }
				 }
			 }
			}

			//print_r(count($this->items));
		}

		public function getSebes(){

			foreach ($this->items as $key => &$v) {
				$tmpPrice = array();
				unset($minPrice);
				unset($price_id);

				if ($this->cabinet == 'WR') {
					$activetitle = 'active_wb';
				} else {
					$activetitle = 'active_wbtl';
				}


				$strSql = "SELECT price, id, count, model, supplier_id FROM ci_price WHERE model = '".$v['ARTICLE']."' AND {$activetitle} = 'Y' ORDER BY price ASC" ;
				$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					$tmpPrice[$row['id']] = [
						'price' =>floatval($row['price']),
						'model' =>$row['model'],
						'count' => intval($row['count']),
						'supplier_id' => $row['supplier_id']
					];
				}

				$strSql = "SELECT * FROM ci_reserved WHERE ARTICLE = '".$v['ARTICLE']."'";
				$resultRes = $this->db->Query($strSql, false, $err_mess.__LINE__);
				$excludeReserved = [];
				while ( $row = $resultRes->Fetch() ){
					$excludeReserved[$row['ARTICLE']] = $row['RESERVED'];
				}

				$curReserved = null;
				$suppExcl = [];
				foreach($tmpPrice as $k => $value){
					if ( $curReserved != null ){
						if ( ($curReserved - $value['count'] >= 0) && !in_array($value['supplier_id'], $suppExcl) ){
							$curReserved = $curReserved - $value['count'];
							$suppExcl[] = $value['supplier_id'];
						}else{
							$minPrice = $value['price'];
					    $price_id = $k;
							break;
						}
					}else{
						if ( isset($excludeReserved[$value['model']]) && ($excludeReserved[$value['model']] - $value['count'] >= 0) ){
							$curReserved = $excludeReserved[$value['model']] - $value['count'];
							$suppExcl[] = $value['supplier_id'];
						}else{
							$minPrice = $value['price'];
					    $price_id = $k;
							break;
						}
					}
				}

				if (!empty($tmpPrice) && (!empty($minPrice) && !empty($price_id) )) {
					$v['SEBES'] = $minPrice;
					$v['PRICE_ID'] = $price_id;
				} else {
					$v['SEBES'] ='';
					$v['PRICE_ID'] = '';
				}
		}
		// print_r($this->items);
	}

	public function getCiBrandID(){
		foreach ($this->items as $key => &$v) {
			$brands = array();
			$brand = '0';
			$strSql = "SELECT brand_id FROM ci_price WHERE model = '".$v['ARTICLE']."'" ;
			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$brands[] = $row['brand_id'];
			}
			foreach($brands as $k => $value){
				$brand = $value;
			}
			if (!empty($brand)) {
				$v['BRAND_ID'] = $brand;
			} else {
				$v['BRAND_ID'] = '';
			}
		}
		//print_r($this->items);
	}

	public function getNewPrice($brand_id,$newprice, $model = '')
	{
		$price = new CPanelPricelist;
		$analysis = new CPanelAnalysis;
		$objCurrency = new CPanelCurrency;


		if ($this->cabinet == 'WR') {
			$price_id = 'wb';
		} else {
			$price_id = 'wbtl';
		}

		$arSettings = array(
			"rate" => 1,
		);

		$arDefaultRRC = json_decode(CProSet::getOption("SETTINGS_RRC"), true)[$price_id];

		$arCurrency = $objCurrency->getDetail($arSettings["currency"]);

		$itemPrice = floatval($newprice);
		$productID = 0;
		$markup = 1;

		//$itemPrice = (float)round($itemPrice, $arSettings["round"]);
		$profile = $analysis->getListByFilter(array("brand_id" => $brand_id,'price_id'=>$price_id));
		// print_r('<br>');
		// print_r($profile);
		if( is_array($profile) && !empty($profile) ){
			$profile = $profile[0];
			$profile["settings"] = json_decode( $profile["settings"], true );

			foreach($profile["settings"] as $key => $arItem){
				if($itemPrice >= $arItem["price_from"] && $itemPrice <= $arItem["price_to"] && $arItem["markup"] > 0)
					$markup = (float)$arItem["markup"];
			}

		}elseif(is_array($arDefaultRRC["rules"])){
			foreach($arDefaultRRC["rules"] as $key => $arItem){
				if($itemPrice >= $arItem["price_from"] && $itemPrice <= $arItem["price_to"] && $arItem["markup"] > 0)
					$markup = (float)$arItem["markup"];
			}
			//prent($markup);
		}
		// print_r('<br>');
		// print_r($markup);
		// print_r('<br>');
		// print_r($itemPrice);
		// print_r('<br>');

		if ( !empty($model) && !empty($this->markups[$model]) ){
			$markup = $this->markups[$model];
		}

		$itemPrice = round($itemPrice * $markup, 0);
		return $itemPrice;
	}

	public function GetTurnover()	{
		$this->fromMS = array();
		$ms = new MoyskladAPI('s1');
		$msItems = $ms->getTurnoverDay(2);
		foreach ($msItems as $key => $value) {
			if (!empty($value['assortment']['article']) and ($value['income']['quantity'] != '0') and empty($this->fromMS[$value['assortment']['article']]) ) {
				$this->fromMS[$value['assortment']['article']] = intval(($value['income']['sum'] / 100) / $value['income']['quantity']);
			}
		}
		for ( $i = 1; $i <= 3; $i++ ){
			$msItems = $ms->getTurnoverWeek($i);
			foreach ($msItems as $key => $value) {
				if (!empty($value['assortment']['article']) and ($value['income']['quantity'] != '0') and empty($this->fromMS[$value['assortment']['article']]) ) {
					$this->fromMS[$value['assortment']['article']] = intval(($value['income']['sum'] / 100) / $value['income']['quantity']);
				}
			}
		}
		// print_r($this->fromMS);
		// die();
	}

	public function getProductsMS()
	{
		$stockFboTemp = [];
		foreach ($this->stockFbo as $key => $value) {
			$keyMod = end( explode('_',$key) );
			$stockFboTemp[$keyMod] = "'" . $value . "'";
		}
		$modelKeys = array_keys($stockFboTemp);
		$models = "";
		$i = 0;
		foreach ($modelKeys as $value){
			$models .= (count($modelKeys) - 1 == $i ) ? "'{$value}'" : "'{$value}',";
			$i++;
		}
		$strSql = "SELECT item_number, product_id FROM ci_ms_directory_products WHERE site_id = 's1' AND item_number IN ({$models})";
		$resultDB = $this->db->Query($strSql, false, $err_mess.__LINE__);
		$productIds = [];
		while( $row = $resultDB->Fetch() ){
			if ( !isset($this->fromMS[$row['item_number']]) ){
				$productIds[$row['item_number']] = $row['product_id'];
			}
		}
		return $productIds;
	}

	public function GetTurnoverBP()
	{
		$ms = new MoyskladAPI('s1');
		$products = $this->getProductsMS();
		// var_dump( count($this->fromMS) );
		// var_dump( count($products) );
		// die;
		foreach ($products as $item => $product_id) {
			if ( !empty($this->fromMS[$item]) ) continue;
			for ($i = 9; $i <= 24; $i = $i + 3){
				$msItems = $ms->getTurnoverByProduct($i, $product_id);
				if ( empty($msItems) ) {
					sleep(1);
					continue;
				}
				foreach ($msItems as $key => $value) {
					if ( !empty($value['assortment']['article']) and ($value['income']['quantity'] != '0') and empty($this->fromMS[$value['assortment']['article']]) ) {
						$this->fromMS[$value['assortment']['article']] = ($value['income']['sum'] / 100) / $value['income']['quantity'];
						break 2;
					}
				}
			}
			sleep(1);
		}
		// var_dump( count($this->fromMS) );
	}

	public function getTurnoverFromTable()
	{
		$this->fromMS = array();
		$result = $this->CurDB->query("SELECT * FROM ms_turnover_wb");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->fromMS[$row['model']] = intval($row['quantity']);
		}
		unset($result);
		unset($rows);
	}

	public function calculatePercentageDifference($number1, $number2) {
	    $average = (floatval($number1) + floatval($number2)) / 2;
	    $difference = floatval($number1) - floatval($number2);
	    $percentDifference = ($difference / $average) * 100;

	    return $percentDifference;
	}

  public function checkFBOStock()
  {
		if (!empty($this->stockFbo)) {
			$this->answerc = [];

			$BDprice = 'wdhs_wb_fbo_price_'.$this->cabinet;
			$BDstock = 'wdhs_wb_fbo_stock_'.$this->cabinet;
			$BDsebes = 'wdhs_wb_fbo_sebes_'.$this->cabinet;

			if ($this->cabinet == 'WR') {
				$BDprice = 'wdhs_wb_fbo_price';
				$BDstock = 'wdhs_wb_fbo_stock';
				$BDsebes = 'wdhs_wb_fbo_sebes';
			}

		  $this->db->Query("DELETE FROM {$BDprice} WHERE 1=1", false, $err_mess.__LINE__);
		  $this->db->Query("DELETE FROM {$BDstock} WHERE 1=1", false, $err_mess.__LINE__);
			$this->db->Query("DELETE FROM {$BDsebes} WHERE 1=1", false, $err_mess.__LINE__);
			print_r($this->items);
			foreach ($this->stockFbo as $key => $value) {
				if (isset($this->items[$key])) {
					if (isset($this->correct[$this->items[$key]['ARTICLE']])) {
						$oldValue = $value;
						$value = intval($value) - intval($this->correct[$this->items[$key]['ARTICLE']]);
						if ($value < 0 ) {
							$articleDelete = $this->items[$key]['ARTICLE'];
						 	$this->db->Query("DELETE FROM wdhs_wb_fbo_correct WHERE model = '{$articleDelete}'", false, $err_mess.__LINE__);
							// print_r($this->items[$key]['ARTICLE']);
							$value = $oldValue;
							unset($articleDelete);
						} else {
							$this->answerc[$this->items[$key]['ARTICLE']] = 'Корректировка: Кол-во ФБО - '.$oldValue.'; Коректировка - '.$this->correct[$this->items[$key]['ARTICLE']].'';
						}

					}
					if ($value != 0 && $value >= 1) {
						$in = array(
							"article" => "'".$this->items[$key]['ARTICLE']."'",
						);
						$this->db->Insert($BDstock, $in, $err_mess.__LINE__);

						$this->control[] = [ $this->items[$key]['ARTICLE'] ];

						if (isset($this->fromMS[$this->items[$key]['ARTICLE']])) {
							$newprice = self::getNewPrice($this->items[$key]['BRAND_ID'],$this->fromMS[$this->items[$key]['ARTICLE']], $this->items[$key]['ARTICLE']);
							$this->answer[$this->items[$key]['ARTICLE']]['count'] = $value;
							$this->answer[$this->items[$key]['ARTICLE']]['asnw'] = "Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
							Остаток на FBO WB: {$value}<br>
							Себес из МС:{$this->fromMS[$this->items[$key]['ARTICLE']]}<br>
							Новая цена:{$newprice}<br>
							<span style=\"color:green\">Уснавливаем цену из МС</span><br><hr><br><br>";
							$in = array(
								"article" => "'".$this->items[$key]['ARTICLE']."'",
								"price" => "'".$newprice."'",
							);
							$this->db->Insert($BDprice, $in, $err_mess.__LINE__);
						}



					}
					$this->analyticsData[ $this->items[$key]['ARTICLE'] ] = $value;
					//}
				}
			}
	  	// print_r($this->answer);
		}
  }

	public function PrintResult()
	{
		// foreach ($this->answer as $key => $value) {
		// 	print_r($value);
		// }
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/fbo/{$this->cabinet}/log_c.txt", print_r(json_encode($this->answerc), true).PHP_EOL);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/fbo/{$this->cabinet}/log.txt", print_r(json_encode($this->answer), true).PHP_EOL);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/export/analytics.json", print_r(json_encode($this->analyticsData), true));
	}

	public function updateStatus( string $code, array $arStat ):void
  {
    if ( empty($arStat) ) return;
    $strSql = "UPDATE wb_agents SET ";
    foreach ($arStat as $field => $value) {
      if ( array_key_last($arStat) == $field ){
        $str = "{$field} = '{$value}'";
      }else{
        $str = "{$field} = '{$value}', ";
      }
      $strSql .= $str;
    }
    $strSql .= " WHERE code = '{$code}'";
    try{
      $this->CurDB->query( $strSql );
    }catch( Throwable $ignored){
			print_r('Не удалось обновить статус' . $ignored . "\n");
    }
  }

	private function checkStockDifference():void
	{
		$cab = $this->cabinet;
		$module = "wb_checkFbo_{$cab}";
		$nameTemplateSys = "checkFbo_{$cab}";
		$nameTemplateSys2 = "checkFbo_diff_{$cab}";
		$nameTemplateBot = "ФБО_WB_{$cab}";
		$nameTemplateBot2 = "ФБО_Разница_WB_{$cab}";
		$messageHeader = "<b>Модуль ФБО WB {$cab}:</b>\n\n";

		$filenamePrev = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/export/{$nameTemplateSys}_prev.xlsx";
		$filenameLast = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/export/{$nameTemplateSys}_last.xlsx";

		$row = $this->CurDB->select(['*'], 'modules_control')->where('module', $module)->make();
		$oldValue = intval( $row[0]['old_value'] );
		$newValue = intval( $row[0]['new_value'] );
		$lastUploadTime = $row[0]['date'];
		$control = count($this->control);
		$dataForBot = $this->control;

		if ( $newValue != 0 ){
			$diff = ($newValue - $control) / $newValue * 100;
		}elseif( $newValue == 0 && $control > 0 ){
			$diff = 100;
		}else{
			$diff = 0;
		}

		$diffInfo = abs( round($diff, 2) );
		print_r("difference - {$diff}\n");
		//
		if ( $diffInfo >= $this->settings['threshold'] ){

			if ($diff > 0 ){
				$cWord = 'меньше';
			}else{
				$cWord = 'больше';
			}

			$this->bot->sendMessage("{$messageHeader}<b>⚠На ФБО числится на <u>{$diffInfo}%</u> {$cWord} товаров!</b>\n\nВремя предыдущего запуска: {$lastUploadTime}\n\n✅ <b>Прошлая итерация: <u>{$newValue}</u></b>.\n❌ <b>Текущая итерация: <u>{$control}</u></b>\n\n<i>Убедитесь в отсутствии ошибок!</i>\n<i>Ниже прикреплены файлы с артикулами выгрузок</i>");
			if ( file_exists($filenamePrev) ){
				$this->bot->sendFile($filenamePrev, "{$nameTemplateBot}_prev.xlsx");
			}

		}

		if ( file_exists($filenameLast) )	rename($filenameLast, $filenamePrev);

		$newFile = $this->buildXlsx( 'wb', "{$nameTemplateSys}_last", ['Модель'], $dataForBot );
		// $arDiff = $this->compareUpload( $filenamePrev, $dataForBot );
		if ( $diffInfo >= $this->settings['threshold'] ){

			if ($diff > 0 ){
				$arDiff = $this->compareUploadLess( $filenamePrev, $dataForBot );
				$diffFile = $this->buildXlsx( 'wb', "{$nameTemplateSys2}", ['Модель'], $arDiff );
			}else{
				$arDiff = $this->compareUploadMore( $filenamePrev, $dataForBot );
				$diffFile = $this->buildXlsx( 'wb', "{$nameTemplateSys2}", ['Модель'], $arDiff );
			}

			if ( $newFile ){
				$this->bot->sendFile($newFile, "{$nameTemplateBot}_last.xlsx");
				$this->bot->sendFile($diffFile, "{$nameTemplateBot2}.xlsx");
			}else{
				$this->bot->sendMessage("Файл {$nameTemplateBot}_last.xlsx не может быть отправлен ввиду непредвиденной ошибки: массив пустой");
			}

		}

		if ( $newValue == 0 && $control == 0 ){
			$this->bot->sendMessage("{$messageHeader}<b>❌Числится нулевое количество товаров в двух или более итерациях подряд.</b>\n\n⚠<i>Необходимо срочно исправить ошибку!</i>");
		}
		$date = date('Y-m-d G:i:s');
		$strSql = " UPDATE modules_control SET old_value = '{$newValue}', new_value = '{$control}', date = '{$date}' WHERE module = '{$module}'";
		$this->CurDB->query( $strSql );
	}

	private function buildXlsx( string $module, string $name, array $headers, array $data ):string
	{
		if (!class_exists('SpreadsheetReader')){
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

		$xls = new PHPExcel();
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $sheet->setTitle('List 1');

		$alphabet = range('A', 'Z');
		foreach ( $headers as $key => $value ){
			$sheet->setCellValueExplicit("{$alphabet[$key]}1", $value, PHPExcel_Cell_DataType::TYPE_STRING);
		}
		foreach ( $data as $i => $value ){
			$row = $i + 2;
			foreach ( $value as $k => $elem ){
				$sheet->setCellValueExplicit("{$alphabet[$k]}{$row}", $elem, PHPExcel_Cell_DataType::TYPE_STRING);
			}
		}
		$objWriter = new PHPExcel_Writer_Excel2007($xls);
    $filename = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/{$module}/export/{$name}.xlsx";
    $objWriter->save( $filename );

		return $filename;
	}

	private function compareUploadLess(string $path, array $data):array
	{
		if (!class_exists('SpreadsheetReader')){
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

		$xls = PHPExcel_IOFactory::load($path);
		$xls->setActiveSheetIndex(0);
		$sheet = $xls->getActiveSheet();

		$arModelsXlsx = [];

		$arUpload = [];
		foreach( $data as $row ){
			$arUpload[ $row[0] ] = $row[0];
		}

		foreach ( $sheet->toArray() as $i => $row ) {
			if ( $i == 0 ) continue;
			if ( empty($arUpload[$row[0]]) ){
					$arDiff[] = [ $row[0] ];
			}
		}
		return $arDiff;
	}

	private function compareUploadMore(string $path, array $data):array
	{
		if (!class_exists('SpreadsheetReader')){
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

		$xls = PHPExcel_IOFactory::load($path);
		$xls->setActiveSheetIndex(0);
		$sheet = $xls->getActiveSheet();

		$arModelsXlsx = [];

		foreach ( $sheet->toArray() as $i => $row ) {
			if ( $i == 0 ) continue;
			$arModelsXlsx[ $row[0] ] = [ $row[0] ];
		}

		$arUpload = [];
		foreach( $data as $row ){
			if ( empty($arModelsXlsx[$row[0]]) ){
					$arDiff[] = [ $row[0] ];
			}
		}
		return $arDiff;
	}
}
if ( in_array($argv[1], ['WR', 'TL']) ){
  $cab = $argv[1];
}else{
  $cab = 'WR';
}
(new checkFBO($cab))->run();
