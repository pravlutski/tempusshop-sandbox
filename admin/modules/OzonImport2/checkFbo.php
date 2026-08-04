<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');

class checkFBO{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$this->metric = new Metric();
		$strSql = "SELECT * FROM wdhs_ozon_main_settings_new";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $arSetting[$row['name']] = $row['value'];
		}
		$this->mcom = $arSetting['com'];
		$this->mnewCom = $arSetting['newCom'];
    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];

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
		$this->FBO_WH = [];
		$this->arLog['TIME_START'] = date('H:i:s');
		$date = date('Y-m-d');

		$startT = microtime(true);
		$this->getStockFboWH();

		$this->getItems();
		$this->getStockFbo();
		$this->getIndMarkups();
		$this->getSebes();
		$this->getCiBrandID();
		$this->GetTurnover();
		$this->checkFBOStock();
		$itemsT = microtime(true);

		$this->PrintResult();

		//$this->db->Update("wdhs_ozon_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='price'", $err_mess.__LINE__);

	}

  public function getItems(){
	    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_OZSB_PRICE","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD");
	    $arFilter = Array(
	      "IBLOCK_ID" => CProSet::IB_CATALOG,
	      //"ID" => 13263,
				//"SECTION_ID" => 558
				"PROPERTY_OZON_ACTIVE_VALUE" => 'Да'
	      //"ID" => 178901
	    );
			//$arFilter["!ID"] = 14124;
	    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);


	    while ($el = $result->GetNext()){
				if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') {
						$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
				}	else if (empty($el['PROPERTY_OZSB_PRICE_VALUE']) or $el['PROPERTY_OZSB_PRICE_VALUE'] == 0) {
						$this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
				} else {
					$arSection = getSectionsElement($el["ID"]);
					// if ($arSection[1]['ID'] == '558') {
		    	$this->items[$el["PROPERTY_WBARTICLE_VALUE"]] = [
		    		"ID" => $el["ID"],
						"ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
		    		"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
		    		"PRICE" => $el["PROPERTY_OZSB_PRICE_VALUE"],
		    	];
				}
	    }
			//print_r($this->items);
		}

		public function getStockFboWH() {
			$check = true;
			$lim = 1000;
			$off = 0;
			while ($check) {
				$data = [
					"limit" => $lim,
			    "offset" => $off,
			    "warehouse_type" => "ALL"
				];

				$ch = curl_init($this->api_url . '/v2/analytics/stock_on_warehouses');
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Api-Key:' . $this->token,
					'Client-Id:' . $this->client_id,
					'Content-Type:application/json'
				));
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_HEADER, false);
				$res = curl_exec($ch);
				curl_close($ch);
				$res = json_decode($res, true);

				if (!empty($res['result']['rows'])) {
					foreach ($res['result']['rows'] as $key => $value) {
						if ($value['free_to_sell_amount'] > 0) {
							if (strpos(strtolower($value['warehouse_name']), 'екатерин') === false
								|| strpos(strtolower($value['warehouse_name']), 'красноя') === false
								|| strpos(strtolower($value['warehouse_name']), 'новосиб') === false
								|| strpos(strtolower($value['warehouse_name']), 'омск') === false
								|| strpos(strtolower($value['warehouse_name']), 'калининг') === false
								|| strpos(strtolower($value['warehouse_name']), 'хабаров') === false
								|| strpos(strtolower($value['warehouse_name']), 'минск') === false
								|| strpos(strtolower($value['warehouse_name']), 'астан') === false
								|| strpos(strtolower($value['warehouse_name']), 'алмат') === false ) {
								$this->FBO_WH[$value['item_code']][] = $value;
							}
						}
					}
					$off = $off + $lim;
				} else {
					$check = false;
				}

			}
			// print_r(count($this->FBO_WH));
			// print_r($this->FBO_WH);
	  }

	  public function getStockFbo() {
			$this->stockFbo = array();
	    foreach ($this->items as $key => &$arItem) {
				$offerIDs[] = $arItem['OZON_ARTICLE'];
	    }
			$offerIDsC = array_chunk($offerIDs, 1000);
			foreach ($offerIDsC as $key => $value) {
				$data = [
					'filter' => array('offer_id' => $value),
					'last_id' => "",
					'limit' => 1000,
				];

				$ch = curl_init($this->api_url . '/v3/product/info/stocks');
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Api-Key:' . $this->token,
					'Client-Id:' . $this->client_id,
					'Content-Type:application/json'
				));
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_HEADER, false);
				$res = curl_exec($ch);
				curl_close($ch);
				$res = json_decode($res, true);

				if (!empty($res['result']['items'])) {
					foreach ($res['result']['items'] as $key => $value) {
						$this->stockFbo[$value['offer_id']] = intval($value['stocks'][1]['present']) - intval($value['stocks'][1]['reserved']);
					}
				}


			}
			//print_r($this->stockFbo);
	  }

		public function getIndMarkups(){
			$strSql = "SELECT * FROM individual_markups WHERE source = 'os'";
			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			$this->markups = [];
			while ( $row = $results->Fetch() ){
				$this->markups[ $row['model'] ] = floatval($row['markup']);
			}
		}

		public function getSebes(){
			foreach ($this->items as $key => &$v) {
				$tmpPrice = array();
				unset($minPrice);
				unset($price_id);
				$strSql = "SELECT price, id, count, model, supplier_id FROM ci_price WHERE model = '".$v['ARTICLE']."' AND active_ozti = 'Y' ORDER BY price ASC" ;
				$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					$tmpPrice[$row['id']] = [
						'price' =>floatval($row['price']),
						'model' =>$row['model'],
						'count' => intval($row['count']),
						'supplier_id' => $row['supplier_id']
					];
				}
				print_r($tmpPrice);
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
				print_r('#####');
				print_r($tmpPrice);
				if (!empty($tmpPrice) && (!empty($minPrice) && !empty($price_id) )) {
					$v['SEBES'] = $minPrice;
					$v['PRICE_ID'] = $price_id;
				} else {
					$v['SEBES'] ='';
					$v['PRICE_ID'] = '';
				}
		}
	 	print_r($this->items);
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
	}

	public function getNewPrice($model, $brand_id,$newprice)
	{
		$price = new CPanelPricelist;
		$analysis = new CPanelAnalysis;
		$objCurrency = new CPanelCurrency;

		$price_id = 'ozti';
		$arSettings = array(
			"rate" => 1,
		);

		print_r($brand_id);
		print_r('<br>######<br>');
		print_r($newprice);
		$arDefaultRRC = json_decode(CProSet::getOption("SETTINGS_RRC"), true)[$price_id];

		$arCurrency = $objCurrency->getDetail($arSettings["currency"]);

		$itemPrice = floatval($newprice);
		$productID = 0;
		$markup = 1;
		print_r('<br>######<br>');
		//$itemPrice = (float)round($itemPrice, $arSettings["round"]);
		$profile = $analysis->getListByFilter(array("brand_id" => $brand_id,'price_id'=>$price_id));
		print_r($profile);
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
		if ( $model != false ){
			if ( !empty($this->markups[$model]) ){
				$itemPrice = $itemPrice * $this->markups[$model];
				return $itemPrice;
			}
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

	public function calculatePercentageDifference($number1, $number2) {
	    $average = (floatval($number1) + floatval($number2)) / 2;
	    $difference = floatval($number1) - floatval($number2);
	    $percentDifference = ($difference / $average) * 100;

	    return $percentDifference;
	}

  public function checkFBOStock()
  {

	  $this->db->Query("DELETE FROM wdhs_ozon_fbo_price_new WHERE 1=1", false, $err_mess.__LINE__);
	  $this->db->Query("DELETE FROM wdhs_ozon_fbo_stock_new WHERE 1=1", false, $err_mess.__LINE__);
		$this->db->Query("DELETE FROM wdhs_ozon_fbo_sebes_new WHERE 1=1", false, $err_mess.__LINE__);
		//print_r($this->stockFbo);
		$com = $this->mcom / 100;
		$allCom = $com;
		$newCom = $this->mnewCom / 100;
		$printCom = $com * 100;
		$skdCom = ($com - $newCom) * 100;
		foreach ($this->stockFbo as $key => $value) {
			if (isset($this->items[$key])) {

				if ($value != 0 && $value >= 1) {
					if (isset($this->fromMS[$this->items[$key]['ARTICLE']])) {

						$this->answer[$this->items[$key]['ARTICLE']]['asnw'] = '';

						if (isset($this->FBO_WH[$this->items[$key]['OZON_ARTICLE']])) {
							$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= 'Товар числиться на СКЛАДАХ: ';
							foreach ($this->FBO_WH[$this->items[$key]['OZON_ARTICLE']] as $kkk => $vvv) {
								$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= $vvv['warehouse_name'].' ';

							}
							$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= '<br>Передаем остаток 0<br><br>';
							$in = array(
								"article" => "'".$this->items[$key]['ARTICLE']."'",
							);
							$this->db->Insert("wdhs_ozon_fbo_stock_new", $in, $err_mess.__LINE__);
						}

						$newprice = self::getNewPrice($this->items[$key]['ARTICLE'],$this->items[$key]['BRAND_ID'],$this->fromMS[$this->items[$key]['ARTICLE']]);
						$oldprice = $newprice;
						$newprice = round(($newprice * (1 - $allCom)) / (1 - $newCom));
						$this->answer[$this->items[$key]['ARTICLE']]['count'] = $value;
						$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= "Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
						Остаток на FBO OZON: {$value}<br>
						Себес из МС:{$this->fromMS[$this->items[$key]['ARTICLE']]}<br>
						Себес из БТ:{$this->items[$key]['SEBES']}<br>
						<span style=\"color:green\">Устанавливаем новую цену</span>:{$newprice}<br><br>
						Коммисия OZON: {$printCom}% , скидка на коммисию для ФБО {$skdCom}%<br>ЦЕНА ФБО:<br>{$newprice} ";
						$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= "= round(($oldprice * (1 - $allCom)) / (1 - $newCom))<hr><br><br>";

						$in = array(
							"model" => "'".$this->items[$key]['ARTICLE']."'",
							"sebes" => "'".$this->fromMS[$this->items[$key]['ARTICLE']]."'",
						);

						$this->db->Insert("wdhs_ozon_fbo_sebes_new", $in, $err_mess.__LINE__);

						$in = array(
							"article" => "'".$this->items[$key]['ARTICLE']."'",
							"price" => "'".$newprice."'",
						);
						$this->db->Insert("wdhs_ozon_fbo_price_new", $in, $err_mess.__LINE__);
					}

				}
				//}
			}
		}
  	//print_r($this->answer);
  }

	public function PrintResult()
	{
		// foreach ($this->answer as $key => $value) {
		// 	print_r($value);
		// }
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/fbo/log.txt", print_r(json_encode($this->answer), true).PHP_EOL);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/TI/fbo/log.txt", print_r(json_encode($this->answer), true).PHP_EOL);
	}

}

(new checkFBO())->run();
