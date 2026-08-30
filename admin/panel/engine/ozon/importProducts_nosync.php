<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workerArgs = array();
if (!empty($_SERVER["argv"])) {
	foreach (array_slice($_SERVER["argv"], 1) as $arg) {
		if ($arg === "" || $arg === "-f" || (isset($arg[0]) && $arg[0] === "-")) {
			continue;
		}
		$workerArgs[] = $arg;
	}
}
$workerKey = implode(" ", $workerArgs);
$workerMap = array(
	"IP 1" => "ozon_importProducts_nosync_php_IP_1",
	"IP" => "engine_ozon_importProducts_nosync_php_IP",
);
$workerId = isset($workerMap[$workerKey]) ? $workerMap[$workerKey] : "ozon_importProducts_nosync_php_IP_1";
$workers = new WorkersChecker($workerId);
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(0);

use Bitrix\Main\Application,
		Bitrix\Main\Loader,
		Bitrix\Iblock\PropertyEnumerationTable;

class OzonImportProducts{
	public function __construct($cabinet,$dislink = 0){

		if ( !in_array( $cabinet, ['TI', 'IP', 'WT']) ) die("WRONG CABINET\n");
		// die($cabinet);
		global $DB;
		$this->loadModules();
		$this->CurDB = new DBPanel();
		$this->db = $DB;
		$this->dislink = $dislink;
		$this->cabinet = $cabinet;

		switch ( $this->cabinet ){
			case 'TI':
				$this->price_prop = "PROPERTY_PRICE_OZTI";
				$this->ci_price_filter = "active_ozti";
				$this->excludeModels = ["LTP-V300D-7A2", "LTP-V007L-7E2"];
				break;
			case 'IP':
				$this->price_prop = "PROPERTY_OZSB_PRICE";
				$this->ci_price_filter = "active_os";
				$this->excludeModels = [];
				break;
			case 'WT':
				$this->price_prop = "PROPERTY_OZSB_PRICE";
				$this->ci_price_filter = "active_os";
				$this->excludeModels = [];
				break;
		}

		// $result = $this->CurDB->query("SELECT * FROM ozon_main_settings_{$this->cabinet}");
		// $rows = $this->CurDB->fetchAll($result);
		// foreach ($rows as $row) {
		// 	$arSetting[$row['name']] = $row['value'];
		// }
		// unset($result);
		// unset($rows);

		$rows = $this->CurDB->select(['*'], "ozon_main_settings_{$this->cabinet}")->make();
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		unset($rows);

		$this->module = 'importProducts_'.$this->cabinet;
    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];

		$result = $this->CurDB->query("SELECT * FROM ozon_model_collection");
		$rows = $this->CurDB->fetchAll($result);
		$coll = [];
		foreach ($rows as $row) {
			$coll[ $row['model'] ] = $row['code'];
		}
		$this->collection = $coll;

		unset($result);
		unset($rows);

		$rsProperties = CIBlockProperty::GetList(
		    array("name" => "asc"),
		    array("IBLOCK_ID" => CProSet::IB_CATALOG)
		);
		$arProperty = array();

		while ($property = $rsProperties->Fetch()) {
		  $this->arProperty[$property["ID"]] = [
		      "NAME" => $property["NAME"],
		      "ID" => $property["ID"],
		      "TYPE" => $property["PROPERTY_TYPE"]
		    ];
		}

