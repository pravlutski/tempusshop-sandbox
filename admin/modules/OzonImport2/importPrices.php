<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class OzonImportPrices{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$this->CurDB = new DBPanel();

		$strSql = "SELECT * FROM wdhs_ozon_main_settings_new";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $arSetting[$row['name']] = $row['value'];
		}


		$result = $this->CurDB->query("SELECT * FROM ozon_fbo_price_TI");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->checkFBOPrice[$row['article']] = $row['price'];
		}
		unset($result);
		unset($rows);


		$result = $this->CurDB->query("SELECT * FROM ozon_sales_prices_TI");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$checkSalePriceTmp[$row['model']][] = $row['price'];
		}
		foreach($checkSalePriceTmp as $key => $values) {
			$this->checkSalePrice[$key] = min($values);
		}
		unset($checkSalePriceTmp);

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];

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

		// //metrica
		// unset($dataMetrica);
		// $dataMetrica = [
		// 		'operation' => 'importPrice',
		// ];
		// $this->o_id = $this->metric->OzonBase($dataMetrica);
		// //

		$this->arLog = array();
		$this->arLog['TIME_START'] = date('H:i:s');
		$date = date('Y-m-d');

		$startT = microtime(true);
		$this->getItems();
		print_r(count($this->items));
		print_r('###');
		$this->getSebes();
		$itemsT = microtime(true);
    $this->BuildArray();
		$whT = microtime(true);
		print_r(count($this->items));
		$this->UploadPrices();
		$endT = microtime(true);
		$totalT = $endT - $startT;

		$this->arLog['TIME_POINTS'] = [
			'TOTAL' => round($totalT,2),
			'GET_ITEMS' => round($itemsT - $startT,2),
			'PREPARE_ITEMS' => round($whT - $itemsT,2),
		];
		$this->db->Update("wdhs_ozon_upload_status_new", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='price'", $err_mess.__LINE__);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/prices/shows/$date.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);

		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/prices/shows/$date.txt", print_r('#SPLIT#',true).PHP_EOL,FILE_APPEND);
	}

  public function getItems(){

		$this->db->Update("wdhs_ozon_upload_status_new", array("status" => "'INCOMPLETE'","percent" => "'0'"), "WHERE agent = 'price'", $err_mess.__LINE__);


    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_PRICE_OZTI","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD");
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
			if (isset($this->checkFBOPrice[$el['PROPERTY_CML2_ARTICLE_VALUE']])) {
				$price = $this->checkFBOPrice[$el['PROPERTY_CML2_ARTICLE_VALUE']];
			} else {
				$price = $el["PROPERTY_PRICE_OZTI_VALUE"];
			}
			if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
			}	else if (empty($el['PROPERTY_PRICE_OZTI_VALUE']) or $el['PROPERTY_PRICE_OZTI_VALUE'] == 0) {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
			//} else if (isset($this->checkSalePrice[$el['PROPERTY_CML2_ARTICLE_VALUE']]) && intval($this->checkSalePrice[$el['PROPERTY_CML2_ARTICLE_VALUE']]) >= $price) {
		  } else if (isset($this->checkSalePrice[$el['PROPERTY_CML2_ARTICLE_VALUE']])) {
					$this->arLog['GET_ITEMS']['ERRORS']['SET_BY_SALE_MODULE'][] = $el['ID'];
			} else {
				$arSection = getSectionsElement($el["ID"]);
				// if ($arSection[1]['ID'] == '558') {

				// $price = $el["PROPERTY_PRICE_OZTI_VALUE"];
	    	$this->items[$el["PROPERTY_WBARTICLE_VALUE"]] = [
	    		"ID" => $el["ID"],
					"ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
	    		"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
	    		"PRICE" => $price,
	    	];
			}
    }

		$this->db->Update("wdhs_ozon_upload_status_new", array("percent" => '10'), "WHERE agent='price'", $err_mess.__LINE__);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/prices/tmp/arUpdatePrice.txt", print_r($this->items, true));
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
	}

  public function BuildArray() {
    foreach ($this->items as $key => &$arItem) {
			$minPrc = strval(round($arItem['PRICE'] * 0.9));

			$items[] =  array(
				'auto_action_enabled' => 'DISABLED',
				'currency_code' => 'RUB',
				'min_price' => $minPrc,
				'offer_id' => $arItem['OZON_ARTICLE'],
				'old_price' => '0',
				'price' => strval($arItem['PRICE']),
				"price_strategy_enabled" => "DISABLED",
			);

    }


		$this->arUpdatePrice = array_chunk($items,1000);
		$this->db->Update("wdhs_ozon_upload_status_new", array("percent" => '20'), "WHERE agent='price'", $err_mess.__LINE__);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/prices/tmp/arPrice.txt", print_r($this->arUpdatePrice, true));
  }

	public function UploadPrices(){
		$itter = 80 / count($this->arUpdatePrice);
		$perc = 20;
		foreach ($this->arUpdatePrice as $key => $data) {
			sleep(3);
				$res = self::curlExecUpload($data,$this->api_url,$this->token,$this->client_id);
				while (!isset($res['result'])) {
					sleep(5);
					$res = self::curlExecUpload($data,$this->api_url,$this->token,$this->client_id);
				}
				//print_r($res);
				foreach ($res['result'] as $k => $v) {
						if ($v['updated'] == '1') {
							$this->arLog['UPDATE']['GOOD'][] = $v['offer_id'];
						} else {
							//print_r($v);
							$this->arLog['UPDATE']['BAD'][] = ['id' => $v['offer_id'],'errors' => $v['errors']];
						}
				}

				 // print_r('<br>');
				 // print_r($res);
				 // print_r('<br>');
				$perc = $perc + round($itter,0);
				if (intval($perc) > 100) { $perc = 99;}
				$this->db->Update("wdhs_ozon_upload_status_new", array("percent" => $perc), "WHERE agent='price'", $err_mess.__LINE__);
		}

	}

	public static function curlExecUpload($data,$url,$token,$client_id) {
			if (empty($data)) {
					return 'error';
			}
			$data_string = json_encode(array('prices'=>$data));
			$ch = curl_init($url . '/v1/product/import/prices');
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Api-Key:' . $token,
					'Client-Id:' . $client_id,
					'Content-Type:application/json'
				));
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_HEADER, false);
				$res = curl_exec($ch);
				curl_close($ch);

				$res = json_decode($res, true);
			return $res;
	}

}

//(new OzonImportPrices())->run();
