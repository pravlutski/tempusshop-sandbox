<?
class CYandexParser{
	public $path = "";
	public $v = "";
	function __construct($file){

		$this->path = $file;

		if (!class_exists('SpreadsheetReader')){
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}
		CProSet::setOption("YANDEX_PARSE_ALL", "");
		$this->TsTriggers = new TsTriggers();
	}
	function getArticles(){
		global $DB;

		$strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE 
		FROM 
			b_iblock_element el 
		LEFT JOIN 
			b_iblock_element_prop_s16 pr 
		ON el.ID=pr.IBLOCK_ELEMENT_ID 
		WHERE 
			el.ACTIVE = 'Y' AND el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";
			
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arArticle = array();
		while ($arFields = $results->Fetch()){
			$arArticle[$arFields["ID"]] = $arFields["ARTICLE"];
		}
		return $arArticle;
	}
	function getPrices(){
		global $DB;

		$strSql = "SELECT product_id, price_discount_ya FROM ci_price_catalog";
			
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arPrice = array();
		while ($arFields = $results->Fetch()){
			$arPrice[$arFields["product_id"]] = $arFields["price_discount_ya"];
		}
		return $arPrice;
	}
	
	function clear(){
		global $DB;
		$DB->Query("TRUNCATE TABLE ci_yandex_shop", false, $err_mess.__LINE__);
		$DB->Query("TRUNCATE TABLE ci_yandex_price", false, $err_mess.__LINE__);
		CProSet::setOption("PARSE_CATALOG_YANDEX", 0);
		//unlink($this->path);
	}
	
	function getPricesUnq($ar, $col_id, $arColShop){
		$arPrice = array();
		foreach($ar as $key => $arItem){
			if($key == 0) continue;
					
			$id = intval($arItem[$col_id]);
					
			foreach($arColShop as $shop){
				if($arItem[$shop]){
					$name = str_replace(" • ", "_", $arItem[$shop]);

					if($name == "TEMPUS - Наручные часы_FBS"){
						$arPrice[$id] = (float) $arItem[$shop - 1];
					}
				}
			}
					
		}
		return $arPrice;
	}
	
