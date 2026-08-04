<?
class CYandexParser{
	public $path = "";
	public $v = "";
	function __construct(){

		$this->path = "/var/www/bitrix/data/www/tempusshop.ru/upload/yandex.csv";
		//$this->source = "https://tempusshop:123456@pricelabs.yandex.ru/export/tempusshop-market@yandex.ru/tempusshop.ru/prices.csv";

		$this->source = COption::GetOptionString("panel.manager", "YANDEX_PATH_PARSER");
		//подключаем класс для работы с csv
		require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/classes/csv.class.php');
	}
	function getArticles(){
		global $DB;
		/*$strSql = "SELECT el.ID as ID, pr.VALUE as ARTICLE FROM b_iblock_element el LEFT JOIN b_iblock_element_property pr 
				ON el.ID=pr.IBLOCK_ELEMENT_ID WHERE 
				el.ACTIVE = 'Y' 
				AND el.IBLOCK_ID IN ('16','17') AND pr.IBLOCK_PROPERTY_ID IN ('121','123') AND pr.VALUE <> ''";*/
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
	function clear(){
		global $DB;
		$DB->Query("TRUNCATE TABLE ci_yandex_shop", false, $err_mess.__LINE__);
		$DB->Query("TRUNCATE TABLE ci_yandex_price", false, $err_mess.__LINE__);
		CProSet::setOption("PARSE_CATALOG_YANDEX", 0);
		unlink($this->path);
		
		$DB->Query("TRUNCATE TABLE ci_marketparser_id", false, $err_mess.__LINE__);
	}
	function parse(){
		global $DB;
		$this->clear();
		$arArticle = $this->getArticles();
		//AddMessage2Log($arArticle);
		$result["status"] = "N";
		if(copy($this->source, $this->path)){
		//if(true){

			$csv = new CSV($this->path);
			
			$arResult["YMARKET_SHOP_HIDE"] = json_decode(CProSet::getOption("YMARKET_HIDE_SHOP"), true);
			
			if($csv->error != "no_found"){

				$arCsv = array();
				$get_csv = $csv->getCSV();
				foreach ($get_csv as $key => $value){ //Проходим по строкам
					$arCsv[] = $value;
				}
				
				$col_id = $col_price = $col_price_min = $col_price2 = $col_url = $col_name = $col_last_price = false;
				$arColShop = array();
				
				$arMarketParser = array();
				
				foreach ($arCsv[0] as $key => $row){
					if(preg_match('/^ID/',$row)) $col_id = $key;
					//if($row == "PriceMin") $col_price_min = $key;
					//if($row == "Price") $col_price = $key;
					//if($row == "Price2") $col_price2 = $key;
					if($row == "CardUrl") $col_url = $key;
					
					if($row == "Name") $col_name = $key;

					if(preg_match('/^Competitor/',$row)) $arColShop[] = $key;
					
					if($row == "Price10") $col_last_price = $key;
				}

				$arResult["ITEMS"] = array();
				foreach($arCsv as $key => &$arItem){
					if($key == 0) continue;
					
					$id = intval($arItem[$col_id]);
					//$price = (float) $arItem[$col_price];
					//$price_min = (float) $arItem[$col_price_min];
					//$price2 = (float) $arItem[$col_price2];
					
					//смотрим последнюю цену, чтобы парсить повторно через cp.marketparser.ru. тут только 10 первых цен
					$last_price = (float) $arItem[$col_last_price];
					
					if($last_price > 0 || !$arItem[$col_url]) 
						$arMarketParser[] = $id;
					
					$yandex_id = "";
					if($arItem[$col_url]){
						$tmp = intval(basename($arItem[$col_url]));
						if($tmp > 0){
							$yandex_id = $tmp;
						}
					}
					
					//if($price == $price_min) $price_new = $price2; else $price_new = $price_min;
					//Price = PriceMin то Price2 иначе PriceMin
					if(strlen($arArticle[$id]) > 0){
						$article = $arArticle[$id];
					}else{
						//prent($arItem);//die;
						continue;
					}
					
					$min_price = false;
					$arPrice = array();
					
					foreach($arColShop as $shop){
						if($arItem[$shop]){
							$name = iconv("windows-1251", "UTF-8", $arItem[$shop]);
							$arResult["SHOPS"][$name] = $name;
							
							//ищем минимальную цену с учетом магазинов которые надо исключить
							if(!in_array($name, $arResult["YMARKET_SHOP_HIDE"])){
								
								/*if(preg_match('/^STAVIATOR/',$name) && strpos($arItem[$col_name], 'Casio') !== false){
									$arItem[$shop + 1] = $arItem[$shop + 1] - ($arItem[$shop + 1] * 20 / 100);
									//AddMessage2Log($arItem[$col_name]);die;
								}
								
								if(preg_match('/^НАРУЧКА - Часы и Аксессуары/',$name)){
									$arItem[$shop + 1] = $arItem[$shop + 1] - ($arItem[$shop + 1] * 5 / 100);
								}*/
								
								if($arItem[0] == "1002"){
								//	AddMessage2Log($name);
								//	AddMessage2Log($arItem[$shop + 1]);
								}
								
								if(preg_match('/^Generalwatches/', $name)){
									$info = "Generalwatches";
								}else{ 
									$info = false;
								}
						
								$arPrice[$arItem[$shop + 1]] = (float) $arItem[$shop + 1];

							}
						}
					}
					
					/*
					if(count($arPrice) > 1){
						asort($arPrice);
						array_shift($arPrice);
						$arPrice = array_values($arPrice);
						//AddMessage2Log($arPrice);
					}
					
					if($min_price){*/
					$arPrice = array_diff($arPrice, array(''));
					if(count($arPrice) > 0){
						asort($arPrice);
						$arPrice = array_values($arPrice);
						$arResult["ITEMS"][] = array(
							"ARTICLE" => $article,
							"BITRIX_ID" => $id,
							"YANDEX_ID" => $yandex_id,
							//"MIN_PRICE" => $min_price,
							"MIN_PRICE" => $arPrice[0],
							"MIN_PRICE2" => ($arPrice[1] ? $arPrice[1] : ""),
							"MIN_PRICE3" => ($arPrice[2] ? $arPrice[2] : ""),
							"INFO" => $info,
						);
					}else{
						//prent($arItem);
					}

					
				}
				unset($arItem);
				//prent($arResult["ITEMS"]);die;
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
						CProSet::setOption("PARSE_CATALOG_YANDEX", $row["cnt"]);
					}
				}else{
					$result["error"] = "Количество 0";
				}

				$this->setMarketParser($arMarketParser);
				
			}else{
				$result["error"] = "Файл не найден";
			}
		}else{
			$result["error"] = "Файл не удалось скопировать";
		}
		//prent($result);
		/*$res = array(
			'status' => ($result["status"] == "Y" ? "ok" : "error"),
			'text' => ($result["status"] == "Y" ? "Выгрузка прошла успешно" : "Не удалось выгрузить")
		);*/
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
				"yandex_id" => "'".addslashes($arItem["YANDEX_ID"])."'",
				"minPrice" => "'".$arItem["MIN_PRICE"]."'",
				"minPrice2" => "'".$arItem["MIN_PRICE2"]."'",
				"minPrice3" => "'".$arItem["MIN_PRICE3"]."'",
				"type_price" => "'".addslashes("MARKET_FILE")."'",
				"info" => "'".$arItem["INFO"]."'",
			);
			$DB->Insert("ci_yandex_price", $in, $err_mess.__LINE__);
		}
	}
	
	function setMarketParser($arItems = array()){
		global $DB;
		
		foreach($arItems as $bitrix_id){
			$in = array(
				"BITRIX_ID" => "'".intval($bitrix_id)."'",
			);
			$DB->Insert("ci_marketparser_id", $in, $err_mess.__LINE__);
		}
	}
	
}

?>