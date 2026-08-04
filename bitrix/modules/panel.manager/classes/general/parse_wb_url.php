<?
class CWbParserURI{
	
	public static $allCnt;
	public static $arWB;
	public $arUrl = array();

	function __construct(){
		$this->path = $_SERVER['DOCUMENT_ROOT'] . "/upload/wb_parse";
	}

	//парсим файл со ссылками для последующего парсинга
	function parseFile($filename){
		
		global $DB;
		if (!class_exists('SpreadsheetReader')){
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}
		
		//$filename = $_SERVER["DOCUMENT_ROOT"] . "/upload/wb_parse.xlsx";
		
		$spreadsheet = new SpreadsheetReader($filename);
		$sheets = $spreadsheet->sheets();
		$ar = array();
		$i = 0;
		foreach ($sheets as $index => $Name){
			if(count($arLists) > 0 && !in_array($index, $arLists)) continue;
			$spreadsheet->ChangeSheet($index);

			foreach ($spreadsheet as $key => $row){
				$ar[$i] = $row;
					
				$i++;
			}
		}

		$strSql = "SELECT pr.IBLOCK_ELEMENT_ID as ID, pr.PROPERTY_123 as ARTICLE 
		FROM 
			b_iblock_element el 
		LEFT JOIN 
			b_iblock_element_prop_s16 pr 
		ON el.ID=pr.IBLOCK_ELEMENT_ID 
		WHERE 
			el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";
		
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($row["ARTICLE"])
				$arArticle[$row["ARTICLE"]] = $row["ID"];
		}

		$arLink = array();
		$arError = array();
		foreach($ar as $key => $arItem){
			$wbID = explode("/", $arItem[0])[4];
			$wbID = intval($wbID);
			
			$article = $arItem[1];
			$supplier = $arItem[2];
			
			if($wbID > 0 && strlen($article) > 0 && strlen($supplier) > 0){
				if($arArticle[$article]){
					
					$arLink[] = array(
						"bitrix_id" => $arArticle[$article],
						"wb_id" => $wbID,
						"article" => $article,
						"supplier" => $supplier,
					);

				}else{
					$arError[] = "WB_ID - {$wbID}, Артикул - {$article}, Поставщик - {$supplier}";//$arItem;
				}
			}
		}
		
		//if(count($arLink) > 0){
			$this->setLink($arLink);
		//}
		CProSet::setOption("PARSE_LINK_WB", count($arLink));
		return array("true" => count($arLink), "error" => count($arError), "detail" => $arError);
	}
	
	function clearPrice(){
		global $DB;
		$DB->Query("TRUNCATE TABLE ci_wb_price", false, $err_mess.__LINE__);
	}
	function clearParse(){
		global $DB;
		$DB->Query("TRUNCATE TABLE ci_wb_parser", false, $err_mess.__LINE__);
	}
	
	function parse(){
		global $DB;
		CProSet::setOption("PARSE_WB_URI", "IN_PROCESS");
		$arArticle = array();
		$arResult = array();
		
		$arParse = $this->getParseList();
		
		$this->clearParse();
		foreach($arParse as $key => $arItem){
			
			if(strlen($arItem["wb_id"]) > 0)
				$url = "https://www.wildberries.ru/catalog/{$arItem["wb_id"]}/detail.aspx";
			else
				continue;
			//если файл есть и он не старше 36000000 то берем его			

			$filename = $this->path . "/main_{$arItem["wb_id"]}.txt";

			if (file_exists($filename) && filesize($filename) > 10000) {
				$filediff = time() - filectime($filename);
				if($filediff < 3600){
					$result_file = $filename;
				}else{
					$result_file = $this->createfile2Parse($url, $arItem["wb_id"], "main", 1);
				}
			}else{
				$result_file = $this->createfile2Parse($url, $arItem["wb_id"], "main", 1);
			}
			
			if($result_file == "no_parse"){
				$error[] = "Не удалось получить характеристики. WB забанил сука) - {$url}";
			}elseif($result_file == "no_found"){
				$error[] = "Страница не существует - {$url}";
			}elseif(strlen($result_file) > 0){
				
				/*
				$tmp = file_get_contents($result_file);
				preg_match_all('/ssrModel:(.*)/', $tmp, $matches);
				$str = $matches[0][0];
				$str = str_replace("ssrModel: ", "", $str);
				
				$str = trim($str, ",");
				$ar = json_decode($str, true);
				prent($ar,0,1);
				prent($url,0,1);
				die;*/
				$html = $this->gzdecode($result_file);
				$saw = new CNokogiri($html);

				$price = $saw->get('.price-block__final-price')->toArray()[0]["#text"][0];
				$price = preg_replace('/[^\d.]/', '', $price);
				
				$price = (float)$price;
				//$price = trim($price);
				
				
				if($price > 0){
					
					$arResult["ITEMS"][] = array(
						"bitrix_id" => $arItem["bitrix_id"],
						"wb_id" => $arItem["wb_id"],
						"price" => $price,
					);
					
				}else{
					//log
					$error[] = "Цена не найдена " . $url;
				}
				unset($html); $html = NULL;
				unset($saw); $saw = NULL;
			}
			
			$p = round(100 * ($key + 1) / count($arParse), 2);
			CProSet::setOption("PARSE_WB_URI_PER", $p);
			
		}
		//prent($arResult["ITEMS"]);
		$this->setParser($arResult["ITEMS"]);
		$this->parseAnalysis();
		
		if(count($error) > 0){
			$txt = "";
			foreach($error as $er){
				$txt .= "<p>{$er}</p>";
			}
			/*$arLog = array(
				"event" => "WB",
				"text" => "Ошибки парсера WB",
				"detail" => array("mess" => $txt),
			);
			CLog::add2log($arLog);*/
			
			$arLog = array(
				"event" => "ER",
				"text" => "Ошибки парсера WB",
				"detail" => array("mess" => $txt),
			);
			CLog::add2log($arLog);
			
		}
		
		CProSet::setOption("PARSE_WB_URI", "N");
		
		return $result;

	}

	function setLink($arItems = array()){
		global $DB;
		$DB->Query("TRUNCATE TABLE ci_wb_link", false, $err_mess.__LINE__);
		foreach($arItems as $arItem){
			$in = array(
				"bitrix_id" => "'".addslashes($arItem["bitrix_id"])."'",
				"wb_id" => "'".addslashes($arItem["wb_id"])."'",
				"article" => "'".addslashes($arItem["article"])."'",
				"supplier" => "'".addslashes($arItem["supplier"])."'",
			);

			$DB->Insert("ci_wb_link", $in, $err_mess.__LINE__);
		}
	}
	
	function createfile2Parse($urlParse, $keyUrl, $pref = "main", $cnt){
		if($cnt == 3) sleep(30);

//		usleep(1000000);
		usleep(500000);
		$ch = curl_init($urlParse);
		$fp = fopen($this->path . "/{$pref}_{$keyUrl}.txt", "w");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		//curl_setopt($ch, CURLOPT_COOKIE, "catalog_region_select=minsk");
		curl_setopt($ch, CURLOPT_TIMEOUT, 240);
		
		curl_setopt($ch, CURLOPT_HEADER, true);
		 
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);

		curl_close($ch);
		fclose($fp);
