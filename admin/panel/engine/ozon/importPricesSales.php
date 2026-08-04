<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class OzonImportPricesSales{
	public function __construct($cabinet){
		if (empty($cabinet) || $cabinet == '') {
			die('need cabinet_id');
		}
		$this->cabinet = $cabinet;

		global $DB;
		$this->loadModules();
		$this->CurDB = new DBPanel();
		$this->db = $DB;

		$this->module = 'importSales_' . $this->cabinet;

		$this->metric = new Metric();

		$result = $this->CurDB->query("SELECT * FROM ozon_main_settings_{$cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		unset($result);
		unset($rows);

		if ($this->cabinet == 'TI') {
			$prefix_old = '_new';
		} else {
			$prefix_old = '';
		}

		// $strSql = "SELECT * FROM wdhs_ozon_fbo_price".$prefix_old."";
		// $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		// while ($row = $results->Fetch()){
		//   $this->checkFBOPrice[$row['article']] = $row['price'];
		// }

		$result = $this->CurDB->query("SELECT * FROM ozon_fbo_price_{$cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->checkFBOPrice[$row['article']] = $row['price'];
		}
		unset($result);
		unset($rows);

		$result = $this->CurDB->query("SELECT * FROM ozon_sales_prices_{$cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$checkSalePriceTmp[$row['model']][] = $row['price'];
		}
		foreach($checkSalePriceTmp as $key => $values) {
			$this->checkSalePrice[$key] = min($values);
		}
		unset($checkSalePriceTmp);


		$result = $this->CurDB->query("SELECT * FROM ozon_sales_pi_{$cabinet} WHERE pi_sets = 'main'");
	  $rows = $this->CurDB->fetchAll($result);
	  foreach ($rows as $row) {
			$this->tops = json_decode($row['tops']);
	  }

	  unset($result);
	  unset($rows);

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];
		$this->excludeModels = $this->getExcludedModels();
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

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Обновляем цены для акционных товаров', 'percent' => 90]);

		$startT = microtime(true);
		$this->getItems();
		$itemsT = microtime(true);
    $this->BuildArray();
		$whT = microtime(true);
		$this->UploadPrices();
		$endT = microtime(true);
		$totalT = $endT - $startT;

		$timeEnd = date('Y.m.d G:i:s');
    //Агент-Инфо
		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => 100,
			'time_end' => $timeEnd
		];
		$this->updateStatus($this->module, $arStat);

		$this->arLog['TIME_POINTS'] = [
			'TOTAL' => round($totalT,2),
			'GET_ITEMS' => round($itemsT - $startT,2),
			'PREPARE_ITEMS' => round($whT - $itemsT,2),
		];
		//$this->db->Update("wdhs_ozon_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='price'", $err_mess.__LINE__);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/shows/$date.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/shows/$date.txt", print_r('#SPLIT#',true).PHP_EOL,FILE_APPEND);
	}

  public function getItems(){
		if ($this->cabinet == 'TI') {
			$constPrice = 'PRICE_OZTI';
			$constID = 'OZON_ID_TI';
			$constPriceType = 'ozti';
		} else if ($this->cabinet == 'IP') {
			$constPrice = 'OZSB_PRICE';
			$constID = 'OZON_ID';
			$constPriceType = 'os';
		} else {
			die('WRONG CONST');
		}
		//$this->db->Update("wdhs_ozon_upload_status", array("status" => "'INCOMPLETE'","percent" => "'0'"), "WHERE agent = 'price'", $err_mess.__LINE__);

    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_".$constPrice."","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
      //"ID" => 13263,
			//"SECTION_ID" => 558
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да'
      //"ID" => 178901
    );
		//$arFilter["!ID"] = 14124;
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		////file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/tmp/topInSale.txt", print_r('MODEL', true).PHP_EOL);
		$index = 0;
    while ($el = $result->GetNext()){
			if ( in_array($el['PROPERTY_CML2_ARTICLE_VALUE'], $this->excludeModels) ) continue;
			if (isset($this->checkFBOPrice[$el['PROPERTY_CML2_ARTICLE_VALUE']])) {
				$price = $this->checkFBOPrice[$el['PROPERTY_CML2_ARTICLE_VALUE']];
			} else {
				$price = $el["PROPERTY_".$constPrice."_VALUE"];
			}

			if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
			} else {
					if (isset($this->checkSalePrice[$el['PROPERTY_CML2_ARTICLE_VALUE']])) {
						//if (intval($this->checkSalePrice[$el['PROPERTY_CML2_ARTICLE_VALUE']]) >= $price) {
						// $price = $price * 1.25;
			    	$this->items[$el["PROPERTY_WBARTICLE_VALUE"]] = [
			    		"ID" => $el["ID"],
							"ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
			    		"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
			    		"PRICE" => $price,
			    	];
					//}
				}
			}
		}
		// print_r('count');
		// print_r($this->items);
		//$this->db->Update("wdhs_ozon_upload_status", array("percent" => '10'), "WHERE agent='price'", $err_mess.__LINE__);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/tmp/arUpdatePrice.txt", print_r($this->items, true));
	}

	private function getExcludedModels():array
	{
		$result = [];
		$path = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/configs/excludedPrices.json';

		if ( !file_exists($path) ) return $result;

		$json = file_get_contents($path);
		$result = json_decode($json, 1);

		return $result;
	}

  public function BuildArray() {
    foreach ($this->items as $key => &$arItem) {
			$items[] =  array(
				'auto_action_enabled' => 'DISABLED',
				'currency_code' => 'RUB',
				'min_price' => strval($arItem['PRICE']), // до 02.06.25 был 0
				'offer_id' => $arItem['OZON_ARTICLE'],
				'old_price' => '0',
				'price' => strval($arItem['PRICE']),
				"price_strategy_enabled" => "DISABLED",
			);

			// //metrica
			// unset($dataMetrica);
			// $someText = 'Подготовка к обновлению. Цена <b style="color:#0fc609;">'.$arItem['PRICE'].'</b>';
			// $dataMetrica = [
			// 		'model' => $arItem['ARTICLE'],
			// 		'name' => 'Обновление цен',
			// 		'o_id' => $this->o_id,
			// 		'code' => 'importPrice',
			// 		'result' => $someText,
			// ];
			//
			// $this->metric->OzonPrice($dataMetrica);
			// //
    }


		$this->arUpdatePrice = array_chunk($items,1000);
		//$this->db->Update("wdhs_ozon_upload_status", array("percent" => '20'), "WHERE agent='price'", $err_mess.__LINE__);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/tmp/arPrice.txt", print_r($this->arUpdatePrice, true));
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
				foreach ($res['result'] as $k => $v) {
						if ($v['updated'] == '1') {
							$this->arLog['UPDATE']['GOOD'][] = $v['offer_id'];
						} else {
							$this->arLog['UPDATE']['BAD'][] = ['id' => $v['offer_id'],'errors' => $v['errors']];
						}
				}

				 // print_r('<br>');
				 // print_r($res);
				 // print_r('<br>');
				$perc = $perc + round($itter,0);
				if (intval($perc) > 100) { $perc = 99;}
				//$this->db->Update("wdhs_ozon_upload_status", array("percent" => $perc), "WHERE agent='price'", $err_mess.__LINE__);
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

	public function updateStatus( string $code, array $arStat ):void
	{
		if ( empty($arStat) ) return;
		$strSql = "UPDATE ozon_agents SET ";
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

}

//(new OzonImportPricesSales())->run();
