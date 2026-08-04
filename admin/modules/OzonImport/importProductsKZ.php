<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class OzonImportProductsKZ{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;

		$strSql = "SELECT * FROM wdhs_ozon_main_settings";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $arSetting[$row['name']] = $row['value'];
		}

    $this->api_url = $arSetting['api_url'];
    $this->client_id = '1776829';
    $this->token = '00bdecf1-32c2-4b74-aec9-73bee4ff4b4e';


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

		$strSql = "SELECT * FROM wdhs_ozon_attribute_category";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $this->arResult['ACTIVE_CATEGORY'][] = $row['name'];
			$this->catID[] = trim($row['category_id']);
		}

		$strSql = "SELECT * FROM wdhs_ozon_attribute";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $this->arResult['ACTIVE_ATT'][$row['attribute_id']] = $row;
		}

		$strSql = "SELECT * FROM wdhs_ozon_attribute_bitrix";
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

		$strSql = "SELECT * FROM wdhs_ozon_attribute_matches WHERE attribute_value_id != ''";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $this->actMatches[ $row['attribute_id'] ][ $row['property_id'] ][ $row['property_value_id'] ] = ['attribute_value_id'=>$row['attribute_value_id'],'attribute_name'=> $row['attribute_name']];
		}

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
		$this->getItems();
		$itemsT = microtime(true);
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
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/products/shows/$date.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/products/shows/$date.txt", print_r('#SPLIT#',true).PHP_EOL,FILE_APPEND);
	}

  public function getItems(){

		//$this->db->Update("wdhs_ozon_upload_status", array("status" => "'INCOMPLETE'","percent" => "'0'"), "WHERE agent = 'products'", $err_mess.__LINE__);
		$strSql = "SELECT model FROM ci_price WHERE active_ozkz = 'Y'";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $models[]  = $row['model'];
		}
		//$models = implode(",", $models);

    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_MORE_PHOTO","PROPERTY_AEN","PROPERTY_WBARTICLE_KZ","PROPERTY_PRICE_OZKZ","PROPERTY_NAME_MARKETPLACE",
		"PROPERTY_INFOOZON_IMAGE","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_FACE");
		// foreach ($this->nBt as $key => $value) {
		// 	$arSelect[] = $value;
		// }

    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
			"PROPERTY_CML2_ARTICLE" => $models,
			//"ID" => 1062

    );

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
			$res['MOREPHOTO'] = array_slice($morephoto,0,13);
			$res['MAINPHOTO'] = $mainphoto;
			if ($res['PROPERTY_FACE_ENUM_ID'] == 1872) {
				$res['MOREPHOTO'][] = "https://tempusshop.ru/upload/wb_after_img.jpg";
			} else {
				$res['MOREPHOTO'][] = "https://tempusshop.ru/upload/wb_after_img_cif.jpg";
			}
			$this->items[$res['ID']] = $res;
			unset($colorimg);
			unset($morephoto);
			unset($mainphoto);
    }
		print_r('\n'.count($this->items));
		//$this->db->Update("wdhs_ozon_upload_status", array("percent" => '10'), "WHERE agent='products'", $err_mess.__LINE__);
		//print_r($this->items);
		//die();
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/products/tmp/arUpdateProducts.txt", print_r($this->items, true));
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

								foreach ($arItem['PROPERTIES'][$propId]['VALUE'] as $k => $v) {
									if (!empty($this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE_ENUM_ID'][$k]]['attribute_value_id'])) {
										$arr_comp[] = ['dictionary_value_id'=> $this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE_ENUM_ID'][$k]]['attribute_value_id'], 'value' => $this->actMatches[$attId][$propId][$arItem['PROPERTIES'][$propId]['VALUE_ENUM_ID'][$k]]['attribute_name']
									];
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
										$arrImg[] = ['value'=>'https://tempusshop.ru'.CFile::GetPath($v)];
									}
									$att[] = array(
 		  						 'id' => $attId,
 		  						 'values' => $arrImg
 		  						);
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
			$oneToDelete = '';
			foreach($att as $key => $elem){
				if ($elem['id'] == 8789 ){
					$oneToDelete = $key;
				}
				if($elem['id'] == 8790){
					unset($oneToDelete);
					break;
				}
				if ($elem['id'] == 4195 ){
					unset($att[$key]);
				}
				if ($elem['id'] == 8229) {
					$att[$key]['values'][0] = ['dictionary_value_id' => 91758];
				}
				if ($elem['id'] == 22336) {
					$string = str_replace(', ,',',',$att[$key]['values'][0]['value']);
					if (strlen($string) > 255) {
						$string = substr($string, 0, 255); // Обрезаем до 255 символов
						$lastCommaPosition = strrpos($string, ',');
							if ($lastCommaPosition !== false) {
									$string = substr($string, 0, $lastCommaPosition);
							}
					}
					$att[$key]['values'][0]['value'] = $string;

				}
			}
			$att[12141]['id'] = 12141;
			$att[12141]['values'][0]['value'] = $arItem['PROPERTY_CML2_ARTICLE_VALUE'];


			if(!empty($oneToDelete) ){
				unset($att[$oneToDelete]);
			}


			$items[] = array(
		    "barcode" => $arItem['PROPERTY_AEN_VALUE'],
		    "description_category_id" => $this->catID[0],
		    "offer_id" => $arItem['PROPERTY_WBARTICLE_KZ_VALUE'],
				"price" => $arItem['PROPERTY_PRICE_OZKZ_VALUE'],
				"vat" => "0",
				"name" => $arItem['PROPERTY_NAME_MARKETPLACE_VALUE'],
				"weight" => '200',
				"height" => '150',
				'width' => '160',
				'dimension_unit' => 'mm',
				'depth' => '200',
				'currency_code' => 'RUB',
				'primary_image' => $arItem['MAINPHOTO'],
				'images' => $arItem['MOREPHOTO'],
				'color_image' => $arItem['COLORIMG'],
				"attributes"=>array_values($att),
			);
			unset($att);
			//print_r($items);
    }
		print_r($items);
		$this->arUpdateProducts = array_chunk($items,100);
		//die();
		//$this->db->Update("wdhs_ozon_upload_status", array("percent" => '20'), "WHERE agent='products'", $err_mess.__LINE__);
		 //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/products/tmp/arProducts.txt", print_r($this->arUpdateProducts, true));
  }

	public function UploadProducts(){
		$itter = 80 / count($this->arUpdateProducts);
		$perc = 20;
		foreach ($this->arUpdateProducts as $key => $data) {
			$data_string = json_encode(array('items' => $data));
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
				$res = json_decode($res, true);

				foreach ($res['result'] as $k => $v) {
						if ($v['updated'] == '1') {
							$this->arLog['UPDATE']['GOOD'][] = $v['offer_id'];
						} else {
							$this->arLog['UPDATE']['BAD'][] = ['id' => $v['offer_id'],'errors' => $v['errors']];
						}
				}

				print_r('<br>');
				print_r($res);
				print_r('<br>');
				$perc = $perc + round($itter,0);
				if (intval($perc) > 100) { $perc = 99;}
				//$this->db->Update("wdhs_ozon_upload_status", array("percent" => $perc), "WHERE agent='products'", $err_mess.__LINE__);
		}

	}

}

//(new OzonImportProductsKZ())->run();
