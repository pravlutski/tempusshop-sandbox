<?
class CCeneoParserURI{
	public static $allCnt;
	public static $arCeneo;
	
	function __construct(){

	}

	function clear($arIDs){
		global $DB;
		$DB->Query("DELETE FROM ci_ceneo_price WHERE bitrix_id IN ('" . implode("','", $arIDs)."')", false, $err_mess.__LINE__);
	}
	function clearAll(){
		global $DB;
		$DB->Query("TRUNCATE TABLE ci_ceneo_price", false, $err_mess.__LINE__);
		//$DB->Query("DELETE FROM ci_ceneo_price WHERE type_price = 'CENEO_URL'", false, $err_mess.__LINE__);
	}
	
	function parse($ID = 0){
		global $DB;
		
		$arArticle = array();
		$arResult = array();
		$el = new CIBlockElement;
		
		$ID = intval($ID);
		
		if($ID == 0 || CProSet::getOption("PARSE_CENEO_URI") == "Y") {
			self::getAllCnt();
			self::getCeneoIDs();
			CProSet::setOption("PARSE_CENEO_URI", "IN_PROCESS");
			
			$this->clearAll();
			
		}
		if(CProSet::getOption("PARSE_CENEO_URI") == "N") return false;
		
		$limit = 100;
		$arSelect = Array("ID", "PROPERTY_CML2_ARTICLE");
		
		$arFilter = Array(
			"IBLOCK_ID" => 16,
			"ACTIVE" => "Y", 
			">CATALOG_PRICE_3" => 0,
//			"=PROPERTY_SITE_ID" => "s3",
			">CATALOG_QUANTITY" => "0",
			">ID" => $ID,
//			"ID" => 80108,
		);
		$rsEl = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, array("nTopCount" => $limit), $arSelect);
		while($arFields = $rsEl->GetNext()){
			$arArticle[$arFields["ID"]] = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
			$lastID = intval($arFields["ID"]);
		}
		
//		$this->clear(array_keys($arArticle));
		
		$arResult["CENEO_SHOP_HIDE"] = json_decode(CProSet::getOption("CENEO_HIDE_SHOP"), true);
		
		//создаем сразу все файлы ceneo. может так быстрее будет
		$ar = array();
		foreach($arArticle as $bitrix_id => $article){
			if(isset(self::$arCeneo[$bitrix_id])){
				$ceneo_id = self::$arCeneo[$bitrix_id];
				//если файл есть и он не старше 3600 то не парсим	
				$filename = "/var/www/bitrix/data/www/tempusshop.ru/upload/tmp_ceneo/{$ceneo_id}.txt";
				if (file_exists($filename) && filesize($filename) > 10000) {
					$filediff = time() - filectime($filename);
					//3600 * 12 = 43200 
					//12 часов
					if($filediff > 43200){
						$ar[] = $ceneo_id;
					}
				}else{
					$ar[] = $ceneo_id;
				}
			}
		}
		//prent(self::$arCeneo);
		if(count($ar) > 0){
			$this->createAllfile2Parse($ar);
		}
		
