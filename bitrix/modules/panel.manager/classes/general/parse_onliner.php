<?
class COnlinerParser{
	public $filename_gz = "";
	public $file_orig = "";
	public $file_ex = "";

	function __construct(){

		//putenv("PHANTOMJS_EXECUTABLE=/usr/local/bin/phantomjs");
		$this->logger = new TsLogger("/onlineParse/");
		$this->TsTriggers = new TsTriggers();
		require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/classes/csv.class.php');
	}

	function parse(){
		$this->filename_gz = $_SERVER['DOCUMENT_ROOT'] . "/upload/onliner_competitors_prices.csv.gz";
		$this->file_orig = $_SERVER['DOCUMENT_ROOT'] . "/upload/onliner_competitors_prices.csv";


		unlink($this->file_orig);
		unlink($this->filename_gz);

		$this->screenshort_filename = "onliner_catalog_prices_" . time() . ".png";
		$this->logger->log("LOG", "Запрашиваем файл с ценами");
		// полный цикл.с пересоздание файла
		//shell_exec("/usr/local/bin/casperjs --web-security=no --fail-fast /var/www/bitrix/data/www/userscripts/js/casper_b2b_onliner.js --screenshort_filename={$this->screenshort_filename} >> /var/www/bitrix/data/www/userscripts/logs/casper_b2b_onliner_" . date("Y-m-d") . ".txt");
		shell_exec("/usr/bin/python3 /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/onliner/pricelists.py >> /home/bitrix/logs/onliner/parse/b2b_onliner_" . date("Y-m-d") . ".txt");
		sleep(30);

		chmod($this->filename_gz, 0777);
		//chmod($this->file_ex, 0777);
		//unlink($this->file_orig);



		$this->parseFile(1);
	}
	function parseFile($attempt = 1){
		global $DB;
		$this->logger->log("LOG", "Парсер. попытка " . $attempt);
		$this->file_tmp = $_SERVER['DOCUMENT_ROOT'] . "/upload/tmp/onliner/onliner_competitors_prices_" .date("Y_m_d_H_i_s"). ".csv";
		$this->filename_gz_tmp = $_SERVER['DOCUMENT_ROOT'] . "/upload/tmp/onliner/prices/onliner_competitors_prices_" . date("Y-m-d H:i:s") . ".csv.gz";
		$this->file_orig_tmp = $_SERVER['DOCUMENT_ROOT'] . "/upload/tmp/onliner/prices/onliner_competitors_prices_" . date("Y-m-d H:i:s") . ".csv";

		if($attempt > 1){
			unlink($this->file_orig);
			unlink($this->filename_gz);
			$this->screenshort_filename = "onliner_catalog_prices_" . time() . ".png";
			// авторизация и забор прайса. без пересоздания
			sleep(300);
			$this->logger->log("LOG", "Запрашиваем файл с ценами. попытка " . $attempt);
			//shell_exec("/usr/local/bin/casperjs --web-security=no --fail-fast /var/www/bitrix/data/www/userscripts/js/b2b_onliner_get_price.js --screenshort_filename={$this->screenshort_filename} >> /var/www/bitrix/data/www/userscripts/logs/casper_b2b_onliner_" . date("Y-m-d") . ".txt");
			shell_exec("/usr/bin/python3 /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/onliner/pricelists.py >> /home/bitrix/logs/onliner/parse/b2b_onliner_" . date("Y-m-d") . ".txt");

		}

		//распаковываем unpack
		if (file_exists($this->filename_gz))
			exec("gunzip -c {$this->filename_gz} > {$this->file_orig}");
		sleep(5);
		//$this->clear();

		//массив разделов которые нам нужны
		$arSection = array("Наручные часы");
		$arArticle = $this->getArticles();
		//prent($arArticle);


		$arResult["ONLINER_HIDE_SHOP"] = (array)json_decode(CProSet::getOption("ONLINER_HIDE_SHOP"), true);
		$arCsv = array();
		if (file_exists($this->file_orig)){
			$csv = new CSV($this->file_orig);
			if($csv->error != "no_found"){
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
				
				$this->logger->log("LOG", "Полученные товары ", $arResult["ITEMS"]);
				if(count($arResult["ITEMS"]) > 0){
					$this->clear();
				}
				//prent($arResult["ITEMS"]);die;
				if(is_array($arResult["SHOPS"]) && count($arResult["SHOPS"]) > 0){
					//пишем в базу
					$this->setShops($arResult["SHOPS"]);
				}
				//prent($arResult["ITEMS"]);die;
				//prent($arResult["ITEMS"]);die;
				if(is_array($arResult["ITEMS"]) && count($arResult["ITEMS"]) > 0){
					//пишем в базу
					$this->setPrices($arResult["ITEMS"]);

					$strSql = "SELECT COUNT(*) as cnt FROM ci_catalog_onliner";
					$results = $DB->Query($strSql, false, $err_mess.__LINE__);
					if ($row = $results->Fetch()){
						$result["status"] = "Y";
						CProSet::setOption("PARSE_CATALOG_ONLINER", $row["cnt"]);

						$html .= "<p>Добавлено {$row["cnt"]}. Всех товаров - " . (count($arCsv) - 1) . "</p>";
					}
					
					$this->parse2New();
				}else{
					$html .= "<p>Количество 0</p>";
					//copy($this->file_orig, $this->file_tmp);
					copy($this->file_orig, $this->file_orig_tmp);
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
			$this->logger->log("LOG", "Парсер онлайнера сломан. Попытка {$attempt}");
			if($attempt == 4){
				$this->TsTriggers->SetError(["Парсер онлайнера сломан. Попытка {$attempt}"]);
				$this->TsTriggers->SendTriggerErrors();
				
				$message = "<p>Парсер онлайнера сломан. Попытка {$attempt}</p>";
				$message .= $html;
				//$message .= "<p><a href='https://tempusshop.ru/upload/tmp/onliner/" . $this->screenshort_filename . "' target='_blank'>скрин</a></p>";
				$message .= "<p><a href='" . str_replace($_SERVER['DOCUMENT_ROOT'], "https://tempusshop.ru", $this->file_orig_tmp) . "' target='_blank'>архив</a></p>";
				$arFields = array(
					"EMAIL_TO" => "sales@tempus.by",
					"SUBJECT" => "Парсер онлайнера сломан",
					"MESSAGE" => $message,
				);

				CEvent::SendImmediate("IM_NEW_MESSAGE", array("s2"), $arFields, "N", 456);
			}elseif($attempt == 5) {
				
				$this->clear();
				return ["error" => "limit attempt"];
			}

			$attempt++;
			$this->parseFile($attempt);
		}
		CLog::add2log(array("event" => "C", "text" => $html));
		return $result;
	}
	
	function parse2New(){
		global $DB;
		$this->file_tmp = $_SERVER['DOCUMENT_ROOT'] . "/upload/tmp/onliner/onliner_competitors_prices_" .date("Y_m_d_H_i_s"). ".csv";
		$this->filename_gz_tmp = $_SERVER['DOCUMENT_ROOT'] . "/upload/tmp/onliner/prices/onliner_competitors_prices_" . date("Y-m-d H:i:s") . ".csv.gz";
		$this->file_orig_tmp = $_SERVER['DOCUMENT_ROOT'] . "/upload/tmp/onliner/prices/onliner_competitors_prices_" . date("Y-m-d H:i:s") . ".csv";

		$this->filename_gz = $_SERVER['DOCUMENT_ROOT'] . "/upload/onliner_competitors_prices.csv.gz";
		$this->file_orig = $_SERVER['DOCUMENT_ROOT'] . "/upload/onliner_competitors_prices.csv";
		//prent($arCsv);
		//if (file_exists($this->filename_gz))
		//	exec("gunzip -c {$this->filename_gz} > {$this->file_orig}");
		//sleep(5);

		$arSection = array("Наручные часы");
		$arArticle = $this->getArticles();
		
		$arResult["ONLINER_HIDE_SHOP"] = (array)json_decode(CProSet::getOption("ONLINER_HIDE_SHOP"), true);
		
		$arCsv = array();
		if (file_exists($this->file_orig)){
			$csv = new CSV($this->file_orig);
			if($csv->error != "no_found"){
				$get_csv = $csv->getCSV();
				foreach ($get_csv as $key => $value){
					$arCsv[] = $value;
				}

				$col_id = $col_name = false;
				$arColShop = array();
				
				// Определяем колонки
				foreach ($arCsv[0] as $key => $row){
					$row_utf8 = iconv("windows-1251", "UTF-8", $row);
					
					if(preg_match('/^ID товара/', $row_utf8)) $col_id = $key;
					if($row_utf8 == "Название") $col_name = $key;
					
					if(preg_match('/^Магазин /', $row_utf8)) {
						$arColShop[] = array(
							'shop_col' => $key,
							'price_col' => $key + 1
						);
					}
				}

				if($col_id === false || $col_name === false) {
					$this->logger->log("ERROR", "Не найдены необходимые колонки (ID товара или Название)");
					$html .= "<p>Не найдены необходимые колонки</p>";
				} else {
					$currentDate = date('Y-m-d H:i:s');
					$processedItems = 0;

					$allProductIds = array();
					foreach($arCsv as $key => $arItem) {
						if($key == 0) continue;
						$productId = intval($arItem[$col_id]);
						if($productId > 0) {
							$allProductIds[] = $productId;
						}
					}

					$allProductIds = array_unique($allProductIds);

					$existingPrices = array();
					if(!empty($allProductIds)) {
						$strSql = "SELECT ID, PRODUCT_ID, COMPETITOR_NAME, PRICE 
								   FROM ci_price_competitor 
								   WHERE PRODUCT_ID IN (".implode(',', $allProductIds).") 
								   AND PRICE_TYPE = 'by'";
						$result = $DB->Query($strSql, false, $err_mess.__LINE__);
						
						while($row = $result->Fetch()) {
							$key = $row['PRODUCT_ID'] . '_' . $row['COMPETITOR_NAME'];
							$existingPrices[$key] = array(
								'ID' => $row['ID'],
								'PRICE' => $row['PRICE']
							);
						}
					}

					$newPrices = array();
					$updatePrices = array();
					$currentPrices = array();

					foreach($arCsv as $key => $arItem) {
						if($key == 0) continue;
						
						$productId = intval($arItem[$col_id]);
						if($productId <= 0) continue;

						$productName = $arItem[$col_name];
						if(strlen($arArticle[$productName]) > 0){
							$article = $arArticle[$productName];
						} else {
							$article = $productName;
						}
						
						foreach($arColShop as $shopCols) {
							$shopName = trim(iconv("windows-1251", "UTF-8", $arItem[$shopCols['shop_col']]));
							$price = trim($arItem[$shopCols['price_col']]);
							
							if(empty($shopName) || empty($price) || $price == '0') continue;
							
							if(in_array($shopName, $arResult["ONLINER_HIDE_SHOP"])) {
								continue;
							}
							
							$price = str_replace(',', '.', $price);
							$priceValue = (float) $price;
							if($priceValue <= 0) continue;
							
							$key = $productId . '_' . $shopName;
							$currentPrices[$key] = true;
							
							if(isset($existingPrices[$key])) {
								$existingPrice = $existingPrices[$key]['PRICE'];
								if(abs($existingPrice - $priceValue) > 0.01) {
									$updatePrices[] = array(
										'ID' => $existingPrices[$key]['ID'],
										'PRICE' => $priceValue,
										'PREVIOUS_PRICE' => $existingPrice
									);
								}
							} else {
								$newPrices[] = array(
									'PRODUCT_ID' => $productId,
									'ARTICLE' => $article,
									'COMPETITOR_NAME' => $shopName,
									'PRICE' => $priceValue
								);
							}
						}
					}

					if(!empty($updatePrices)) {
						foreach($updatePrices as $update) {
							$strSql = "UPDATE ci_price_competitor SET 
									  PRICE = ".$update['PRICE'].",
									  PREVIOUS_PRICE = ".$update['PREVIOUS_PRICE'].",
									  DATE_UPDATE = '".$currentDate."'
									  WHERE ID = ".$update['ID'];
							$DB->Query($strSql, false, $err_mess.__LINE__);
						}
						$processedItems += count($updatePrices);
						$this->logger->log("LOG", "Обновлено записей: ".count($updatePrices));
					}

					if(!empty($newPrices)) {
						$values = array();
						foreach($newPrices as $newPrice) {
							$values[] = "(
								".$newPrice['PRODUCT_ID'].",
								'".$DB->ForSql($newPrice['ARTICLE'])."',
								'by',
								'".$DB->ForSql($newPrice['COMPETITOR_NAME'])."',
								".$newPrice['PRICE'].",
								NULL,
								'".$currentDate."',
								'".$currentDate."'
							)";
						}
						
						$strSql = "INSERT INTO ci_price_competitor 
								  (PRODUCT_ID, ARTICLE, PRICE_TYPE, COMPETITOR_NAME, PRICE, PREVIOUS_PRICE, DATE_CREATE, DATE_UPDATE)
								  VALUES ".implode(',', $values);
						$DB->Query($strSql, false, $err_mess.__LINE__);
						$processedItems += count($newPrices);
						$this->logger->log("LOG", "Добавлено новых записей: ".count($newPrices));
					}

					if(!empty($allProductIds)) {
						$notInConditions = array();
						foreach($currentPrices as $key => $value) {
							list($productId, $competitorName) = explode('_', $key, 2);
							$notInConditions[] = "(PRODUCT_ID = ".$productId." AND COMPETITOR_NAME = '".$DB->ForSql($competitorName)."')";
						}
						
						if(!empty($notInConditions)) {
							$strSql = "DELETE FROM ci_price_competitor 
									  WHERE PRODUCT_ID IN (".implode(',', $allProductIds).")
									  AND PRICE_TYPE = 'by'";
							
							if(!empty($notInConditions)) {
								$strSql .= " AND NOT (".implode(' OR ', $notInConditions).")";
							}
							
							$deleteResult = $DB->Query($strSql, false, $err_mess.__LINE__);
						} else {
							$strSql = "DELETE FROM ci_price_competitor 
									  WHERE PRODUCT_ID IN (".implode(',', $allProductIds).")
									  AND PRICE_TYPE = 'by'";
							$deleteResult = $DB->Query($strSql, false, $err_mess.__LINE__);
						}
					}

					$this->logger->log("LOG", "Всего обработано записей: ".$processedItems);

					$strSql = "SELECT COUNT(DISTINCT PRODUCT_ID) as product_count, 
									  COUNT(*) as price_count 
							   FROM ci_price_competitor 
							   WHERE PRICE_TYPE = 'by'";
					$results = $DB->Query($strSql, false, $err_mess.__LINE__);

					if($row = $results->Fetch()) {
						//CProSet::setOption("PARSE_CATALOG_ONLINER", $row["price_count"]);

						$html .= "<p>Обновлено/добавлено: ".$processedItems." цен</p>";
						$html .= "<p>Товаров в базе: ".$row['product_count']."</p>";
						$html .= "<p>Всего цен в базе: ".$row['price_count']."</p>";
						$html .= "<p>Всех товаров в CSV: " . (count($arCsv) - 1) . "</p>";
					}
				}

			} else {
				$html .= "<p>Файл сломан</p>";
				copy($this->filename_gz, $this->filename_gz_tmp);
				copy($this->file_orig, $this->file_orig_tmp);
				$html .= "<p><a href='".str_replace($_SERVER['DOCUMENT_ROOT'], "https://tempusshop.ru", $this->filename_gz_tmp)."'>Оригинал файла скачанный из б2б онлайнера</a></p>";
				$html .= "<p><a href='".str_replace($_SERVER['DOCUMENT_ROOT'], "https://tempusshop.ru", $this->file_orig_tmp)."'>Распакованный файл из архива</a></p>";
			}
		} else {
			$html .= "<p>Архив не загружен</p>";
		}
		
		/*if(count($arCsv) == 0){
			$this->logger->log("LOG", "Парсер онлайнера сломан. Попытка {$attempt}");
			if($attempt == 4){
				$this->TsTriggers->SetError(["Парсер онлайнера сломан. Попытка {$attempt}"]);
				$this->TsTriggers->SendTriggerErrors();
				
				$message = "<p>Парсер онлайнера сломан. Попытка {$attempt}</p>";
				$message .= $html;
				$message .= "<p><a href='" . str_replace($_SERVER['DOCUMENT_ROOT'], "https://tempusshop.ru", $this->file_orig_tmp) . "' target='_blank'>архив</a></p>";
				$arFields = array(
					"EMAIL_TO" => "sales@tempus.by",
					"SUBJECT" => "Парсер онлайнера сломан",
					"MESSAGE" => $message,
				);

				CEvent::SendImmediate("IM_NEW_MESSAGE", array("s2"), $arFields, "N", 456);
			} elseif($attempt == 5) {
				$strSql = "DELETE FROM ci_price_competitor WHERE PRICE_TYPE = 'current'";
				$DB->Query($strSql);
				$this->logger->log("LOG", "Очищена таблица цен конкурентов после 5 попыток");
				return ["error" => "limit attempt"];
			}

			$attempt++;
			$this->parseFile($attempt);
		}
		
		CLog::add2log(array("event" => "C", "text" => $html));
		return $result;*/
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
