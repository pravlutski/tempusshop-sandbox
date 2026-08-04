<?
class CYandexParserURI{
	public static $allCnt;
	public static $arYandex;
	
	function __construct(){

	}

	function parse($ID = 0){
		global $DB;
		
		$arIDs = array();
		$arResult = array();
		
		$ID = intval($ID);
		
		if($ID == 0 || CProSet::getOption("PARSE_YANDEX_URI") == "Y") {
			//self::getAllCnt();
			self::getCeneoIDs();
			self::$allCnt = count(self::$arYandex);
			CProSet::setOption("PARSE_YANDEX_URI", "IN_PROCESS");
		}
		if(CProSet::getOption("PARSE_YANDEX_URI") == "N") return false;
		
		
		
		$limit = 1;
		
		$strSql = "SELECT bitrix_id, yandex_id FROM ci_yandex_link WHERE bitrix_id > {$ID} LIMIT 0,{$limit}";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arIDs[$row["bitrix_id"]] = $row["yandex_id"];
			$lastID = intval($row["bitrix_id"]);
		}
		
		//создаем сразу все файлы yandex.
		$ar = array();
		foreach($arIDs as $bitrix_id => $yandex_id){
			//если файл есть и он не старше 3600 то не парсим	
			$filename = "/var/www/bitrix/data/www/tempusshop.ru/upload/tmp_yandex/{$yandex_id}.txt";
			if (file_exists($filename) && filesize($filename) > 10000) {
				$filediff = time() - filectime($filename);
				if($filediff > 3600){
					$ar[] = $yandex_id;
				}
			}else{
				$ar[] = $yandex_id;
			}
		}

		if(count($ar) > 0){
			$this->createAllfile2Parse($ar);
		}
		
		die;
		

		
		foreach($arArticle as $bitrix_id => $article){
			$ceneo_id = self::$arCeneo[$bitrix_id];
			$result_file = "/var/www/bitrix/data/www/tempusshop.ru/upload/tmp_yandex/{$ceneo_id}.txt";
			if($ceneo_id > 0 && file_exists($result_file)){

				//if ($result_file && file_exists($result_file)){
					$html = $this->gzdecode($result_file);
					$saw = new CNokogiri($html);
					
					$ar = $saw->get('.product-offers-group table.product-offers tr.product-offer')->toArray();
					
					$arTitle = $saw->get('.product-offers-group .title-limit strong')->toArray();
					if($arTitle[0]["#text"][0] == "Podobne oferty") continue;
					
					$arPrice = array();
					foreach($ar as $key => $arItem){
						if($arItem["data-shopurl"] && $arItem["data-price"]){
							$arPrice[] = array(
								"SHOP" => $arItem["data-shopurl"],
								"PRICE" => $arItem["data-price"],
							);
						}else{
							//CLog::add2log(array("event" => "PC", "text" => "CENEO_ID - {$ceneo_id} не найден  базе"));
						}

					}
					
					if(count($arPrice) > 0){
						$min_price = false;
						foreach($arPrice as $k => $arOffer){
							$name = $arOffer["SHOP"];
							$arResult["SHOPS"][$name] = $name;
										
							//ищем минимальную цену с учетом магазинов которые надо исключить
							if(!in_array($name, $arResult["CENEO_SHOP_HIDE"])){
								if(!$min_price){
									$min_price = $arOffer["PRICE"];
								}elseif($min_price && $arOffer["PRICE"] < $min_price){
									$min_price =$arOffer["PRICE"];
								}
							}
						}
						
						if($min_price){
							$arResult["ITEMS"][] = array(
								"ARTICLE" => $article,
								"BITRIX_ID" => $bitrix_id,
								"CENEO_ID" => $ceneo_id,
								"MIN_PRICE" => $min_price,
							);
						}
					}
				
				//}else{
					//файл $ceneo_id не создан
				//	CLog::add2log(array("event" => "PC", "text" => "Файл {$ceneo_id} не создан"));
				//}
				
			//}else{
				//ceneo id не найден
			//	CLog::add2log(array("event" => "PC", "text" => "{$article} - не найден CENEO_ID в базе."));
			}
		}
		
		if(count($arResult["ITEMS"]) > 0){
			//пишем в базу
			$this->setReviews($arResult["ITEMS"]);
					
		}else{
		//	$result["ERROR"][] = "Количество 0";
		}
			
		$arFilter = Array(
			"IBLOCK_ID" => 16,
			"ACTIVE" => "Y", 
			">CATALOG_PRICE_3" => 0,
			"=PROPERTY_SITE_ID" => "s3",
			">CATALOG_QUANTITY" => "0",
			"<=ID" => $lastID
		);
		$rsLeftBorder = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter);
		$leftBorderCnt = $rsLeftBorder->SelectedRowsCount();

		$p = round(100 * $leftBorderCnt / self::$allCnt, 2);
		if($p > 100) $p = 100;
	
		CProSet::setOption("PARSE_YANDEX_URI_PER", $p);
		CProSet::setOption("PARSE_YANDEX_URI_LAST_ID", $lastID);
		if($p >= 100){
			//ставим метку если успешно обновлено
			CProSet::setOption("PARSE_YANDEX_URI", "N");
		}
		//return array("PERCENT" => $p, "LAST_ID" => $lastID);
		$result["PERCENT"] = $p;
		$result["LAST_ID"] = $lastID;

		return $result;

	}

	function createAllfile2Parse($arYandex){
		putenv("PHANTOMJS_EXECUTABLE=/usr/local/bin/phantomjs");
		shell_exec("/usr/local/bin/casperjs --web-security=no --fail-fast /userscripts/js/parse_all_yandex.js " . implode(",", $arYandex));// >> /userscripts/logs/ceneo.txt
	}
	
	function setPrices($arItems = array()){
		global $DB;
		foreach($arItems as $arItem){
			$in = array(
				"name" => "'".addslashes($arItem["ARTICLE"])."'",
				"bitrix_id" => "'".addslashes($arItem["BITRIX_ID"])."'",
				"ceneo_id" => "'".addslashes($arItem["CENEO_ID"])."'",
				"minPrice" => "'".$arItem["MIN_PRICE"]."'",
			);
			$DB->Insert("ci_ceneo_price", $in, $err_mess.__LINE__);
		}
	}
	static private function getAllCnt(){
		global $DB;
		$strSql = "SELECT COUNT(*) as cnt FROM ci_yandex_link";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			self::$allCnt = $row["cnt"];
		}
	}
	
	static public function getCeneoIDs() {
		global $DB;
		$strSql = "SELECT bitrix_id, yandex_id FROM ci_yandex_link";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			self::$arYandex[$row["bitrix_id"]] = $row["yandex_id"];
		}
		
	}
	function gzdecode($file){
		ob_start();
		readgzfile($file);
		$d = ob_get_clean();
		return $d;
	}
	
}
?>