		foreach($arArticle as $bitrix_id => $article){
			$ceneo_id = self::$arCeneo[$bitrix_id];
			$result_file = "/var/www/bitrix/data/www/tempusshop.ru/upload/tmp_ceneo/{$ceneo_id}.txt";
			if($ceneo_id > 0 && file_exists($result_file)){

				//if ($result_file && file_exists($result_file)){
					$html = $this->gzdecode($result_file);
					$saw = new CNokogiri($html);
					
					//$ar = $saw->get('.product-offers-group table.product-offers tr.product-offer')->toArray();
					//$ar = $saw->get('.product-offer-2020__container')->toArray();
					$ar = $saw->get('.product-offer__container')->toArray();
					
					//prent($ar);
					//$arTitle = $saw->get('.product-offers-group .title-limit strong')->toArray();
					$arTitle = $saw->get('.title-limit strong')->toArray();//prent($arTitle);

					if($arTitle[0]["#text"][0] == "Podobne oferty") continue;
					
					
					//prent($ar);
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
						$arMinPrice = array();
						foreach($arPrice as $k => $arOffer){
							$name = $arOffer["SHOP"];
							$arResult["SHOPS"][$name] = $name;
										
							//ищем минимальную цену с учетом магазинов которые надо исключить
							if(!in_array($name, $arResult["CENEO_SHOP_HIDE"])){
								if(!$min_price){
									$min_price = $arOffer["PRICE"];
								}elseif($min_price && $arOffer["PRICE"] < $min_price){
									$min_price = $arOffer["PRICE"];
								}
								
								$arMinPrice[$arOffer["PRICE"]] = (float) $arOffer["PRICE"];
							}
						}
						//prent($arMinPrice);
						if(count($arMinPrice) > 0){
							asort($arMinPrice);
							$arMinPrice = array_values($arMinPrice);
							$arResult["ITEMS"][] = array(
								"ARTICLE" => $article,
								"BITRIX_ID" => $bitrix_id,
								"CENEO_ID" => $ceneo_id,
								"MIN_PRICE" => $arMinPrice[0],
								"MIN_PRICE2" => $arMinPrice[1],
								"MIN_PRICE3" => $arMinPrice[2],
							);
						}
					}
				//prent($arResult["ITEMS"]);die;
				//}else{
					//файл $ceneo_id не создан
				//	CLog::add2log(array("event" => "PC", "text" => "Файл {$ceneo_id} не создан"));
				//}
				
			//}else{
				//ceneo id не найден
			//	CLog::add2log(array("event" => "PC", "text" => "{$article} - не найден CENEO_ID в базе."));
			}
		}
		//prent($arResult);die;
		if(count($arResult["SHOPS"]) > 0){
			//пишем в базу
			$this->setShops($arResult["SHOPS"]);
		}
	
		if(count($arResult["ITEMS"]) > 0){
			//пишем в базу
			$this->setPrices($arResult["ITEMS"]);
					
		}else{
		//	$result["ERROR"][] = "Количество 0";
		}
			
		$arFilter = Array(
			"IBLOCK_ID" => 16,
			"ACTIVE" => "Y", 
			">CATALOG_PRICE_3" => 0,
//			"=PROPERTY_SITE_ID" => "s3",
			">CATALOG_QUANTITY" => "0",
			"<=ID" => $lastID
		);
		$rsLeftBorder = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter);
		$leftBorderCnt = $rsLeftBorder->SelectedRowsCount();

		$p = round(100 * $leftBorderCnt / self::$allCnt, 2);
		if($p > 100) $p = 100;
	
		CProSet::setOption("PARSE_CENEO_URI_PER", $p);// . " - " . memory_get_usage());
		CProSet::setOption("PARSE_CENEO_URI_LAST_ID", $lastID);
		if($p >= 100){
			//ставим метку если успешно обновлено
			CProSet::setOption("PARSE_CENEO_URI", "N");
		}
		//return array("PERCENT" => $p, "LAST_ID" => $lastID);
		$result["PERCENT"] = $p;
		$result["LAST_ID"] = $lastID;

		return $result;

	}
	
	function setShops($arItems = array()){
		global $DB;
		foreach($arItems as $shop){
			if(!$DB->Query("SELECT ID FROM ci_ceneo_shop WHERE NAME = '{$shop}'", false, $err_mess.__LINE__)->Fetch()){
				$in = array(
					"NAME" => "'".addslashes($shop)."'",
				);
				$DB->Insert("ci_ceneo_shop", $in, $err_mess.__LINE__);
			}

		}
	}
	function setLink($arItems = array()){
		global $DB;
		foreach($arItems as $arItem){
			if($arItem["BITRIX_ID"] > 0 && $arItem["CENEO_ID"] > 0){
				if(!$DB->Query("SELECT bitrix_id FROM ci_ceneo_link WHERE bitrix_id = '{$arItem["BITRIX_ID"]}'", false, $err_mess.__LINE__)->Fetch()){
					$in = array(
						"bitrix_id" => "'".addslashes($arItem["BITRIX_ID"])."'",
						"ceneo_id" => "'".addslashes($arItem["CENEO_ID"])."'",
					);
					$DB->Insert("ci_ceneo_link", $in, $err_mess.__LINE__);
				}
			}
		}
	}
	
	function createfile2Parse($ceneo_id){
		putenv("PHANTOMJS_EXECUTABLE=/usr/local/bin/phantomjs");
		shell_exec("/usr/local/bin/casperjs --web-security=no --fail-fast /userscripts/js/parse_ceneo.js {$ceneo_id}");// >> /userscripts/logs/ceneo.txt
		return "/var/www/bitrix/data/www/tempusshop.ru/upload/tmp_ceneo/" . $ceneo_id . ".txt";
	}
	function createAllfile2Parse($arCeneo){
		putenv("PHANTOMJS_EXECUTABLE=/usr/local/bin/phantomjs");
		shell_exec("/usr/local/bin/casperjs --web-security=no --fail-fast /userscripts/js/parse_all_ceneo.js " . implode(",", $arCeneo));// >> /userscripts/logs/ceneo.txt
	}
	
	function setPrices($arItems = array()){
		global $DB;
		foreach($arItems as $arItem){
			$in = array(
				"name" => "'".addslashes($arItem["ARTICLE"])."'",
				"bitrix_id" => "'".addslashes($arItem["BITRIX_ID"])."'",
				"ceneo_id" => "'".addslashes($arItem["CENEO_ID"])."'",
				"minPrice" => "'".$arItem["MIN_PRICE"]."'",
				"minPrice2" => "'".$arItem["MIN_PRICE2"]."'",
				"minPrice3" => "'".$arItem["MIN_PRICE3"]."'",
				"type_price" => "'".addslashes("CENEO_URL")."'",
			);
			//prent($in);die;
			$DB->Insert("ci_ceneo_price", $in, $err_mess.__LINE__);
		}
	}
	static private function getAllCnt(){
		$arFilter = Array(
			"IBLOCK_ID" => 16,
			"ACTIVE" => "Y", 
			">CATALOG_PRICE_3" => 0,
//			"=PROPERTY_SITE_ID" => "s3",
			">CATALOG_QUANTITY" => "0",
		);
		$rsAll = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter);
		self::$allCnt = $rsAll->SelectedRowsCount();
	}
	
	static public function getCeneoIDs() {
		global $DB;
		$strSql = "SELECT * FROM ci_ceneo_link WHERE PARSE = 'Y'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			self::$arCeneo[$row["bitrix_id"]] = $row["ceneo_id"];
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