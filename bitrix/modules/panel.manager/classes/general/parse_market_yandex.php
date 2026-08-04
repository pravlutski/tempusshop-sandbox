<?
class YaMarketParser{
	public $filename_gz = "";
	public $file_orig = "";
	public $file_ex = "";

	function __construct(){
	
		$this->filename_gz = $_SERVER['DOCUMENT_ROOT'] . "/upload/onliner_competitors_prices.csv.gz";
		$this->file_orig = $_SERVER['DOCUMENT_ROOT'] . "/upload/onliner_competitors_prices.csv";
		
		$this->filename_gz_tmp = $_SERVER['DOCUMENT_ROOT'] . "/upload/tmp/onliner/onliner_competitors_prices_" . date("Y-m-d H:i:s") . ".csv.gz";
		$this->file_orig_tmp = $_SERVER['DOCUMENT_ROOT'] . "/upload/tmp/onliner/onliner_competitors_prices_" . date("Y-m-d H:i:s") . ".csv";

//		unlink($this->file_orig);
//		unlink($this->filename_gz);
		
		putenv("PHANTOMJS_EXECUTABLE=/usr/local/bin/phantomjs");
		shell_exec("/usr/local/bin/casperjs --web-security=no --fail-fast /userscripts/js/parser_yandex.js >> /userscripts/logs/yandex/parser_yandex_" . date("Y-m-d") . ".txt");

//		sleep(30);

//		chmod($this->filename_gz, 0777);
		//chmod($this->file_ex, 0777);
		//unlink($this->file_orig);

		//распаковываем unpack
		//exec("gunzip {$this->filename_gz}"); 
//		if (file_exists($this->filename_gz))
//			exec("gunzip -c {$this->filename_gz} > {$this->file_orig}"); 
		//die;
//		require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/classes/csv.class.php');
	}
	