	function parse(){
		global $DB;
		
		if(!file_exists($this->path)){
			$arLog = array(
				"event" => "E",
				"text" => "Yandex. файл не существует",
				"detail" => array("file" => $this->path),
			);
			CLog::add2log($arLog);
			CProSet::setOption("YANDEX_PARSE_ALL", "ERROR_2");				
			
			$this->TsTriggers->SetError(["Парсер yandex. Файл {$this->path} не существует"]);
			$this->TsTriggers->SendTriggerErrors();
			exit();
		}

		$this->clear();
		$arArticle = $this->getArticles();
		$arPriceCatalog = $this->getPrices();
		
		$result["status"] = "N";

		try{

			// Файл xlsx
			$xls = PHPExcel_IOFactory::load($this->path);
			// Первый лист
			$xls->setActiveSheetIndex(0);
			$sheet = $xls->getActiveSheet();
			$ar = array();
			foreach ($sheet->toArray() as $key => $row) {
				if($key <= 3) continue;
				$ar[] = $row;
			}
			file_put_contents("/home/bitrix/logs/CYandexParser/last_file.txt", print_r($ar, true));
			$arResult["YMARKET_SHOP_HIDE"] = json_decode(CProSet::getOption("YMARKET_HIDE_SHOP"), true);
			
			if(count($ar) > 0){

				$col_id = $col_price = $col_price_min = $col_price2 = $col_url = $col_name = $col_last_price = false;
				$arColShop = array();
					
				foreach ($ar[0] as $key => $row){
					if(preg_match('/^SKU/',$row)) $col_id = $key;

					if($row == "Товар на Маркете") $col_name = $key;

					if(preg_match('/^Магазин с мин ценой/',$row)) $arColShop[] = $key;
					if(preg_match('/^Магазин с ценой /',$row)) $arColShop[] = $key;	
				}
				$arPriceTempus = array();
				//prent($ar[0]);prent($arColShop);die;
				//$arPriceTempus = $this->getPricesUnq($ar, $col_id, $arColShop);
				
				$arResult["ITEMS"] = array();
				foreach($ar as $key => &$arItem){
					if($key == 0) continue;
					
					$id = intval($arItem[$col_id]);
					
					if(strlen($arArticle[$id]) > 0){
						$article = $arArticle[$id];
					}else{
						//prent($arItem);//die;
						continue;
					}

					$min_price = false;
					$arPrice = $arPriceAll = array();
					
					foreach($arColShop as $shop){
						if($arItem[$shop]){
							$keyPrice = $shop - 1;
							if($shop == 10) $keyPrice = 5;
							//$name = iconv("windows-1251", "UTF-8", $arItem[$shop]);
							$name = str_replace(" • ", "_", $arItem[$shop]);
							$arResult["SHOPS"][$name] = $name;
							
							/* 1) В отчете яндекс маркета все магазины, которые работают по fbs выводятся как магазин "Яндекс.Маркет". Часто за этим названием мы, но бывает иначе. Нужно пропускать цену магазина "Яндекс.Маркет", Если она равна нашей цене. А если не равна, записывать как любой другой магазин. */
							$break = false;
							if($arPriceCatalog[$id] == (float)$arItem[$keyPrice]){
							//	$break = true;
							}
							
							//ищем минимальную цену с учетом магазинов которые надо исключить
							if(!in_array($name, $arResult["YMARKET_SHOP_HIDE"])){
								if(preg_match('/^STAVIATOR/',$name) && strpos($arItem[$col_name], 'Casio') !== false){
									$arItem[$keyPrice] = $arItem[$keyPrice] - ($arItem[$keyPrice] * 20 / 100);
								}
								
								if($break === false){
									$arPrice[$arItem[$keyPrice]] = (float) $arItem[$keyPrice];
								}
								
								$arPriceAll[$arItem[$keyPrice]] = (float) $arItem[$keyPrice];
							}
							
							if(preg_match('/^TEMPUS/', $name)){
								$arPriceTempus[$id] = (float) $arItem[$keyPrice];
							}
						}
					}
//if($id == 4117){
//	prent($arPriceAll);prent($arPrice);die;
//}
					$arPrice = array_diff($arPrice, array(''));
					$arPriceAll = array_diff($arPriceAll, array(''));
					
					if(count($arPrice) <= 0 && count($arPriceAll) > 0 && $arPriceTempus[$id] > 0){
						asort($arPriceAll);
						$arPrice = array_values($arPriceAll);
					}
					//if($id == "4117"){
					//	prent($arPrice,0,1);die;
					//}
					if(count($arPrice) > 0){
						asort($arPrice);
						$arPrice = array_values($arPrice);
						$arResult["ITEMS"][] = array(
							"ARTICLE" => $article,
							"BITRIX_ID" => $id,
							//"YANDEX_ID" => $yandex_id,
							//"MIN_PRICE" => $min_price,
							"MIN_PRICE" => $arPrice[0],
							"MIN_PRICE2" => ($arPrice[1] ? $arPrice[1] : ""),
							"MIN_PRICE3" => ($arPrice[2] ? $arPrice[2] : ""),
						);
					}else{
						//prent($arItem);
					}

					
				}
				unset($arItem);
				//prent($arResult["SHOPS"]);die;
				//die;
				file_put_contents("/home/bitrix/logs/CYandexParser/lastITEMS_file.txt", print_r($arResult["ITEMS"], true));
				//prent($arResult["ITEMS"]);
				if(count($arResult["SHOPS"]) > 0){
					//пишем в базу
					$this->setShops($arResult["SHOPS"]);
				}
				
				//prent($arResult["ITEMS"]);die;
				if(count($arResult["ITEMS"]) > 0){
					//пишем в базу
					$this->setPrices($arResult["ITEMS"]);
					
					$strSql = "SELECT COUNT(*) as cnt FROM ci_yandex_price";
					$results = $DB->Query($strSql, false, $err_mess.__LINE__);
					if ($row = $results->Fetch()){
						$result["status"] = "Y";
						$result["count"] = $row["cnt"];
						CProSet::setOption("PARSE_CATALOG_YANDEX", $row["cnt"]);
						
						CProSet::setOption("YANDEX_LAST_FILE", basename($this->path));

						//$this->TsTriggers->SetError(["Парсер yandex. Успешно завершен. " . $result["count"] . " товаров"]);
						//$this->TsTriggers->SendTriggerErrors();
		
					}else{
						$this->TsTriggers->SetError(["Парсер yandex. Ни один товар не записан"]);
						$this->TsTriggers->SendTriggerErrors();
		
					}
				}else{
					$result["error"] = "Количество 0";
					CProSet::setOption("YANDEX_PARSE_ALL", "ERROR_5");
					$this->TsTriggers->SetError(["Парсер yandex. Файл {$this->path} Количество 0"]);
					$this->TsTriggers->SendTriggerErrors();
				}
				
			}else{
				$result["error"] = "Файл пустой";
				CProSet::setOption("YANDEX_PARSE_ALL", "ERROR_3");
				
				$this->TsTriggers->SetError(["Парсер yandex. Файл https://tempusshop.ru/upload/partner_yandex/" . basename($this->path) . " пустой"]);
				$this->TsTriggers->SendTriggerErrors();
			}
		}catch (Exception $E){
			$result["error"] = $E->getMessage();
			CProSet::setOption("YANDEX_PARSE_ALL", "ERROR_4");
			
			$this->TsTriggers->SetError(["Парсер yandex. " . $E->getMessage()]);
			$this->TsTriggers->SendTriggerErrors();
		}

		return $result;

	}
	function setShops($arItems = array()){
		global $DB;
		foreach($arItems as $shop){
			$in = array(
				"NAME" => "'".addslashes($shop)."'",
			);
			$DB->Insert("ci_yandex_shop", $in, $err_mess.__LINE__);
		}
	}
	function setPrices($arItems = array()){
		global $DB;
		foreach($arItems as $arItem){
			$in = array(
				"name" => "'".addslashes($arItem["ARTICLE"])."'",
				"bitrix_id" => "'".addslashes($arItem["BITRIX_ID"])."'",
				//"yandex_id" => "'".addslashes($arItem["YANDEX_ID"])."'",
				"minPrice" => "'".$arItem["MIN_PRICE"]."'",
				"minPrice2" => "'".$arItem["MIN_PRICE2"]."'",
				"minPrice3" => "'".$arItem["MIN_PRICE3"]."'",
				"type_price" => "'".addslashes("PARTNER_FILE")."'",
			);
			$DB->Insert("ci_yandex_price", $in, $err_mess.__LINE__);
		}
	}
	
}

?>