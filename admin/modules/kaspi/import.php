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

    $this->token = 'B/PupyTfqTd99t7CKGbMu1YSIJjvCtx0UQgm+oOHL/g=';


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


		$this->getItems();

		//$this->getCategory();
		/*
		текущая
		[3814] => Array
			 (
					 [code] => Master - Wrist watches
					 [title] => Часы наручные
			 )

		*/

		$this->getProps();
		$this->BuildArray();

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
			"ID" => 180342

    );

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);


    while ($el = $result->GetNextElement()){
			$res = $el->getFields();
			foreach ($el->getProperties() as $key => $value) {
				$props[$value['ID']] = $value;
			}
			$res['PROPERTIES'] = $props;
			if (!empty($res['PROPERTY_INFOOZON_IMAGE_VALUE'])) {
				$mainphoto = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_INFOOZON_IMAGE_VALUE']);
			} else {
				$mainphoto = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_IMAGE_MARKETPLACE_VALUE']);
			}
			$res['MAINPHOTO'] = $mainphoto;
			$res['MOREPHOTO'][] = $mainphoto;
			if (!empty($res['PROPERTY_IMAGE_MARKETPLACE_VALUE'])) {
				$colorimg = 'https://tempusshop.ru'.CFile::GetPath($res['PROPERTY_IMAGE_MARKETPLACE_VALUE']);
			}
			if (!empty($res['PROPERTY_MORE_PHOTO_VALUE'])) {
				foreach ($res['PROPERTY_MORE_PHOTO_VALUE'] as $key => $value) {
						$res['MOREPHOTO'][] = 'https://tempusshop.ru'.CFile::GetPath($value);
				}
			}

			$res['MOREPHOTO'] = array_slice($res['MOREPHOTO'],0,13);
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
		print_r('\n'.count($this->items).'\n');
	}

	  public function getCategory(){
		$ch = curl_init('https://kaspi.kz/shop/api/products/classification/categories');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'X-Auth-Token:' . $this->token,
				'Content-Type:application/json'
			));
			//curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HEADER, false);
			$res = curl_exec($ch);
			curl_close($ch);
			// print_r($res);
			$res = json_decode($res, true);
			print_r($res);
	}

	public function getProps(){
		$path = 'https://kaspi.kz/shop/api/products/classification/attributes?c=Master%20-%20Wrist%20watches&HTTP/1.1=&Host=kaspi.kz';
		$ch = curl_init($path);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'X-Auth-Token:' . $this->token,
				'Content-Type:application/json'
			));
			//curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HEADER, false);
			$res = curl_exec($ch);
			curl_close($ch);

			$res = json_decode($res, true);
			//print_r($res);
			$this->props = $res;
	}

	public function BuildArray(){
		foreach ($this->items as $key => $item) {
			$arSection = getSectionsElement($item["ID"]);
			$attTmp = [];
			$brand = $arSection[1]['NAME'];
			$arbrnadcaps = ['Casio','Fossil','Orient','Jacques Lemans','Wenger','Moschino','George Kini'];
			if (in_array($brand,$arbrnadcaps)) {
				$brand = strtoupper($brand);
			}
			$arexbrnad = ['Luch','Bering','Obaku','Thomas Earnshaw','Dolce & Gabbana','Cluse','Bellevue','Carisen','Chronotech','Harry Lime','Karen Millen','Morgan','Sisley','Breil','Rochas'];
			if (!in_array($brand,$arexbrnad)) {
				if ($item['PROPERTIES']['127']['VALUE'] == 'Кварцевые') {
					$type = 'кварцевые';
				} else if ($item['PROPERTIES']['127']['VALUE'] == 'Автокварц (кинетик)' || $item['PROPERTIES']['127']['VALUE'] == 'Процессор') {
					$type = 'мехатронные';
				} else {
					$type = 'механические';
				}
				$attTmp[] = [
					"code" => "Wrist watches*Osnovnye harakteristiki.wrist watches*type",
	      	"value" => $type
				];
				//----
				if ($item['PROPERTY_FACE_VALUE'] == 'Аналоговый') {
					$face = 'аналоговый (стрелки)';
				} else if ($item['PROPERTY_FACE_VALUE']  == 'Цифровой') {
					$face = 'цифровой (электронный)';
				} else {
					$face = 'бинарный';
				}
				$attTmp[] = [
					"code" => "Wrist watches*Osnovnye harakteristiki.wrist watches*clock-face",
	      	"value" => $face
				];
				//===
				$attTmp[] = [
					"code" => "Wrist watches*Osnovnye harakteristiki.wrist watches*symbols",
					"value" => 'арабские'
				];
				//===
				if ($item['PROPERTIES']['281']['VALUE'] == 'Круг') {
					$shape = 'круг';
				} else if ($item['PROPERTIES']['281']['VALUE'] == 'Прямоугольник') {
					$shape = 'прямоугольник';
				} else if ($item['PROPERTIES']['281']['VALUE'] == 'Бочка') {
					$shape = 'бочкообразная';
				} else if ($item['PROPERTIES']['281']['VALUE'] == 'Овал') {
					$shape = 'овал';
				}
				$attTmp[] = [
					"code" => "Wrist watches*Konstrukcia.wrist watches*shape",
					"value" => $shape
				];
				//===
				if ($item['PROPERTIES']['128']['VALUE'][0] == 'Каучук' || $item['PROPERTIES']['128']['VALUE'][0] == 'Кожа') {
					$casemat = 'полимерный пластик';
				} else if ($item['PROPERTIES']['128']['VALUE'][0] == 'Полимерный материал') {
					$casemat = 'полимер';
				} else {
					$casemat = mb_strtolower($item['PROPERTIES']['128']['VALUE'][0]);
				}
				$attTmp[] = [
					"code" => "Wrist watches*Konstrukcia.wrist watches*case material",
					"value" => $casemat
				];
				//===
				if ($item['PROPERTIES']['129']['VALUE'][0] == 'Золото') {
					$bandmat = 'металлический сплав';
				} else if ($item['PROPERTIES']['128']['VALUE'][0] == 'Полимерный материал') {
					$bandmat = 'полимер';
				} else if ($item['PROPERTIES']['128']['VALUE'][0] == 'Кожа с покрытием') {
					$bandmat = 'кожа';
				} else {
					$bandmat = mb_strtolower($item['PROPERTIES']['128']['VALUE'][0]);
				}
				$attTmp[] = [
					"code" => "Wrist watches*Konstrukcia.wrist watches*band material",
					"value" => $bandmat
				];
				//===
				if ($item['PROPERTIES']['130']['VALUE'] == 'Органическое') {
					$window = 'пластиковое';
				} else if ($item['PROPERTIES']['130']['VALUE'] == 'Минеральное') {
					$window = 'минеральное';
				} else if ($item['PROPERTIES']['130']['VALUE'] == 'Сапфировое') {
					$window = 'сапфировое';
				}
				$attTmp[] = [
					"code" => "Wrist watches*Konstrukcia.wrist watches*dial window material",
					"value" => $window
				];
				//===
				$attTmp[] = [
					"code" => "Wrist watches*Konstrukcia.wrist watches*case diameter",
					"value" => intval($item['PROPERTIES']['130']['VALUE']) . '.0мм'
				];
				//===
				if(!empty($item['PROPERTIES']['2818']['VALUE']) && !empty($item['PROPERTIES']['130']['VALUE']) && !empty($item['PROPERTIES']['125']['VALUE'])) {
					$attTmp[] = [
						"code" => "Wrist watches*Konstrukcia.wrist watches*size",
						"value" => $item['PROPERTIES']['2818']['VALUE'] .'x'.$item['PROPERTIES']['130']['VALUE'].'x'. $item['PROPERTIES']['125']['VALUE']
					];
				}
				//===
				if ($item['PROPERTIES']['131']['VALUE'] == 'Число') {
					$date = 'число';
				} else if ($item['PROPERTIES']['131']['VALUE'] == 'Число и день недели') {
					$date = '"число"+"день недели"';
				} else if ($item['PROPERTIES']['131']['VALUE'] == 'Число и месяц') {
					$date = '"число"+"месяц"';
				} else if ($item['PROPERTIES']['131']['VALUE'] == 'Число, месяц и день недели') {
					$date = '"число"+"месяц"+"день недели"';
				} else if ($item['PROPERTIES']['131']['VALUE'] == 'Число, месяц, год и день недели') {
					$date = '"число"+"месяц"+"день недели"+"год"';
				}
				$attTmp[] = [
					"code" => "Wrist watches*Osobennosti.wrist watches*calendar",
					"value" => $date
				];
				//===
				$attTmp[] = [
					"code" => "Wrist watches*Osobennosti.wrist watches*country",
					"value" => $item['PROPERTIES']['146']['VALUE']
				];
				//===
				$attTmp[] = [
					"code" => "Wrist watches*Osobennosti.wrist watches*code",
					"value" => $item['PROPERTY_CML2_ARTICLE_VALUE']
				];
				//===
				if ($item['PROPERTIES']['126']['VALUE'][0] == 'Мужские' || $item['PROPERTIES']['126']['VALUE'][0] == 'Мужской') {
					$pol = 'для мужчин';
				} else if ($item['PROPERTIES']['126']['VALUE'][0] == 'Женские' || $item['PROPERTIES']['126']['VALUE'][0] == 'Женский') {
					$pol = 'для женщин';
				} else if ($item['PROPERTIES']['126']['VALUE'][0] == 'Детские') {
					$pol = 'для детей';
				} else if ($item['PROPERTIES']['126']['VALUE'][0] == 'Унисекс') {
					$pol = '"для мужчин"+"для женщин"';
				}
				$attTmp[] = [
					"code" => "Fashion accessories*Harakteristiki.fashion accessories*for whom",
					"value" => $pol
				];
				//===
				if ($item['PROPERTIES']['2746']['VALUE'][0] == 'Разноцветный' || $item['PROPERTIES']['2746']['VALUE'][0] == 'Перламутровый') {
					$col1 = 'мультиколор';
				} else {
						$col1 = mb_strtolower($item['PROPERTIES']['2746']['VALUE'][0]);
				}
				$attTmp[] = [
					"code" => "Wrist watches*Osnovnye harakteristiki.wrist watches*colour",
					"value" => $col1
				];
				//===
				$attTmp[] = [
					"code" => "Fashion accessories*Harakteristiki.fashion accessories*color",
					"value" => $col1
				];
				//===
				$attTmp[] = [
					"code" => "Wrist watches*Konstrukcia.wrist watches*water resistant",
					"value" => 1
				];
				//===
				if ($item['PROPERTIES']['134']['VALUE'] == 'WR30m') {
					$wrRes = 'WR30 (3 атм)';
				} else if ($item['PROPERTIES']['134']['VALUE'] == 'WR50m') {
					$wrRes = 'WR50 (5 атм)';
				} else if ($item['PROPERTIES']['134']['VALUE'] == 'WR100m') {
					$wrRes = 'WR100 (10 атм)';
				} else if ($item['PROPERTIES']['134']['VALUE'] == 'WR150m') {
					$wrRes = 'WR150 (15 атм)';
				} else if ($item['PROPERTIES']['134']['VALUE'] == 'WR180m') {
					$wrRes = 'WR150 (15 атм)';
				} else if ($item['PROPERTIES']['134']['VALUE'] == 'WR200m') {
					$wrRes = 'WR200 (20 атм)';
				} else if ($item['PROPERTIES']['134']['VALUE'] == 'WR300m') {
					$wrRes = 'WR300 (30 атм)';
				} else if ($item['PROPERTIES']['134']['VALUE'] == 'WR500m') {
					$wrRes = 'WR500 (50 атм)';
				} else if ($item['PROPERTIES']['134']['VALUE'] == 'WR600m') {
					$wrRes = 'WR600 (60 атм)';
				} else if ($item['PROPERTIES']['134']['VALUE'] == 'WR1000m') {
					$wrRes = 'WR1000 (100 атм)';
				}
				$attTmp[] = [
					"code" => "Wrist watches*Konstrukcia.wrist watches*water resistant depth",
					"value" => $wrRes
				];
				$tmpArray = [
				 "sku" => $item['PROPERTY_WBARTICLE_KZ_VALUE'],
				 "title" => $item['PROPERTY_NAME_MARKETPLACE_VALUE'],
				 "brand"  => $brand,
				 "category"  => "Master - Wrist watches",
				 "description" => $item['PROPERTIES']['2849']['VALUE']['TEXT'],
				 "attributes" => $attTmp,
				 "images" => $res['MAINPHOTO']
				];

				$path = 'https://kaspi.kz/shop/api/products/import';
				$ch = curl_init($path);
					curl_setopt($ch, CURLOPT_HTTPHEADER, array(
						'X-Auth-Token:' . $this->token,
						'Accept: application/json',
						'Content-Type:text/plain'
					));
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tmpArray));
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_HEADER, false);
					$res = curl_exec($ch);
					print_r($res);
					curl_close($ch);

					$res = json_decode($res, true);
			}
		}
	}

}

(new OzonImportProductsKZ())->run();