	function parse(){
		global $DB;
		$this->clear();

		//массив разделов которые нам нужны
		$arSection = array("Наручные часы");
		$arArticle = $this->getArticles();
		//prent($arArticle);
		

		$arResult["ONLINER_HIDE_SHOP"] = json_decode(CProSet::getOption("ONLINER_HIDE_SHOP"), true);
		
		if (file_exists($this->file_orig)){
			$csv = new CSV($this->file_orig);
			if($csv->error != "no_found"){

				$arCsv = array();
				$get_csv = $csv->getCSV();
				foreach ($get_csv as $key => $value){ //Проходим по строкам
					$arCsv[] = $value;
				}
					/*
					Раздел;Производитель;"ID товара";Название;"Ссылка на товар";
					"Цена 1";"Цена 2";"Цена 3";"Мин. цена";"Макс. цена";"Ср. цена";
					"Магазин 1";"Цена 1";"Цена доставки 1";"Срок доставки 1";
					"Магазин 2";"Цена 2";"Цена доставки 2";"Срок доставки 2";
					"Магазин 3";"Цена 3";"Цена доставки 3";"Срок доставки 3";
					*/
					
				$col_id = $col_price = $col_price_min = $col_price2 = $col_url = false;
				$arColShop = array();
				foreach ($arCsv[0] as $key => $row){
					if(preg_match('/^ID товара/', iconv("windows-1251", "UTF-8", $row))) $col_id = $key;
					if(iconv("windows-1251", "UTF-8", $row) == "Мин. цена") $col_price_min = $key;
					//if($row == "Price") $col_price = $key;
					$col_price = 5;
						
					if(iconv("windows-1251", "UTF-8", $row) == "Ссылка на товар") $col_url = $key;
						
					if(iconv("windows-1251", "UTF-8", $row) == "Раздел") $col_section = $key;
					if(iconv("windows-1251", "UTF-8", $row) == "Производитель") $col_brand = $key;
					if(iconv("windows-1251", "UTF-8", $row) == "Название") $col_name = $key;
						
					if(preg_match('/^Магазин /', iconv("windows-1251", "UTF-8", $row))) $arColShop[] = $key;
				}

				$arResult["ITEMS"] = array();
				foreach($arCsv as $key => $arItem){
					if($key == 0) continue;
					$id = intval($arItem[$col_id]);
					$price = (float) $arItem[$col_price];
					$price_min = (float) $arItem[$col_price_min];
						
					if(strlen($arArticle[$arItem[$col_name]]) > 0){
						$article = $arArticle[$arItem[$col_name]];
					}else{
						$article = $arItem[$col_name];
						//continue;
					}
					
					$min_price = false;
					$arPrice = array();
					
					foreach($arColShop as $shop){
						if($arItem[$shop]){
							$name = iconv("windows-1251", "UTF-8", $arItem[$shop]);
							$name = str_replace(array("'",'"'), "", $name);

							$arResult["SHOPS"][$name] = $name;
							
							//ищем минимальную цену с учетом магазинов которые надо исключить
							if(!in_array($name, $arResult["ONLINER_HIDE_SHOP"])){
								/*if($min_price === false){
									$min_price = (float)$arItem[$shop + 1];
								}elseif((float)$arItem[$shop + 1] < $min_price){
									$min_price = (float)$arItem[$shop + 1];
									if($id == "1523145"){
										AddMessage2Log($name);
										AddMessage2Log($min_price);
										AddMessage2Log($arItem[$shop + 1]);
									}
								}*/
								
								$arPrice[$arItem[$shop + 1]] = (float) $arItem[$shop + 1];
							}
						}
					}
					
					if(count($arPrice) > 0){
						asort($arPrice);
						$arPrice = array_values($arPrice);
						$arResult["ITEMS"][$id] = array(
							"SECTION" => iconv("windows-1251", "UTF-8", $arItem[$col_section]),
							"VENDOR" => iconv("windows-1251", "UTF-8", $arItem[$col_brand]),
							"MODEL" => $article,
							"LINK_ONLINER" => $arItem[$col_url],
							"ONLINER_ID" => $id,
							"MIN_PRICE" => $arPrice[0],
							"MIN_PRICE2" => ($arPrice[1] ? $arPrice[1] : ""),
							"MIN_PRICE3" => ($arPrice[2] ? $arPrice[2] : ""),
							"SHOP_PRICE" => $price,
						);
					}else{
						//prent($arItem);
					}
					/*
					if($min_price){
						$arResult["ITEMS"][] = array(
							"SECTION" => iconv("windows-1251", "UTF-8", $arItem[$col_section]),
							"VENDOR" => iconv("windows-1251", "UTF-8", $arItem[$col_brand]),
							"MODEL" => $article,
							"LINK_ONLINER" => $arItem[$col_url],
							"ONLINER_ID" => $id,
							"MIN_PRICE" => $min_price,
							"SHOP_PRICE" => $price,
						);
					}else{
						//prent($arItem);
					}*/

						
				}
					
				//prent($arResult["ITEMS"]);die;
				if(count($arResult["SHOPS"]) > 0){
					//пишем в базу
					$this->setShops($arResult["SHOPS"]);
				}
				//prent($arResult["ITEMS"]);die;
				//prent($arResult["ITEMS"]);die;
				if(count($arResult["ITEMS"]) > 0){
					//пишем в базу
					$this->setPrices($arResult["ITEMS"]);
						
					$strSql = "SELECT COUNT(*) as cnt FROM ci_catalog_onliner";
					$results = $DB->Query($strSql, false, $err_mess.__LINE__);
					if ($row = $results->Fetch()){
						$result["status"] = "Y";
						CProSet::setOption("PARSE_CATALOG_ONLINER", $row["cnt"]);
						
						$html .= "<p>Добавлено {$row["cnt"]}. Всех товаров - " . (count($arCsv) - 1) . "</p>";
					}
				}else{
					$html .= "<p>Количество 0</p>";
				}

			}else{
				$html .= "<p>Файл сломан</p>";
				copy($this->filename_gz, $this->filename_gz_tmp);
				copy($this->file_orig, $this->file_orig_tmp);
				$html .= "<p><a href='".str_replace($_SERVER['DOCUMENT_ROOT'], "https://tempusshop.ru", $this->filename_gz_tmp)."'>Оригинал файла скачанный из б2б онлайнера</a></p>";
				$html .= "<p><a href='".str_replace($_SERVER['DOCUMENT_ROOT'], "https://tempusshop.ru", $this->file_orig_tmp)."'>Распакованный файл из архива</a></p>";
			}
		}else{
			$html .= "<p>Архив не загружен</p>";
		}
		if(count($arCsv) == 0){
			$message = "<p>Парсер онлайнера сломан</p>";
			$message .= $html;
			$arFields = array(
				"TO_EMAIL" => "sales@tempus.by",
				"SUBJECT" => "Парсер онлайнера сломан",
				"MESSAGE" => $message,
			);
			
			CEvent::SendImmediate("NEW_FORUM_PRIV", array("s2"), $arFields, "N", 364);
		}
		CLog::add2log(array("event" => "C", "text" => $html));
		return $result;
	}
	function getArticles(){
		global $DB;
		
		$strSql = "SELECT article, name FROM ci_onliner_articles";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arArticle[$row["name"]] = $row["article"];
		}
		/*
		//выбираем артикулы онлайнера и подменяем на свои . на онлайнере левые
		$objRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, '!PROPERTY_CML2_ARTICLE' => false, '!PROPERTY_MODEL_ONLINER' => false), false, false, array('ID', "PROPERTY_CML2_ARTICLE", "PROPERTY_MODEL_ONLINER"));
		while ($res = $objRes->GetNext()){
			if($res["PROPERTY_MODEL_ONLINER_VALUE"] != $res["PROPERTY_CML2_ARTICLE_VALUE"])
				$arArticle[$res["PROPERTY_MODEL_ONLINER_VALUE"]] = $res["PROPERTY_CML2_ARTICLE_VALUE"];
		}*/
		return $arArticle;
	}
	function clear(){
		global $DB;
		$DB->Query("TRUNCATE TABLE ci_onliner_shop", false, $err_mess.__LINE__);
		$DB->Query("TRUNCATE TABLE ci_catalog_onliner", false, $err_mess.__LINE__);
		CProSet::setOption("PARSE_CATALOG_ONLINER", 0);
	}

	function setShops($arItems = array()){
		global $DB;
		foreach($arItems as $shop){
			$in = array(
				"NAME" => "'".addslashes($shop)."'",
			);
			$DB->Insert("ci_onliner_shop", $in, $err_mess.__LINE__);
		}
	}
	function setPrices($arItems = array()){
		global $DB;
		foreach($arItems as $arItem){
			$in = array(
				"id" => "'".addslashes($arItem["ONLINER_ID"])."'",
				"section" => "'".addslashes($arItem["SECTION"])."'",
				"brand" => "'".addslashes($arItem["VENDOR"])."'",
				"model" => "'".addslashes($arItem["MODEL"])."'",
				"url" => "'".addslashes($arItem["LINK_ONLINER"])."'",
				"min_price" => "'".$arItem["MIN_PRICE"]."'",
				"min_price2" => "'".$arItem["MIN_PRICE2"]."'",
				"min_price3" => "'".$arItem["MIN_PRICE3"]."'",
				"shop_price" => "'".$arItem["SHOP_PRICE"]."'"
			);
			//prent($arItem);die;
			$DB->Insert("ci_catalog_onliner", $in, $err_mess.__LINE__);
		}
	}
}

?>