		$strSql = "SELECT * FROM wdhs_ozon_attribute_category_new";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $this->arResult['ACTIVE_CATEGORY'][] = $row['name'];
			$this->catID[] = $row['category_id'];
		}

		$strSql = "SELECT * FROM wdhs_ozon_attribute_new";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $this->arResult['ACTIVE_ATT'][$row['attribute_id']] = $row;
		}

		$strSql = "SELECT * FROM wdhs_ozon_attribute_bitrix_new";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if ($row['property_id'] != 'NULL' and $row['property_id'] != 'default-value') {
					 $this->nBt[] = 'PROPERTY_'.$row['property_id'];
			}
			if ($row['property_id'] != 'NULL') {
				if ($row['property_id'] != 'default-value') {
					 $this->notEmpty[$row['attribute_id']] = $row['property_id'];
				} else {
					$this->notEmpty[$row['attribute_id']]['default-value'] = $row['default_value'];
				}
			}
		}

		$strSql = "SELECT * FROM wdhs_ozon_attribute_matches_new WHERE attribute_value_id != ''";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $this->actMatches[ $row['attribute_id'] ][ $row['property_id'] ][ $row['property_value_id'] ] = ['attribute_value_id'=>$row['attribute_value_id'],'attribute_name'=> $row['attribute_name']];
		}

		$result = $this->CurDB->query("SELECT * FROM ozon_sales_prices_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$checkSalePriceTmp[$row['model']][] = $row['price'];
		}
		foreach($checkSalePriceTmp as $key => $values) {
			$this->checkSalePrice[$key] = min($values);
		}
		$this->substSizes = $this->getSubstSizesData();
		$this->fboPriceData = $this->getFboPriceData();
		$this->rate = $this->getExchangeRate();
		$this->dynamicPrices = $this->getDynamicPrices();
		$this->createdCards = $this->getSKUDictionary();

		unset($result);
		unset($rows);
		unset($checkSalePriceTmp);

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("panel_manager");
    }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}
		$arStat = [
			'status' => 'IN_PROCESS',
			'status_text' => 'Начало',
			'percent' => 0,
			'time_start' => date('Y.m.d G:i:s')
		];
		$this->updateStatus($this->module, $arStat);
		$this->arLog = array();
		$this->arLog['TIME_START'] = date('H:i:s');
		$date = date('Y-m-d');

		$startT = microtime(true);
		$this->updateStatus($this->module, ['status_text' => 'Получаем товары', 'percent' => 20]);
		$this->getItems();
		$itemsT = microtime(true);
		$this->updateStatus($this->module, ['status_text' => 'Формируем массив', 'percent' => 20]);
    $this->BuildArray();
		$whT = microtime(true);
		$this->UploadProducts();
		$endT = microtime(true);
		$totalT = $endT - $startT;

		$this->arLog['TIME_POINTS'] = [
			'TOTAL' => round($totalT,2),
			'GET_ITEMS' => round($itemsT - $startT,2),
			'PREPARE_ITEMS' => round($whT - $itemsT,2),
		];
		//$this->db->Update("wdhs_ozon_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='products'", $err_mess.__LINE__);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/shows/$date.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/shows/$date.txt", print_r('#SPLIT#',true).PHP_EOL,FILE_APPEND);
		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => 100,
			'time_end' => date('Y.m.d G:i:s')
		];
		$this->updateStatus($this->module, $arStat);
		exec("php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/classes/SelectManager.php > /dev/null 2>&1 &");
		exec("php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/getProductsSKU.php IP > /dev/null 2>&1 &");
	}

		public function getItems(){
			// $checkArray = array("LQ-139EMV-7A","LQ-142E-7A","LTP-1183A-1A","MW-59-7B","LTP-V002L-1B","BI5000-87L","PRW-61LD-5E","VR56J010Y","LA-20WH-1B","LTP-V006L-4B","GA-2100-7A","MTP-VD300L-2E","VQ87J002Y","GA-140-1A1","VP46J035Y","LLA3J301Y","Q880J101Y","LTP-V005GL-7A","GMA-S120GS-8A","F279J204Y","MTP-1375L-7A","B640WBG-1B","V32AJ001Y","LTP-V006L-1B","MTP-B205D-7E","LTP-V002D-1A","GBD-200-9E","DW-5600HR-1E","EFV-C110D-2B","GST-B400BB-1A","LTP-1215A-2A","LTP-V002L-1B3","AEQ-100W-1A","RA-AA0818L","GA-B2100-1A1","A-168WEGB-1B","LA-680WGA-1E","LA-680WGA-9B","VP46J030Y","LTP-1130N-7B","F-91WM-2A","MQ-76-7A1","MDV-107D-3A","MW-59-7E","EFV-620D-1A4");
			////$this->db->Update("wdhs_ozon_upload_status", array("status" => "'INCOMPLETE'","percent" => "'0'"), "WHERE agent = 'products'", $err_mess.__LINE__);
			$strSql = "SELECT model FROM ci_price WHERE {$this->ci_price_filter} = 'Y'";

			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				if ( in_array( $row['model'], $this->excludeModels ) ) continue;
				$models[]  = $row['model'];
				// if (!in_array($row['model'],$checkArray)) {
				// }

			}
			//$models = implode(",", $models);
			//$models = array("CH2564");
			// $models = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/yuh.txt');
			// $models = json_decode($models, true);
	    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_MORE_PHOTO","PROPERTY_INFO_TOP","PROPERTY_AEN","PROPERTY_WBARTICLE","PROPERTY_PRICE_OZTI","PROPERTY_OZSB_PRICE","PROPERTY_NAME_MARKETPLACE",
			"PROPERTY_INFOOZON_IMAGE","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_FACE", "PROPERTY_COLLECTION", "PROPERTY_INFO_WB_PRIORITY", "PROPERTY_INFO_WB_IMAGE", "DETAIL_PICTURE");
			// foreach ($this->nBt as $key => $value) {
			// 	$arSelect[] = $value;
			// }
		//$models = array("RN-WG0415A");
			// $models = array("AE-1500WH-8B", "MTP-V005D-2B4", "MTP-V001D-1B", "A-158WA-1", "MTP-VD01D-1B", "A-159WA-N1", "A-168WG-9E", "MTP-V005L-1B5", "MTP-V001L-1B", "LTP-V007D-4E", "AE-1200WHD-1A", "MTP-1375D-1A", "W-218H-1B", "MTP-1183A-1A", "MTP-1384D-1A", "MTP-VD01D-1B", "A-168WA-1Q");
			// $json = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/3.txt');
			// $xml_ids = json_decode($json);

			// die;
	    $arFilter = Array(
	      "IBLOCK_ID" => CProSet::IB_CATALOG,
				"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
				// "PROPERTY_CML2_ARTICLE" => $models,
				// "XML_ID" => [181472]
			  // "ID" => [211844]
	    );

			if ( $modelsFilter = $this->getUploadCardsList() ){
				$arFilter["PROPERTY_CML2_ARTICLE"] = $modelsFilter;
			}
			$skuFilter = [];
			if ( $this->dislink == 1 ){
				// $arFilter['PROPERTY_CML2_ARTICLE'] = $this->getSKUDictionary();
				$skuFilter = $this->getSKUDictionary();
				// if ( empty($arFilter['PROPERTY_CML2_ARTICLE']) ) die('FILTER CANNOT BE EMPTY');
				unset( $arFilter["PROPERTY_OZON_ACTIVE_VALUE"] );
			}

			//$arFilter["PROPERTY_BRAND"] = '43508';
			$timesStart = time();
	    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

			// var_dump( $result->SelectedRowsCount() );
			// die;
			var_dump( time() - $timesStart );

	    while ($el = $result->GetNextElement()){


				$res = $el->getFields();
				if ( $this->dislink == 1 && empty($skuFilter[ $res['PROPERTY_CML2_ARTICLE_VALUE'] ]) ) continue;
				foreach ($el->getProperties() as $key => $value) {
					$props[$value['ID']] = $value;
				}
				$res['PROPERTIES'] = $props;
				// var_dump($res['PROPERTIES']);
				// file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/props_items.txt', print_r($res['PROPERTIES'], true));
				// die;

				$tmpArrMORE = array();
				$tmpArrTOP = array();
				if (!empty($res['PROPERTY_MORE_PHOTO_VALUE'])) {
					foreach ($res['PROPERTY_MORE_PHOTO_VALUE'] as $key => $value) {
						$morephoto[] = 'https://tempusshop.ru'.CFile::GetPath($value);
					}
				}
				//print_r($tmpArrMORE);
				// if (!empty($res['PROPERTY_INFO_TOP_VALUE'])) {
				// 	foreach ($res['PROPERTY_INFO_TOP_VALUE'] as $key => $value) {
				// 		$tmpArrTOP[] = 'https://tempusshop.ru'.CFile::GetPath($value);
				// 	}
				// }
				//print_r($tmpArrTOP);
				// $morephoto = [];

				// if (!empty($tmpArrMORE)) {
				//     $morephoto[] = reset($tmpArrMORE);
				//
				//     if (!empty($tmpArrTOP)) {
				//         $morephoto = array_merge($morephoto, $tmpArrTOP);
				//     }
				//
				//     if (count($tmpArrMORE) > 1) {
				//         $remainingMORE = array_slice($tmpArrMORE, 1);
				//         $morephoto = array_merge($morephoto, $remainingMORE);
				//     }
				// }

				if (!empty($res['PROPERTY_INFOOZON_IMAGE_VALUE'])) {
					$mainphoto = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_INFOOZON_IMAGE_VALUE']);
				} else {
					$mainphoto = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_IMAGE_MARKETPLACE_VALUE']);
				}
				// if (!empty($res['PROPERTY_INFO_WB_IMAGE_VALUE'])) {
				// 	$mainphoto = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_INFO_WB_IMAGE_VALUE']);
				// } else {
				// 	$mainphoto = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_IMAGE_MARKETPLACE_VALUE']);
				// }
				// if ( !empty($res['PROPERTY_INFO_WB_PRIORITY_VALUE']) ){
				// 	$mainphoto = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_INFO_WB_PRIORITY_VALUE']);
				// }
				$res['MAINPHOTO'] = $mainphoto;
				if (!empty($res['PROPERTY_IMAGE_MARKETPLACE_VALUE'])) {
					$colorimg = 'https://tempusshop.ru'.CFile::GetPath($res['DETAIL_PICTURE']);
				}
				// $res['COLORIMG'] = $colorimg;
				$res['COLORIMG'] = 'https://tempusshop.ru'.CFile::GetPath($res['DETAIL_PICTURE']);
				if (is_array($morephoto)) {
					$morephoto = array_slice($morephoto,0,12);
				}
				$res['MOREPHOTO'] = $morephoto;
				if ($res['PROPERTY_FACE_ENUM_ID'] == 1872) {
					$res['MOREPHOTO'][] = "https://tempusshop.ru/upload/analog.png";
				} else {
					$res['MOREPHOTO'][] = "https://tempusshop.ru/upload/digital.png";
				}
				if (!empty($res['PROPERTY_CML2_ARTICLE_VALUE'])) {
					$res['article'] = $res['PROPERTY_CML2_ARTICLE_VALUE'];
				}
				$res['DETAIL_PICTURE'] = 'https://tempusshop.ru'.CFile::GetPath($res['DETAIL_PICTURE']);
				$this->items[$res['ID']] = $res;
				unset($colorimg);
				unset($morephoto);
				unset($mainphoto);
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/ids.txt", print_r($res['ID'], true).PHP_EOL,FILE_APPEND);
	    }
			// print_r(count($this->items));
			// print_r('+++');
			//$this->db->Update("wdhs_ozon_upload_status", array("percent" => '10'), "WHERE agent='products'", $err_mess.__LINE__);
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/tmp/arUpdateProducts.txt", print_r($this->items, true));
			print_r('@@@');
		}

		private function mergeAdvertPic( string $advertPic, array $data ):array
		{
			// return $data;
			if ( empty($advertPic) ) return $data;
			$result = [];
			$insertKey = 0;

			foreach ( $data as $k => $pic ){
				$result[] = $pic;
				if ( $k == $insertKey ){
					$result[] = $advertPic;
				}
			}

			return $result;
		}

	  public function BuildArray() {
			$jsonArr = [];
	    foreach ($this->items as $key => $arItem) {
				$jsonArr[] = $arItem['article'];
				$arItem['MOREPHOTO'] = $this->mergeAdvertPic(
					advertPic: ($this->cabinet == 'IP') ? 'https://tempusshop.ru/upload/tempus_brand_new.png' : '',
					data: $arItem['MOREPHOTO']
				);
				//print_r($arItem);
				foreach ($this->notEmpty as $attId => $propId) {
					if (is_array($propId)) {
						$att[] = array(
							'id' => $attId,
							'values' => array(array(
								'value' => $propId['default-value'])
							)
					 );
					}else{
						if ($this->arResult['ACTIVE_ATT'][$attId]['dictionary_id'] != '0') {

							if ($arItem['PROPERTIES'][$propId]['PROPERTY_TYPE'] == 'E') {
								if (!empty($arItem['PROPERTIES'][$propId]['VALUE'])) {
									$att[] = array(
										'id' => $attId,
										'values' => array(array(
											'dictionary_value_id'=> $this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE']]['attribute_value_id'],
											'value' => $this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE']]['attribute_name'])
										)
									);
								}
							} else if ($arItem['PROPERTIES'][$propId]['PROPERTY_TYPE'] == 'S') {

								if (!empty($arItem['PROPERTIES'][$propId]['VALUE'])) {
									$att[] = array(
										'id' => $attId,
										'values' => array(array(
											'dictionary_value_id'=> $this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE']]['attribute_value_id'],
											'value' => $this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE']]['attribute_name'])
										)
									);
								}
							} else {

								if (is_array($arItem['PROPERTIES'][$propId]['VALUE'])) {
									$counter = 0;
									foreach ($arItem['PROPERTIES'][$propId]['VALUE'] as $k => $v) {
										if ( $counter == 12 ) break;
										if (!empty($this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE_ENUM_ID'][$k]]['attribute_value_id'])) {
											$arr_comp[] = ['dictionary_value_id'=> $this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE_ENUM_ID'][$k]]['attribute_value_id'], 'value' => $this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE_ENUM_ID'][$k]]['attribute_name']
										];
										$counter++;
										}
									}
									$att[] = array(
										'id' => $attId,
										'values' => $arr_comp
									);
									unset($arr_comp);
								} else {
									if (!empty($arItem['PROPERTIES'][$propId]['VALUE_ENUM_ID'])) {
										$att[] = array(
											'id' => $attId,
											'values' => array(array(
												'dictionary_value_id'=> $this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE_ENUM_ID']]['attribute_value_id'],
											 	'value' => $this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE_ENUM_ID']]['attribute_name'])
								 			)
									 	);
									}
							 	}
							}
					 } else {
						 if ($arItem['PROPERTIES'][$propId]['PROPERTY_TYPE'] == 'F') {

							 if (!empty($arItem['PROPERTIES'][$propId]['VALUE'])) {
								 	if (is_array($arItem['PROPERTIES'][$propId]['VALUE'])) {
										foreach ($arItem['PROPERTIES'][$propId]['VALUE'] as $k => $v) {
											if ( $v != '0' ){
												$arrImg[] = ['value'=>'https://tempusshop.ru'.CFile::GetPath($v)];
											}else{
												$arrImg = [];
											}
										}
										if ( !empty($arrImg) ){
											$att[] = array(
												'id' => $attId,
												'values' => $arrImg
											);
										}
										unset($arrImg);
							 		} else {
									 $att[] = array(
			  						 'id' => $attId,
			  						 'values' => array(
			  							 array('value' => 'https://tempusshop.ru'.CFile::GetPath($arItem['PROPERTIES'][$propId]['VALUE']))
										 )
			  						);
									}
								}
						} else {
							if ($propId != '2839') {
								if (!empty($arItem['PROPERTIES'][$propId]['VALUE'])) {
									$att[] = array(
			 						 'id' => $attId,
			 						 'values' => array(array('value' => $arItem['PROPERTIES'][$propId]['VALUE'])
			 						 )
			 						);
								}
							} else {
								if (!empty($arItem['PROPERTIES'][$propId]['VALUE'])) {
									// var_dump($arItem['PROPERTIES'][$propId]['VALUE']['TEXT']);
									$json = html_entity_decode($arItem['PROPERTIES'][$propId]['VALUE']['TEXT']);
									$arRich = json_decode($json, true);
									$rich = json_encode($arRich, JSON_UNESCAPED_UNICODE);
									$att[] = array(
			 						 'id' => $attId,
			 						 'values' => array(array('value' => $rich)
			 						 )
			 						);

								}
							}
						}

					 }
					}
				}



				$i = 0;
				$oneToDelete = '';
				$oneToDelete2 = '';
				foreach($att as $keyDetail => $elem){
					// print_r('<br>');
					// print_r($elem);

					$i = $i + 1;

					if ($elem['id'] == 4191 ){
						$att[$keyDetail]['values'][0]['value'] = str_replace( '%BR%%BR%', '', $elem['values'][0]['value']['TEXT'] );
					}
					if ($elem['id'] == 8229) {
						$att[$keyDetail]['values'][0] = ['dictionary_value_id' => 91758];
					}
					if ($elem['id'] == 22336) {
						$string = str_replace(', ,',',',$att[$keyDetail]['values'][0]['value']);
						if (strlen($string) > 255) {
								$string = substr($string, 0, 255); // Обрезаем до 255 символов
								$lastCommaPosition = strrpos($string, ',');
									if ($lastCommaPosition !== false) {
											$string = substr($string, 0, $lastCommaPosition);
									}
						}
						// $att[$keyDetail]['values'][0]['value'] = $string;
						// unset($att[$keyDetail]);
					}
					// if ($elem['id'] == 9048) {
					// 	$att[$keyDetail]['values'][0] = ['value' => $arItem['article']];
					// }

					if ($elem['id'] == 10097) {

						if ( isset($this->collection[$arItem['article']]) ){
							$att[$keyDetail]['values'][0] = ['value' => $arItem['article']];
						}
					}
					if ($elem['id'] == '9048') {
						if ($this->dislink == 1) {
							$att[$keyDetail]['values'][0] = [ 'value' => $arItem['article'] ];
						} else if ( isset($this->collection[$arItem['article']]) ){
							$att[$keyDetail]['values'][0] = [ 'value' => $this->collection[$arItem['article']] ];
						}
						// $att[$keyDetail]['values'][0] = ['value' => $arItem['article']];
					}

				}
				foreach($att as $kd => $es){
					if ($es['id'] == 8789 ){
						$att[$kd]['values'][0]['value'] .= ' '.$arItem['article'];
						$oneToDelete = $kd;
					}
					if($es['id'] == 8790){
						unset($oneToDelete);
					}
					if ($es['id'] == 4195 ){
						$oneToDelete2 = $kd;
						unset($att[$kd]);
					}
				}
				if(!empty($oneToDelete) ){
					unset($att[$oneToDelete]);
				}
				if(!empty($oneToDelete2) ){
					unset($att[$oneToDelete2]);
				}
				if (isset($this->checkSalePrice[$arItem['article']])) {
					// $price = intval($arItem["{$this->price_prop}_VALUE"]) * 1.25;
					$price = intval($arItem["{$this->price_prop}_VALUE"]);
				} else {
					$price = intval($arItem["{$this->price_prop}_VALUE"]);
				}

				if ( isset( $this->fboPriceData[$arItem['article']] ) ){
					$price = $this->fboPriceData[$arItem['article']];
				}

				if ($price == '' || empty($price)) {
					$prices[$arItem['article']] = $price;
					$price = 500000;
				}
				// $substImage = $this->getSubstImage( $arItem['article'] );

				$width = $this->getSubstSize($arItem['article'], 'width');
				$height = $this->getSubstSize($arItem['article'], 'height');
				$depth = $this->getSubstSize($arItem['article'], 'depth');
				$weight = $this->getSubstSize($arItem['article'], 'weight');

				$price = $price / $this->rate;

				$item = [
					"barcode" => $arItem['PROPERTY_AEN_VALUE'],
					"description_category_id" => $this->catID[0],
					"offer_id" => $arItem['PROPERTY_WBARTICLE_VALUE'],
					"price" => strval($this->dynamicPrices[$arItem['article']] ?? $price),
					// "vat" => "0.05",
					"name" => $arItem['PROPERTY_NAME_MARKETPLACE_VALUE'],
					"weight" => $weight ? $weight : '200',
					"height" => $height ? $height : '120',
					'width' => $width ? $width: '110',
					'dimension_unit' => 'mm',
					'depth' => $depth ? $depth : '150',
					'type_id' => '91758',
					'primary_image' => $arItem['MAINPHOTO'],
					'images' => $arItem['MOREPHOTO'],
					'color_image' => $arItem['COLORIMG'],
					"attributes"=>array_values($att),
				];
				$item["attributes"][] = [
					'id' => 23500,
					'values' => [
						['value' => $arItem['DETAIL_PICTURE']]
					],
				];
				$item["attributes"][] = [
					'id' => 23536,
					'values' => [
						['value' => "false"]
					]
				];
				// var_dump($item);
				// die;

				if ( in_array($this->cabinet, ['IP', 'TI']) ){
					$item['vat'] = '0.05';
				}

				$items[] = $item;
				unset($att);
				// var_dump($items);
				// die;

				// print_r($items);
	    }
			file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/jsonArr.json', json_encode($jsonArr));
			//print_r($items);

			$this->arUpdateProducts = array_chunk($items,100);

			////$this->db->Update("wdhs_ozon_upload_status", array("percent" => '20'), "WHERE agent='products'", $err_mess.__LINE__);
			 //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/tmp/arProducts.txt", print_r($this->arUpdateProducts, true));
			 //die();
	  }

		private function getSubstImage( string $model ):string|bool
		{
			if ( empty($model) ) return false;
			$path = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/Ozon_promo/%s.jpg';
			$link = 'https://tempusshop.ru/local/cron/infograph/newinf/Ozon_promo/%s.jpg';
			if ( !file_exists( sprintf($path, $model) ) ) return false;
			return sprintf( $link, $model );
		}

		private function getSubstSizesData():array
		{
			$path = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/configs/ovh.json';
			if ( !file_exists( $path ) ) return [];

			$json = file_get_contents($path);
			$data = json_decode($json, true);

			return $data;
		}

		private function getSKUDictionary():array
		{
			$rows = $this->CurDB->select(['*'], 'ozon_sku_dict_IP')->make();
			$result = [];

			foreach ( $rows as $row ){
				$result[ $row['model'] ] = 1;
			}

			return $result;
		}

		private function getDynamicPrices():array
		{
			return [];
			if ( $this->cabinet == 'WT') return [];

			$rows = $this->CurDB->select(['*'], 'ozon_dp_prices')->where('cabinet', $this->cabinet)->make();
			$result = [];

			foreach ( $rows as $row ){
				$result[ $row['model'] ] = $row['price'];
			}

			return $result;
		}

		private function getFboPriceData():array
		{
			if ( $this->cabinet == 'WT' ) return [];
			$rows = $this->CurDB->select(['*'], "ozon_fbo_price_{$this->cabinet}")->make();
			$items = [];
			foreach ( $rows as $row ){
				$items[ $row['article'] ] = intval($row['price']);
			}

			return $items;
		}

		private function getUploadCardsList():array|bool
		{
			if ( $this->cabinet != 'WT' ) return false;
			$path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/configs/cardsUploadWT.json";

			if ( !file_exists($path) ) return false;

			$json = file_get_contents( $path );

			return json_decode( $json, true );
		}

		private function getSubstSize( string $model, string $char ):string|bool
		{
			if ( empty($this->substSizes[$model]) ) return false;
			if ( empty($this->substSizes[$model][$char]) ) return false;

			return strval($this->substSizes[$model][$char]);
		}

		private function getExchangeRate():float
		{
			switch ( $this->cabinet ){
				case 'IP':
					$currency = 'RUB';
					return 1;
					break;
				case 'TI':
					$currency = 'RUB';
					return 1;
					break;
				case 'WT':
					$currency = 'BYN';
					break;
			}

			$result = $this->db->Query("SELECT rate FROM ci_currency WHERE id = '{$currency}'");
			$row = $result->Fetch()["rate"];

			return floatval($row);
		}

		public function UploadProducts(){
			$itter = 80 / count($this->arUpdateProducts);
			$perc = 20;

			foreach ($this->arUpdateProducts as $key => $data) {
				sleep(5);
				// var_dump($data);
				//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products1.txt', print_r($data,1));
				// die;
				$data_string = json_encode(array('items' => $data));
				if( $key == 0) {
					//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products1.txt', print_r($data_string,1));
				}
				//print_r($data_string);
				// echo substr($data_string, 0, 26);
				$ch = curl_init($this->api_url . '/v3/product/import');
					curl_setopt($ch, CURLOPT_HTTPHEADER, array(
						'Api-Key:' . $this->token,
						'Client-Id:' . $this->client_id,
						'Content-Type:application/json'
					));
					curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_HEADER, false);
					$res = curl_exec($ch);
					curl_close($ch);

					// file_put_contents($_SERVER["DOCUMENT_ROOT"].'/admin/panel/engine/ozon/request.txt', "DATE: " . date('Y-m-d G:i:s') . PHP_EOL, FILE_APPEND);
					// file_put_contents($_SERVER["DOCUMENT_ROOT"].'/admin/panel/engine/ozon/request.txt', "API KEY: " . substr( $this->token, 0, 3 ) . PHP_EOL, FILE_APPEND);
					// file_put_contents($_SERVER["DOCUMENT_ROOT"].'/admin/panel/engine/ozon/request.txt', "METHOD: {$this->api_url}/v3/product/import" . PHP_EOL, FILE_APPEND);
					//file_put_contents($_SERVER["DOCUMENT_ROOT"].'/admin/panel/engine/ozon/request.txt', 'BODY:' . PHP_EOL . $data_string . PHP_EOL);
					// file_put_contents($_SERVER["DOCUMENT_ROOT"].'/admin/panel/engine/ozon/request.txt', 'RESPONSE:' . PHP_EOL . $res . PHP_EOL, FILE_APPEND);

					// print_r($res);
					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/shows/debugUpload1.txt", print_r($res, true).PHP_EOL);
					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/shows/debugUpload.txt", print_r($res, true).PHP_EOL,FILE_APPEND);
					$res = json_decode($res, true);

				    print_r($res);

					if (!empty($res['result']['task_id'])){
						$this->arLog['UPDATE']['GOOD'][] = $res['result']['task_id'];
					}else{
						$this->arLog['UPDATE']['BAD'][] = $res;
					}
					// foreach ($res['result'] as $k => $v) {
					// 		if ($v['updated'] == '1') {
					// 			$this->arLog['UPDATE']['GOOD'][] = $v['offer_id'];
					// 		} else {
					// 			$this->arLog['UPDATE']['BAD'][] = ['id' => $v['offer_id'],'errors' => $v['errors']];
					// 		}
					// }
					//
					// print_r('<br>');
					// print_r($res);
					// print_r('<br>');
					$perc = $perc + round($itter,0);
					if (intval($perc) > 100) { $perc = 99;}
					$this->updateStatus( $this->module, ['status_text' => 'Выгружаем товары', 'percent' => $perc] );
					//$this->db->Update("wdhs_ozon_upload_status", array("percent" => $perc), "WHERE agent='products'", $err_mess.__LINE__);
					// sleep(5);
			}

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

( new OzonImportProducts( $argv[1],$argv[2] ) )->run();
$workers->updateStatus("N");
