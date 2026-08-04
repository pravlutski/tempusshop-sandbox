<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');

class checkFBO{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;

		$this->auth = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
    }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$this->arLog = array();
		$this->arLog['TIME_START'] = date('H:i:s');
		$date = date('Y-m-d');

		$startT = microtime(true);


		$this->getItemsWB();
		$this->getItems();
		$this->getSebes();
		$this->getCiBrandID();
		$this->GetTurnover();
		$this->GetTurnoverBP();
		$this->checkFBOStock();
		$itemsT = microtime(true);

		$this->PrintResult();

		//$this->db->Update("wdhs_WB_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='price'", $err_mess.__LINE__);

	}

	public function wbTest(){
		$base_url = WB_BASE_URL;
		$path = "/api/v1/supplier/stocks";

		//$data_string = array('dateFrom' => date("Y-m-d\TH:i:s"));

		$author = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");
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
			$author = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");
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

  public function getItems(){
	    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_WBPRICE","PROPERTY_WBARTICLE2","PROPERTY_TYPEOFSKLAD");
	    $arFilter = Array(
	      "IBLOCK_ID" => CProSet::IB_CATALOG,
				"!PROPERTY_WBARTICLE2" => false,
	      //"ID" => 5045,
				//"SECTION_ID" => 558
	      // "ID" => 178901
	    );
			//$arFilter["!ID"] = 14124;
	    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);


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
			//print_r(count($this->items));
		}

		public function getSebes(){
			foreach ($this->items as $key => &$v) {
				$tmpPrice = array();
				$strSql = "SELECT price, id, count, model, supplier_id FROM ci_price WHERE model = '".$v['ARTICLE']."' AND active_wb = 'Y' ORDER BY price ASC" ;
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
						if ( $excludeReserved[$value['model']] - $value['count'] >= 0 ){
							$curReserved = $excludeReserved[$value['model']] - $value['count'];
							$suppExcl[] = $value['supplier_id'];
						}else{
							$minPrice = $value['price'];
					    $price_id = $k;
							break;
						}
					}
				}

				if (!empty($tmpPrice)) {
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

	public function getNewPrice($brand_id,$newprice)
	{
		$price = new CPanelPricelist;
		$analysis = new CPanelAnalysis;
		$objCurrency = new CPanelCurrency;

		$price_id = 'wb';
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
		if(is_array($profile)){
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
		$itemPrice = round($itemPrice * $markup, 0);
		return $itemPrice;
	}

	public function GetTurnover()	{
		$this->fromMS = array();
		$ms = new MoyskladAPI('s1');
		for ($i = 3; $i <= 6; $i = $i + 3){
			$msItems = $ms->getTurnover($i);
			foreach ($msItems as $key => $value) {
				if ( !empty($value['assortment']['article']) and ($value['income']['quantity'] != '0') and empty($this->fromMS[$value['assortment']['article']]) ) {
					$this->fromMS[$value['assortment']['article']] = ($value['income']['sum'] / 100) / $value['income']['quantity'];
				}
			}
		// 	sleep(1);
		}
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

	public function calculatePercentageDifference($number1, $number2) {
	    $average = (floatval($number1) + floatval($number2)) / 2;
	    $difference = floatval($number1) - floatval($number2);
	    $percentDifference = ($difference / $average) * 100;

	    return $percentDifference;
	}

  public function checkFBOStock()
  {
	  // $this->db->Query("DELETE FROM wdhs_wb_fbo_price WHERE 1=1", false, $err_mess.__LINE__);
	  // $this->db->Query("DELETE FROM wdhs_wb_fbo_stock WHERE 1=1", false, $err_mess.__LINE__);
		//print_r($this->items);
		foreach ($this->stockFbo as $key => $value) {
			if (isset($this->items[$key])) {
				//if ($this->items[$key]['ARTICLE'] == 'F-91W-1Q') {
				if ($value != 0 && $value >= 1) {
					// print_r($this->items[$key]['ARTICLE']);
					// print_r('<br>');
					// print_r($this->fromMS[$this->items[$key]['ARTICLE']]);
					// print_r('<br>');
					// print_r($this->items[$key]['SEBES']);
					// print_r('<br>');
					if (!isset($this->fromMS[$this->items[$key]['ARTICLE']])) {
						$this->answer[$this->items[$key]['ARTICLE']] = "Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
						Остаток на FBO WB: {$value}<br>
						Себес из МС:<span style=\"color:red\">Отсутствует в отчетах за последние 24 месяца</span><br>
						Себес из БТ:{$this->items[$key]['SEBES']}<br>
						<span style=\"color:red\">Не производим изменений</span><br><hr><br><br>";

					}else{
						if (!empty($this->items[$key]['SEBES'])) {
							$diff = self::calculatePercentageDifference($this->items[$key]['SEBES'],$this->fromMS[$this->items[$key]['ARTICLE']]);
						} else {
							$diff = null;
						}
						if ($diff === null) {
							$newprice = self::getNewPrice($this->items[$key]['BRAND_ID'],$this->fromMS[$this->items[$key]['ARTICLE']]);
							$this->answer[$this->items[$key]['ARTICLE']] = "Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
							Остаток на FBO WB: {$value}<br>
							Себес из МС:{$this->fromMS[$this->items[$key]['ARTICLE']]}<br>
							Себес из БТ: В данный момент товар недоступен для WB у нас в системе<br>
							<span style=\"color:green\">Устанавливаем новую цену</span>:{$newprice}<br><hr><br><br>";

							$in = array(
				        "article" => "'".$this->items[$key]['ARTICLE']."'",
				        "price" => "'".$newprice."'",
				      );
				      // $this->db->Insert("wdhs_wb_fbo_price", $in, $err_mess.__LINE__);

						}else if ($diff > 10) {
							$newprice = self::getNewPrice($this->items[$key]['BARND_ID'],$this->fromMS[$this->items[$key]['ARTICLE']]);
							$this->answer[$this->items[$key]['ARTICLE']] = "Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
							Остаток на FBO WB: {$value}<br>
							Себес из МС:{$this->fromMS[$this->items[$key]['ARTICLE']]}<br>
							Себес из БТ:{$this->items[$key]['SEBES']}<br>
							<span style=\"color:red\">Отличаются на {$diff}%</span><br>
							<span style=\"color:green\">Устанавливаем новую цену</span>:{$newprice}<br>
							<span style=\"color:red\">Передаем остаток 0</span><br><hr><br><br>";
							$in = array(
								"article" => "'".$this->items[$key]['ARTICLE']."'",
								"price" => "'".$newprice."'",
							);
							// $this->db->Insert("wdhs_wb_fbo_price", $in, $err_mess.__LINE__);

							$in = array(
								"article" => "'".$this->items[$key]['ARTICLE']."'",
							);
							// $this->db->Insert("wdhs_wb_fbo_stock", $in, $err_mess.__LINE__);
						} else if ($diff < 0) {
							$this->answer[$this->items[$key]['ARTICLE']] = "Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
							Остаток на FBO WB: {$value}<br>
							Себес из МС:{$this->fromMS[$this->items[$key]['ARTICLE']]}<br>
							Себес из БТ:{$this->items[$key]['SEBES']}<br>
							<span style=\"color:green\">Цена в БТ меньше чем в МС</span><br>
							<span style=\"color:green\">Оставляем старую цену</span><br><hr><br><br>";
						}else{
							$newprice = self::getNewPrice($this->items[$key]['BARND_ID'],$this->fromMS[$this->items[$key]['ARTICLE']]);
							$this->answer[$this->items[$key]['ARTICLE']] = "Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
							Остаток на FBO WB: {$value}<br>
							Себес из МС:{$this->fromMS[$this->items[$key]['ARTICLE']]}<br>
							Себес из БТ:{$this->items[$key]['SEBES']}<br>
							<span style=\"color:green\">Отличаются на {$diff}%</span><br>
							<span style=\"color:green\">Устанавливаем новую цену</span>:{$newprice}<br><hr><br><br>";
							$in = array(
								"article" => "'".$this->items[$key]['ARTICLE']."'",
								"price" => "'".$newprice."'",
							);
							// $this->db->Insert("wdhs_wb_fbo_price", $in, $err_mess.__LINE__);
						}
					}
				}
				//}
			}
		}
  	print_r($this->answer);
  }

	public function PrintResult()
	{
		// foreach ($this->answer as $key => $value) {
		// 	print_r($value);
		// }
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/fbo/logTest.txt", print_r(json_encode($this->answer), true).PHP_EOL);
	}

}

(new checkFBO())->run();
