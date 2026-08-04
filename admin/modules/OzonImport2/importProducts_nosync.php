<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
		Bitrix\Main\Loader,
		Bitrix\Iblock\PropertyEnumerationTable;

class OzonImportProducts{
	public function __construct(){
		global $DB;
		$this->loadModules();
		$this->CurDB = new DBPanel();
		$this->db = $DB;

		$result = $this->CurDB->query("SELECT * FROM ozon_main_settings_TI");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		unset($result);
		unset($rows);
		$this->module = 'importProducts_TI';
    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];


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

		$result = $this->CurDB->query("SELECT * FROM ozon_sales_prices_TI");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$checkSalePriceTmp[$row['model']][] = $row['price'];
		}
		foreach($checkSalePriceTmp as $key => $values) {
			$this->checkSalePrice[$key] = min($values);
		}
		unset($result);
		unset($rows);
		unset($checkSalePriceTmp);

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
		$arStat = [
			'status' => 'IN_PROCESS',
			'status_text' => 'Начало',
			'percent' => 0,
			'time_start' => date('Y-m-d G:i:s')
		];
		$this->updateStatus($this->module, 'Начало', 0, 'IN_PROCESS');
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
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/shows/$date.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/shows/$date.txt", print_r('#SPLIT#',true).PHP_EOL,FILE_APPEND);
		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено'
			'percent' => 100,
			'time_end' => date('Y-m-d G:i:s')
		];
		$this->updateStatus($this->module, $arStat);
	}

		public function getItems(){
			// $checkArray = array("LQ-139EMV-7A","LQ-142E-7A","LTP-1183A-1A","MW-59-7B","LTP-V002L-1B","BI5000-87L","PRW-61LD-5E","VR56J010Y","LA-20WH-1B","LTP-V006L-4B","GA-2100-7A","MTP-VD300L-2E","VQ87J002Y","GA-140-1A1","VP46J035Y","LLA3J301Y","Q880J101Y","LTP-V005GL-7A","GMA-S120GS-8A","F279J204Y","MTP-1375L-7A","B640WBG-1B","V32AJ001Y","LTP-V006L-1B","MTP-B205D-7E","LTP-V002D-1A","GBD-200-9E","DW-5600HR-1E","EFV-C110D-2B","GST-B400BB-1A","LTP-1215A-2A","LTP-V002L-1B3","AEQ-100W-1A","RA-AA0818L","GA-B2100-1A1","A-168WEGB-1B","LA-680WGA-1E","LA-680WGA-9B","VP46J030Y","LTP-1130N-7B","F-91WM-2A","MQ-76-7A1","MDV-107D-3A","MW-59-7E","EFV-620D-1A4");
			////$this->db->Update("wdhs_ozon_upload_status", array("status" => "'INCOMPLETE'","percent" => "'0'"), "WHERE agent = 'products'", $err_mess.__LINE__);
			$strSql = "SELECT model FROM ci_price WHERE active_ozti = 'Y'";
			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$models[]  = $row['model'];
				// if (!in_array($row['model'],$checkArray)) {
				// }

			}
			//$models = implode(",", $models);
			//$models = array("CH2564");
	    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_MORE_PHOTO","PROPERTY_AEN","PROPERTY_WBARTICLE","PROPERTY_PRICE_OZTI","PROPERTY_NAME_MARKETPLACE",
			"PROPERTY_INFOOZON_IMAGE","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_FACE");
			// foreach ($this->nBt as $key => $value) {
			// 	$arSelect[] = $value;
			// }
		//$models = array("RN-WG0415A");
			// $models = array("AE-1500WH-8B", "MTP-V005D-2B4", "MTP-V001D-1B", "A-158WA-1", "MTP-VD01D-1B", "A-159WA-N1", "A-168WG-9E", "MTP-V005L-1B5", "MTP-V001L-1B", "LTP-V007D-4E", "AE-1200WHD-1A", "MTP-1375D-1A", "W-218H-1B", "MTP-1183A-1A", "MTP-1384D-1A", "MTP-VD01D-1B", "A-168WA-1Q");
	    $arFilter = Array(
	      "IBLOCK_ID" => CProSet::IB_CATALOG,
				"=PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
				"=PROPERTY_CML2_ARTICLE" => $models,
				// "XML_ID" => [13263]
				// "ID" => [1002]
	    );
			//$arFilter["PROPERTY_BRAND"] = '43508';
	    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);


	    while ($el = $result->GetNextElement()){
				$res = $el->getFields();
				foreach ($el->getProperties() as $key => $value) {
					$props[$value['ID']] = $value;
				}
				$res['PROPERTIES'] = $props;

				if (!empty($res['PROPERTY_MORE_PHOTO_VALUE'])) {
					foreach ($res['PROPERTY_MORE_PHOTO_VALUE'] as $key => $value) {
						$morephoto[] = 'https://tempusshop.ru'.CFile::GetPath($value);
					}
				}
				if (!empty($res['PROPERTY_INFOOZON_IMAGE_VALUE'])) {
					$mainphoto = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_INFOOZON_IMAGE_VALUE']);
				} else {
					$mainphoto = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_IMAGE_MARKETPLACE_VALUE']);
				}
				if (!empty($res['PROPERTY_IMAGE_MARKETPLACE_VALUE'])) {
					$colorimg = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_IMAGE_MARKETPLACE_VALUE']);
				}
				$res['COLORIMG'] = $colorimg;
				if (is_array($morephoto)) {
					$morephoto = array_slice($morephoto,0,12);
				}
				$res['MOREPHOTO'] = $morephoto;
				$res['MAINPHOTO'] = $mainphoto;
				if ($res['PROPERTY_FACE_ENUM_ID'] == 1872) {
					$res['MOREPHOTO'][] = "https://tempusshop.ru/upload/wb_after_img.jpg";
				} else {
					$res['MOREPHOTO'][] = "https://tempusshop.ru/upload/wb_after_img_cif.jpg";
				}
				if (!empty($res['PROPERTY_CML2_ARTICLE_VALUE'])) {
					$res['article'] = $res['PROPERTY_CML2_ARTICLE_VALUE'];
				}
				$this->items[$res['ID']] = $res;
				unset($colorimg);
				unset($morephoto);
				unset($mainphoto);
	    }
			print_r(count($this->items));
			print_r('+++');
			//$this->db->Update("wdhs_ozon_upload_status", array("percent" => '10'), "WHERE agent='products'", $err_mess.__LINE__);
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/tmp/arUpdateProducts.txt", print_r($this->items, true));
			print_r('@@@');
			// print_r($this->items);
		}

	  public function BuildArray() {
	    foreach ($this->items as $key => $arItem) {
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
									$att[] = array(
			 						 'id' => $attId,
			 						 'values' => array(array('value' => $arItem['PROPERTIES'][$propId]['VALUE'])
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

						if (in_array($arItem['article'], array("MTP-1374D-1A","MTP-1183A-1A","A-158WA-1","LTP-V007D-7E","LA-680WEA-7E","LTP-V007SG-9E","AE-1500WH-8B","LTP-V001D-7B","LTP-V007G-9E","A-168WA-1Q","LTP-V007D-7B","LA-670WEA-7E","LTP-V007L-7E2","W-218H-1B","A-178WA-1A","LTP-V001GL-9B","MTP-V001GL-9B","LTP-V007L-9E","MTP-V001L-7B","MTP-VD01D-1B","AE-1200WHD-1A","LTP-V007D-4E","MTP-1303D-1A","LTP-V007L-7B2","LTP-V007D-2E","MTP-1183A-2A","MTP-V001L-1B","MTP-1183A-7A","AQ-230A-1D","A-159WA-N1","LTP-V007SG-9B","MTP-1375D-1A","MTP-V005D-2B4","MTP-1302D-1A1","LTP-V007L-9B","LTP-V007L-7E1","LTP-V005D-7A","AQ-230A-7D","MTP-VD01D-1E2","LTP-V009D-4E","MTP-V002D-1B","F-91W-1Q","MTP-V001D-7B","AE-1500WHX-3A","LTP-V005D-4B2","MTP-1384D-1A","A-168WA-8A","MW-240-1E2","AQ-230A-7A","LTP-VT01D-7B","LTP-V009D-2E","W-800H-1A","MTP-V006D-2B","CA-53W-1","LTP-V001GL-7B","LTP-V009D-7E","MTP-VD01D-7C","MTP-V001D-1B","MTP-VD01D-2B","MTP-V005D-1B","MTP-V002L-1B","MTP-V004D-2B","MRW-200H-1B","F-91W-3S","GA-2100-1A1","W-800H-1B","W-218HC-4A2","LTP-V007L-7B1","AE-1500WHX-1A","A-700WE-1A","LTP-V009D-1E","LA-670WEGA-9E","MTP-V002D-2B3","EFV-640D-1A","MTP-V002L-5B3","MTP-V300D-1A","A-168WEGG-1B","LTP-V009G-7E","LTP-V001L-7B","AE-1500WH-8B2","EFV-C100D-1B","A-159W-N1","MTP-VD01D-2E","AE-1500WH-1A","LTP-1169D-7A","W-218H-1A","MTP-1384D-2A","LTP-V001D-1B","MTP-V001GL-1B","MW-240-1B","LTP-VT01D-1B","LRW-200H-7B","LTP-V005D-2B3","LTP-1169D-1A","LTP-V007G-9B","MTP-VD01D-3B","F-91WM-7A","MTP-V002D-7B","LTP-1183A-7A","LA-680WA-1E","MTP-V004D-1C","LA-680WA-1B","LA-20WH-2A","MTP-VD01D-1E","GA-2100-1A","F-91WS-7E","AMW-870D-1A","W-218HC-8A","EFR-526D-1A","LTP-V002D-4B","MTP-V006D-1B2","MW-240-1E","MTP-V005D-2B5","AE-1200WH-1B","DB-360-1A","MTP-V005D-7B5","LTP-V001L-1B","MTP-V005D-7B4","EFR-571D-1A","LTP-V005SG-7A","LA-670WA-4E","MTP-V004D-1B2","MTP-VD01L-1B","MQ-24-7B","LTP-V005D-2B","AE-1200WHB-3B","MTP-V005L-2B4","LTP-V007D-1E","G-5600UE-1E","AE-1200WH-1A","MTP-V005D-7B","MTP-V006D-1B","GBD-200-1E","MTP-V004D-1B","F-91WM-3A","MTP-1374L-1A","MTS-100D-1A","MTP-1314D-1A","MTP-V005D-1B4","MTP-VD03D-1A","MTS-100D-2A","DW-5600BBN-1E","EF-316D-1A","MTP-1183G-7A"))) {
								$att[$keyDetail]['values'][0] = ['value' => $arItem['article']];
						}
					}
					if ($elem['id'] == '9048') {
						if (in_array($arItem['article'],array("LTP-V007SG-9E","W-218H-1B","LTP-V007D-4E","MTP-V001L-1B","LTP-V007SG-9B","MTP-1375D-1A","AQ-230A-7D","MTP-V001D-7B","MW-240-1E2","AQ-230A-7A","LTP-V001GL-7B","MTP-V004D-2B","W-218HC-4A2","EFV-640D-1A","LTP-V001L-7B","GA-2100-1A","F-91WS-7E","AMW-870D-1A","W-218HC-8A","MTP-V006D-1B2","MTP-V004D-1B2","MTP-VD01L-1B","G-5600UE-1E","DW-5600BBN-1E"))) {
								$att[$keyDetail]['values'][0] = ['value' => 'TAA05'];
						}
						if (in_array($arItem['article'],array("AE-1500WH-8B","LTP-V007L-7E2","MTP-VD01D-1B","LTP-V007D-2E","LTP-V005D-7A","AE-1500WHX-3A","CA-53W-1","LTP-V009D-7E","LTP-V007L-7B1","MTP-V002D-2B3","AE-1500WH-8B2","W-218H-1A","MW-240-1B","LTP-VT01D-1B","LRW-200H-7B","LTP-V005D-2B3","LTP-1169D-1A","MTP-V002D-7B","LTP-V001L-1B","AE-1200WHB-3B","AE-1200WH-1A","MTS-100D-1A"))) {
								$att[$keyDetail]['values'][0] = ['value' => 'TAA06'];
						}
						if (in_array($arItem['article'],array("MTP-V002L-1B","MTP-1374D-1A"))) {
								$att[$keyDetail]['values'][0] = ['value' => 'TAA06'];
						}
						if (in_array($arItem['article'],array("MTP-1183A-1A","LTP-V001D-7B","LTP-V007D-7B","A-178WA-1A","MTP-V001L-7B","AE-1200WHD-1A","MTP-VD01D-1E2","F-91W-1Q","LTP-V005D-4B2","LTP-V009D-2E","W-800H-1A","MTP-VD01D-7C","MRW-200H-1B","F-91W-3S","AE-1500WHX-1A","MTP-V002L-5B3","MTP-V300D-1A","EFV-C100D-1B","MTP-V005D-2B5","DB-360-1A","MTP-V005D-7B4","EFR-571D-1A","MTP-V005D-7B","GBD-200-1E"))) {
								$att[$keyDetail]['values'][0] = ['value' => 'TAA01'];
						}
						if (in_array($arItem['article'],array("A-158WA-1","LTP-V007G-9E","MTP-V001GL-9B","AQ-230A-1D","MTP-1302D-1A1","LTP-V009D-4E","MTP-1384D-1A","MTP-V001D-1B","A-700WE-1A","A-159W-N1","LTP-1183A-7A","LA-680WA-1E","MTP-V004D-1C","LA-680WA-1B","LA-20WH-2A","EFR-526D-1A","LTP-V002D-4B","MW-240-1E","LTP-V005SG-7A","LA-670WA-4E","MQ-24-7B","MTP-V005L-2B4","MTP-1314D-1A","EF-316D-1A"))) {
								$att[$keyDetail]['values'][0] = ['value' => 'TAA02'];
						}
						if (in_array($arItem['article'],array("LTP-V007D-7E","MTP-1374L-1A","A-168WA-1Q","MTP-1303D-1A","LTP-V007L-9B","MTP-V002D-1B","A-168WA-8A","MTP-V006D-2B","MTP-VD01D-2B","GA-2100-1A1","LTP-V009D-1E","A-168WEGG-1B","MTP-VD01D-2E","LTP-V001D-1B","AE-1200WH-1B","MTP-V005D-7B5","LTP-V005D-2B","LTP-V007D-1E","MTP-V006D-1B","MTP-V004D-1B","F-91WM-3A","MTP-V005D-1B4","MTP-VD03D-1A","MTP-1183G-7A"))) {
								$att[$keyDetail]['values'][0] = ['value' => 'TAA03'];
						}
						if (in_array($arItem['article'],array("LA-680WEA-7E","LA-670WEA-7E","LTP-V001GL-9B","LTP-V007L-9E","LTP-V007L-7B2","MTP-1183A-2A","MTP-1183A-7A","A-159WA-N1","MTP-V005D-2B4","LTP-V007L-7E1","LTP-VT01D-7B","MTP-V005D-1B","W-800H-1B","LA-670WEGA-9E","LTP-V009G-7E","AE-1500WH-1A","LTP-1169D-7A","MTP-1384D-2A","MTP-V001GL-1B","LTP-V007G-9B","MTP-VD01D-3B","F-91WM-7A","MTP-VD01D-1E","MTS-100D-2A"))) {
								$att[$keyDetail]['values'][0] = ['value' => 'TAA04'];
						}
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
					$price = intval($arItem['PROPERTY_PRICE_OZTI_VALUE']) * 1.9;
				} else {
					$price = intval($arItem['PROPERTY_PRICE_OZTI_VALUE']);
				}
				if ($price == '' || empty($price)) {
					$prices[$arItem['article']] = $price;
				}
				$items[] = array(
			    "barcode" => $arItem['PROPERTY_AEN_VALUE'],
			    "description_category_id" => $this->catID[0],
			    "offer_id" => $arItem['PROPERTY_WBARTICLE_VALUE'],
					"price" => strval($price),
					"vat" => "0",
					"name" => $arItem['PROPERTY_NAME_MARKETPLACE_VALUE'],
					"weight" => '200',
					"height" => '120',
					'width' => '110',
					'dimension_unit' => 'mm',
					'depth' => '150',
					'primary_image' => $arItem['MAINPHOTO'],
					'images' => $arItem['MOREPHOTO'],
					'color_image' => $arItem['COLORIMG'],
					"attributes"=>array_values($att),
				);
				unset($att);


				// print_r($items);
	    }
			//print_r($items);
			$this->arUpdateProducts = array_chunk($items,100);

			////$this->db->Update("wdhs_ozon_upload_status", array("percent" => '20'), "WHERE agent='products'", $err_mess.__LINE__);
			 file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/tmp/arProducts.txt", print_r($this->arUpdateProducts, true));
			 //die();
	  }

		public function UploadProducts(){
			$itter = 80 / count($this->arUpdateProducts);
			$perc = 20;
			foreach ($this->arUpdateProducts as $key => $data) {
				// var_dump($data);
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
					// print_r($res);
					file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/shows/debugUpload1.txt", print_r($res, true).PHP_EOL);
					file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/products/shows/debugUpload.txt", print_r($res, true).PHP_EOL,FILE_APPEND);
					$res = json_decode($res, true);
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

					print_r('<br>');
					print_r($res);
					print_r('<br>');
					$perc = $perc + round($itter,0);
					if (intval($perc) > 100) { $perc = 99;}
					$this->updateStatus( $this->module, ['status_text' => 'Выгружаем товары', 'percent' => $perc] );
					//$this->db->Update("wdhs_ozon_upload_status", array("percent" => $perc), "WHERE agent='products'", $err_mess.__LINE__);
					sleep(1);
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

(new OzonImportProducts())->run();