prent($info);
		if($info["http_code"] == "302" && $info["redirect_url"]){
			return $this->createfile2Parse($info["redirect_url"], $keyUrl, $pref, $cnt);
		}
			
		if($info["http_code"] == "503" && $cnt < 6){
			//если страница есть, но WB забанил
			//если 10 раз не получится получить бляцкую страницу то бросаем)))
			sleep($cnt);
			$cnt++;
			if($cnt == 5)
				return "no_parse";
			else
				return $this->createfile2Parse($urlParse, $keyUrl, $pref, $cnt);
				
		}elseif($info["http_code"] == "404"){
			//если страницы не существует
			//$data = "no_found";
			return "no_found";
		}elseif($info["http_code"] == "200"){
			return $this->path . "/{$pref}_{$keyUrl}.txt";
		}else{
			if($cnt != 6)
				return $this->createfile2Parse($urlParse, $keyUrl, $pref, 16);
			return "error";
		}

	}
	function setParser($arItems = array()){
		global $DB;
		foreach($arItems as $arItem){
			$in = array(
				"bitrix_id" => "'".addslashes($arItem["bitrix_id"])."'",
				"wb_id" => "'".addslashes($arItem["wb_id"])."'",
				"price" => "'".$arItem["price"]."'",
			);
			$DB->Insert("ci_wb_parser", $in, $err_mess.__LINE__);
		}
		
		CProSet::setOption("PARSE_CATALOG_WB", count($arItems));
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
			$DB->Insert("ci_wb_price", $in, $err_mess.__LINE__);
		}
	}
	public function parseAnalysis() {
		global $DB;
		$this->clearPrice();
		$strSql = "SELECT * FROM ci_wb_parser parser";
		
		$strSql = "SELECT parser.bitrix_id as BITRIX_ID, parser.wb_id as WB_ID, parser.price as PRICE, link.article as ARTICLE, link.supplier as SUPPLIER  
		FROM 
			ci_wb_parser parser 
		LEFT JOIN 
			ci_wb_link link 
		ON parser.wb_id=link.wb_id"; 

//WHERE 
//			el.ID IN ('" . implode("','", $arIDs) . "') AND el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";
		
		
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$ar[$row["BITRIX_ID"]][] = $row;
		}
		//prent($ar);
		foreach($ar as $bitrix_id => $arItem){
			
			$arPrice = sort_nested_arrays($arItem, array('PRICE' => 'asc'));
			
			$in = array(
				"name" => "'".addslashes($arPrice[0]["ARTICLE"])."'",
				"bitrix_id" => "'".addslashes($arPrice[0]["BITRIX_ID"])."'",
				"wb_id" => "'".addslashes($arPrice[0]["WB_ID"])."'",
				"minPrice" => "'".$arPrice[0]["PRICE"]."'",
				"minPrice2" => "'".$arPrice[1]["PRICE"]."'",
				"minPrice3" => "'".$arPrice[2]["PRICE"]."'",
				"info" => "'".addslashes($arPrice[0]["SUPPLIER"])."'",
			);

			$DB->Insert("ci_wb_price", $in, $err_mess.__LINE__);
			//prent($in);
			/*
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `bitrix_id` int(11) COLLATE utf8_unicode_ci NOT NULL,
  `wb_id` int(11) COLLATE utf8_unicode_ci NOT NULL,
  `minPrice` float(15) COLLATE utf8_unicode_ci NOT NULL,
  `minPrice2` float(15) COLLATE utf8_unicode_ci NOT NULL,
  `minPrice3` float(15) COLLATE utf8_unicode_ci NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `info` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			*/
		}
		
	}
	public function getParseList() {
		global $DB;
		$strSql = "SELECT * FROM ci_wb_link";// LIMIT 0,10";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
//			$arItems[$row["wb_id"]] = $row;
			$arItems[] = $row;
		}
		return $arItems;
	}
	function gzdecode($file){
		ob_start();
		readgzfile($file);
		$d = ob_get_clean();
		return $d;
	}
	
}
?>