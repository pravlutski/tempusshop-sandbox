<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class OzonImportStocks{
	public function __construct( $cabinet ){

		if ( !in_array( $cabinet, ['IP', 'TI', 'WT'] ) ) die('WRONG CABINET');

		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$this->controlArray = array();

		$this->cabinet = $cabinet;
		$this->module = 'stock_' . $this->cabinet;

		if ( $this->cabinet == 'TI' ){
			$this->ci_price_filter = 'active_ozti';
		}
		if ( $this->cabinet == 'IP' ){
			$this->ci_price_filter = 'active_os';
		}
		if ( $this->cabinet == 'WT' ){
			$this->ci_price_filter = 'active_os';
		}

		$this->CurDB = new DBPanel();
		$this->db = $DB;
		$this->bot = new TGNotifier;

		$result = $this->CurDB->query("SELECT * FROM ozon_main_settings_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		unset($result);
		unset($rows);

		$result = $this->CurDB->query("SELECT * FROM ozon_fbo_stock_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->checkFBOStock[$row['article']] = "Y";
		}
		unset($result);
		unset($rows);

		$strSql = "SELECT * FROM ci_price_quarantine";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$this->quarantine[$row['ARTICLE']] = "Y";
		}

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];
		$this->bot_threshold = intval( $arSetting['bot_threshold'] );

		$this->excludedCheckFBO = $this->getExcludedFBO();

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("panel.manager");
    }

	public function run(){

		$argv = $_SERVER['argv'];

		if (isset($_GET['cabinet']) || !empty($_GET['cabinet'])) {
			$CABINET = $_GET['cabinet'];
		} else if (isset($argv[1])) {
			$CABINET = $argv[1];
		} else {
			die('WRONG CABINET');
		}

		if (isset($_GET['source']) || !empty($_GET['source'])) {
			$SOURCE = $_GET['source'];
		} else if (isset($argv[2])) {
			$SOURCE = $argv[2];
		} else {
			$SOURCE = 'undefine';
		}

		$this->arLog = array();
		$this->arLog['TIME_START'] = date('H:i:s');
		$date = date('Y-m-d');
		$time = date('H:i:s');

		$timeStart = date('Y.m.d G:i:s');


		$in = array(
			"source	" => "'".$SOURCE."'",
			"script	" => "'stock_TI'",
			"time	" => "'".$timeStart."'",
			"status	" => "'RUN'",
		);

		$fields = implode(",", array_keys($in));
		$values = implode(",",$in);

		$sql = "INSERT INTO ozon_tech_log ($fields) VALUES ($values)";
		$this->CurDB->query($sql);

		//Агент-Инфо
		$arStat = [
			'status' => 'PROCESS',
			'status_text' => 'Запуск скрипта',
			'percent' => 0,
			'time_start' => $timeStart
		];
		$this->updateStatus($this->module, $arStat);

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение товаров из БТ', 'percent' => 10]);

		$startT = microtime(true);
		$this->checkReserv();
		$this->getItems();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение склады OZON', 'percent' => 20]);

		$itemsT = microtime(true);
    $this->GetWarehouseListFromOzon();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Готовим массивы для выгрузки', 'percent' => 25]);

    $this->BuildArray();
		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Выгружаем остатки', 'percent' => 50]);

		$whT = microtime(true);
    $this->UploadStocks();
		try{
			$this->checkStockDifference();
		}
		catch( Throwable $e ){
			file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/importStockDebug.txt', print_r($e->getMessage(), true));
		}
		$endT = microtime(true);
		$totalT = $endT - $startT;

		//Агент-Инфо
		$timeEnd = date('Y.m.d G:i:s');
		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => 100,
			'time_end' => $timeEnd
		];
		$this->updateStatus( $this->module, $arStat );


		$this->arLog['TIME_POINTS'] = [
			'TOTAL' => round($totalT,2),
			'GET_ITEMS' => round($itemsT - $startT,2),
			'PREPARE_ITEMS' => round($whT - $itemsT,2),
		];

		if(!empty($this->controlArray)) {
				foreach ($this->controlArray as $key => $value) {
					$controlLog[$time][] = ['article' => $key,'count'=>$value];
				}
		}
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/stocks/control/$date.txt", print_r(json_encode($controlLog), true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/stocks/control/$date.txt", print_r('#SPLIT#',true).PHP_EOL,FILE_APPEND);

		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/stocks/shows/$date.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/stocks/shows/$date.txt", print_r('#SPLIT#',true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/stocks/tmp/UpdateStockResult.txt", print_r('START', true));
	}




  public function getItems(){

    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_AVAILABILITY_RU","PROPERTY_CML2_ARTICLE","CATALOG_QUANTITY","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD","PROPERTY_OZSB_PRICE");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
      //"ID" => 13263,
			//"SECTION_ID" => 558
			"=PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
      //"ID" => 178901
    );
		//$arFilter["!ID"] = 14124;
    //Array("nPageSize"=>50)
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){
			// $test[] = $el;
			if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
			} else {
				// if ($el['PROPERTY_AVAILABILITY_RU_VALUE'] == 'Нет в наличии') {
				// 	$el["CATALOG_QUANTITY"] = 0;
				// }
					$arSection = getSectionsElement($el["ID"]);
				// if ($arSection[1]['ID'] == '558') {
		    	$this->items[$el['PROPERTY_WBARTICLE_VALUE']] = [
		    		"ID" => $el["ID"],
		    		"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
		    		"QUANTITY" => $el["CATALOG_QUANTITY"],
						"ARTICLE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
						"PRICE" => $el["PROPERTY_OZSB_PRICE_VALUE"],
		    	];
				// }
			}
    }
	  file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/stocks/tmp/arUpdateStock.txt", print_r($this->items, true));
	}

	private function getExcludedFBO():array
	{
		$json = file_get_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/configs/excludeStockImport.json");
		if ( empty($json) ) return [];

		$data = json_decode( $json, true );
		if ( empty($data) ) return [];

		return $data;
	}

	public function checkReserv() {
		$strSql = "SELECT * FROM ci_reserved";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		// $this->arLog['RESERVED']['COUNT'] = 0;
		while ($row = $results->Fetch()){
			if ($row["RESERVED"] >= $row["AVAILABLE_RU"]) {
				$this->reserved[$row["ARTICLE"]] = $row["ARTICLE"];
			}
		}
		//$this->arLog['RESERVED']['ITEMS'] = json_encode($this->arLog['RESERVED']['ITEMS']);
	}

	private function getAvailablePriceData():array
	{
		$reserved = $this->getReserved();

		$strSql = "SELECT * FROM ci_price WHERE active_os = 'Y' ORDER BY price";
		$rows = $this->db->query( $strSql );
		$result = [];
		$priceData = [];

		while( $row = $rows->fetch() ){
			$priceData[ $row['model'] ][] = [
				'price' => $row['price'],
				'count' => $row['count']
			];
		}

		foreach ( $priceData as $model => $data ){
			$isAvailable = $this->getBestPropsition( $data, $reserved[$model] ?? 0 );
			if ( !$isAvailable ) continue;
			$result[ $model ] = $isAvailable;
		}

		return $result;
	}

	private function getReserved():array
	{
		$result = [];

		$strSql = "SELECT * FROM ci_reserved";
		$rows = $this->db->query( $strSql );

		while ( $row = $rows->fetch() ){
			$result[ $row['ARTICLE'] ] = $row['RESERVED'];
		}

		return $result;
	}

	private function getBestPropsition( array $data, int $reserved ):bool
	{
		foreach ( $data as $row ){
			if ( $row['count'] - $reserved <= 0 ){
				$reserved = abs( $row['count'] - $reserved );
				continue;
			}
			return true;
		}

		return false;
	}

  public function GetWarehouseListFromOzon() {
	file_put_contents('/var/www/bitrix_logs/ozon/timing.txt', print_r(['GetWarehouseListFromOzon', date('Y-m-d H:i:s')], true), 8);
    $data = array('limit' => 100);

    $ch = curl_init($this->api_url . '/v2/warehouse/list');
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

    if (!empty($res['warehouses'])) {
      foreach ($res['warehouses'] as $k => $v) {
        if ($v['status'] == 'created' and $v['name'] != 'OZON') {
          $this->arWarehouseOzon[] = $v['warehouse_id'];
        }
      }
    }
  }

  public function BuildArray() {
    //print_r($this->arWarehouseOzon);
		$availableModels = $this->getAvailablePriceData();

    foreach ($this->items as $key => &$arItem) {
	      $arWarehouse = $this->getWarehouseItem($arItem['ID'], 's1');
				// var_dump($arWarehouse);
				// var_dump($this->arWarehouseOzon);
				// die;
				//print_r($arWarehouse);
				$stockControl = 0;
	      foreach ($this->arWarehouseOzon as $key => $wh) {
						if ($arWarehouse[$wh]) {
							// $strSql = "SELECT * FROM ci_price WHERE model = '".$arItem['ARTICLE']."' AND active_ozti = 'Y'";
							$art = $arItem['ARTICLE'];
							// $strSql = "SELECT * FROM ci_price WHERE model = '{$art}' AND {$this->ci_price_filter} = 'Y'";
							// $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
							// $i = 0;
							// while ($row = $results->Fetch()){
							// 	$i++;
							// }
							// if ($i == 0) {
							if ( empty($availableModels[$art]) ){
								$stock_res = 0;

							} else if (isset($this->quarantine[$arItem['ARTICLE']])) {
								$stock_res = 0;

							} else {
								if (intval($arItem['QUANTITY']) > 0) {
								  // $stock_res = intval($arItem['QUANTITY']) + 1;
									$stock_res = rand( 40, 100 );
									// $stock_res = 1002;
								} else {
									$stock_res = 0;
								}
							}
							// if ( isset( $this->checkFBOStock[$arItem['ARTICLE']] ) ) {
							// 	$stock_res = 0;
							// }
							if ( in_array( $arItem['ARTICLE'], $this->excludedCheckFBO ) ) {
								$stock_res = 0;
							}
							// if (isset($this->reserved[$arItem['ARTICLE']])){
							// 	  ///$this->arLog['GET_ITEMS']['ERRORS']['RESERVED'][] = $arItem['ARTICLE'];
							// 		print_r($arItem['ARTICLE']);
							// 		print_r('###');
							// 		$stock_res = 0;
							// }

							if (($wh == '1020002803288000') && (intval($arItem['PRICE']) < 5000)) {
								$stock_res = 0;
							}
							$arStock = array(
			          "offer_id" => $arItem['OZON_ARTICLE'],
			          "stock" => $stock_res,
			          "warehouse_id" => $wh
			        );
      			} else {
							if ( $arItem['OZON_ARTICLE'] == 'W_CASIO_GBA-900-1A' ){
								var_dump($wh);
							}
							$arStock = array(
			          "offer_id" => $arItem['OZON_ARTICLE'],
			          "stock" => 0,
			          "warehouse_id" => $wh
			        );

						}
						$stockControl = $stockControl + intval($arStock['stock']);
						$items[] = $arStock;
						if ( $arStock['offer_id'] == 'W_CASIO_GBA-900-1A' ){
							var_dump($arStock);
						}
						if ( !empty( $this->notifData[$arItem['ARTICLE']] ) ){
							$this->noitfData[ $arItem['ARTICLE'] ] += $arStock['stock'];
						}else{
							$this->notifData[ $arItem['ARTICLE'] ] = $arStock['stock'];
						}
	    }
			if ($stockControl != 0) {
				$this->controlArray[$arItem['ARTICLE']] = $stockControl;
			}
	  }

		$this->arUpdateStock = array_chunk($items,100);

		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/stocks/tmp/arStock.txt", print_r($this->arUpdateStock, true));
		//$this->db->Update("wdhs_ozon_upload_status_new", array("percent" => '21'), "WHERE agent='stock'", $err_mess.__LINE__);
	}

	public function UploadStocks(){
		$itter = 75 / count($this->arUpdateStock);
		$perc = 25;
		foreach ($this->arUpdateStock as $key => $data) {
			usleep(500000);
			// print_r(count($newdata));
			// die();
			// $data = array_filter($data);
			// $data = array_values($data);
				$res = self::curlExecUpload($data,$this->api_url,$this->token,$this->client_id);
				//print_r($res);
				file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/stocks/tmp/UpdateStockResult.txt", print_r($res, true));
				while (!isset($res['result'])) {
					sleep(5);
					$res = self::curlExecUpload($data,$this->api_url,$this->token,$this->client_id);
					file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/stocks/tmp/UpdateStockResult.txt", print_r($res, true),FILE_APPEND);
				}
				foreach ($res['result'] as $k => $v) {
						if ($v['updated'] == '1') {
							$this->arLog['UPDATE']['GOOD'][] = $v['offer_id'];

						} else {
							$this->arLog['UPDATE']['BAD'][] = ['id' => $v['offer_id'],'errors' => $v['errors']];

						}

				}

				$perc = $perc + $itter;
				if (intval($perc) > 100) { $perc = 99;}

		}

	}

	public static function curlExecUpload($data,$url,$token,$client_id) {
		file_put_contents('/var/www/bitrix_logs/ozon/timing.txt', print_r(['curlExecUpload', date('Y-m-d H:i:s')], true), 8);
	    if (empty($data)) {
	        return 'error';
	    }
	    $data_string = json_encode(array('stocks' => $data), JSON_UNESCAPED_UNICODE);
	    $ch = curl_init($url . '/v2/products/stocks');
	    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	        'Api-Key:' . $token,
	        'Client-Id:' . $client_id,
	        'Content-Type: application/json'
	    ));
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($ch, CURLOPT_HEADER, false);
	    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	    $res = curl_exec($ch);
	    curl_close($ch);
	    if(curl_errno($ch)) {
	        echo 'Curl error: ' . curl_error($ch);
	    }
	    $res = json_decode($res, true);
	    return $res;
	}

  public function getWarehouseItem($ID = 0, $SITE_ID = "s1") {
    global $DB;

    $arWarehouse = [];

    $arSelect = Array("ID", "PROPERTY_TYPEOFSKLAD");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
      "ID" => $ID
    );
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){
      $types = $el['PROPERTY_TYPEOFSKLAD_VALUE'];
    }

    if(CModule::IncludeModule('panel.manager')){
			if ( $this->cabinet == 'TI' ){
				$sklads = TYPE_SKLAD_CONST_TI;
			}
			if ( $this->cabinet == 'IP' ){
				$sklads = TYPE_SKLAD_CONST;
			}
			if ( $this->cabinet == 'WT' ){
				$sklads = TYPE_SKLAD_CONST_WT;
			}
    }
    if ($sklads) {
      foreach ($types as $value) {
        if ($sklads[$value]) {
          $arWarehouse[$sklads[$value]] = true;
        }
      }
    }
    return $arWarehouse;
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

	private function checkStockDifference():void
	{
		$marketplace = 'ozon';
		$cab = $this->cabinet;
		$module = "ozon_importStock_{$cab}";
		$nameTemplateSys = "importStock_{$cab}";
		$nameTemplateBot = "Остатки_OZON_{$cab}";
		$messageHeader = "<b>Модуль выгрузки остатков OZON {$cab}:</b>\n\n";

		$filenamePrev = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/{$marketplace}/export/{$nameTemplateSys}_prev.xlsx";
		$filenameLast = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/{$marketplace}/export/{$nameTemplateSys}_last.xlsx";

		$row = $this->CurDB->select(['*'], 'modules_control')->where('module', $module)->make();
		$oldValue = intval( $row[0]['old_value'] );
		$newValue = intval( $row[0]['new_value'] );
		$control = 0;
		$dataForBot = [];

		// foreach ( $this->arUpdateStock as $value ){
		// 	foreach ($value as $elem ){
		// 		if ( $elem['stock'] > 0 ) $control++;
		// 		$dataForBot[] = [ end( explode('_', $elem['offer_id']) ) , $elem['stock'] ];
		// 	}
		// }

		foreach ( $this->notifData as $model => $stock ){
			if ( $stock > 0 ) $control++;
			$dataForBot[] = [ end( explode( '_', $model ) ), $stock ];
		}

		if ( $newValue != 0 ){
			$diff = ($newValue - $control) / $newValue * 100;
		}else{
			$diff = 0;
		}

		$diffInfo = round($diff, 2);

		if ( $diff > $this->bot_threshold ){
			$this->bot->sendMessage("{$messageHeader}<b>⚠ Выгружено на <u>{$diffInfo}%</u> меньше положительных остатков!</b>\n\n✅ <b>Прошлая итерация: <u>{$newValue}</u></b>.\n❌ <b>Текущая итерация: <u>{$control}</u></b>\n\n<i>Убедитесь в отсутствии ошибок!</i>\n<i>Ниже прикреплены файлы с артикулами выгрузок</i>");
			if ( file_exists($filenamePrev) ){
				$this->bot->sendFile($filenamePrev, "{$nameTemplateBot}_prev.xlsx");
			}
		}

		if ( file_exists($filenameLast) ) rename($filenameLast, $filenamePrev);

		$newFile = $this->buildXlsx( $marketplace, "{$nameTemplateSys}_last", ['Модель', 'Остаток'], $dataForBot );
		if ( $diff > $this->bot_threshold ){
			if ( $newFile ){
				$this->bot->sendFile($newFile, "{$nameTemplateBot}_last.xlsx");
			}else{
				$this->bot->sendMessage("Файл {$nameTemplateBot}_last.xlsx не может быть отправлен ввиду непредвиденной ошибки: массив пустой");
			}
		}

		if ( $newValue == 0 && $control == 0 ){
			$this->bot->sendMessage("<b>{$messageHeader}❌Нет положительных остатков в двух или более итерациях подряд</b>\n\n⚠<i>Необходимо срочно исправить ошибку!</i>");
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


}


( new OzonImportStocks($argv[1]) )->run();
