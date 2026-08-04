<?
class CCeneoParser{
	public static $allCnt;
	
	function __construct(){
		$this->apiKey = "1999a451-f756-4de5-be40-d03f4eee7cd5";
	}
	function getArticles($arID = array()){
		global $DB;
		$strSql = "SELECT el.ID as ID, pr.VALUE as ARTICLE FROM b_iblock_element el LEFT JOIN b_iblock_element_property pr 
				ON el.ID=pr.IBLOCK_ELEMENT_ID WHERE 
				el.ACTIVE = 'Y' 
				AND el.IBLOCK_ID IN ('16','17') AND pr.IBLOCK_PROPERTY_ID IN ('121','123') AND pr.VALUE <> '' AND  
				el.ID IN ('".implode("','",$arID)."')";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arArticle = array();
		while ($arFields = $results->Fetch()){
			$arArticle[$arFields["ID"]] = $arFields["ARTICLE"];
		}
		return $arArticle;
	}
	function clear($arIDs){
		global $DB;
		$DB->Query("DELETE FROM ci_ceneo_price WHERE bitrix_id IN ('" . implode("','", $arIDs)."')", false, $err_mess.__LINE__);
		
//		CProSet::setOption("PARSE_CATALOG_YANDEX", 0);
	}
	
	function parse($ID = 0){
		global $DB;
		
		//$this->clear($arID);
		//$arArticle = $this->getArticles($arID);
		$arArticle = array();
		$arResult = array();
		$el = new CIBlockElement;

		
		$ID = intval($ID);
		
		if($ID == 0 || CProSet::getOption("PARSE_CENEO") == "Y") {
			self::getAllCnt();
			CProSet::setOption("PARSE_CENEO", "IN_PROCESS");
			CProSet::setOption("PARSE_CENEO_ERROR", "0");
		}
		/*
		$strSql = "SELECT 
				el.ID as ID, pr.VALUE as ARTICLE 
				FROM 
					b_iblock_element el 
				LEFT JOIN 
					b_iblock_element_property pr 
				ON el.ID=pr.IBLOCK_ELEMENT_ID 
				WHERE 
					el.ACTIVE = 'Y' AND el.IBLOCK_ID IN ('16','17') AND pr.IBLOCK_PROPERTY_ID IN ('121','123') 
					AND pr.VALUE <> '' AND  el.ID > {$ID} ORDER BY el.ID ASC LIMIT 0,{$limit}";
					
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($arFields = $results->Fetch()){
			$arArticle[$arFields["ID"]] = $arFields["ARTICLE"];
			$lastID = intval($arFields["ID"]);
		}
		*/
		$limit = 10;
		$arSelect = Array("ID", "PROPERTY_CML2_ARTICLE");
		
		$arFilter = Array(
			"IBLOCK_ID" => 16,
			"ACTIVE" => "Y", 
			">CATALOG_PRICE_3" => 0,
//			"=PROPERTY_SITE_ID" => "s3",
			">CATALOG_QUANTITY" => "0",
			">ID" => $ID,
		);
		$rsEl = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, array("nTopCount" => $limit), $arSelect);
		while($arFields = $rsEl->GetNext()){
			$arArticle[$arFields["ID"]] = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
			$lastID = intval($arFields["ID"]);
		}
	
		$this->clear(array_keys_($arArticle));
		//if(count($arArticle) <= 0){
		//	CProSet::setOption("PARSE_CENEO", "N");
		//	return false;
		//}
		
		$ceneo = file_get_contents("https://developers.ceneo.pl/api/v2/function/webapi_data_critical.getCeneo10ProductsTop10OffersBy_ShopIDs/Call?shop_product_ids_comma_separated=".implode(",",array_keys_($arArticle))."&apiKey={$this->apiKey}&resultFormatter=json");
		$res = json_decode($ceneo, true);
//
		$arResult["CENEO_SHOP_HIDE"] = json_decode(CProSet::getOption("CENEO_HIDE_SHOP"), true);

		$result["status"] = "N";
		//prent($res);die;
		if($res["Response"]["Status"] == "OK"){
			
			
			foreach($res["Response"]["Result"]["product_offers_by_ids"]["product_offers_by_id"] as $key => $arItem){
				
				$arResult["LINKS"][$arItem["ShopProdID"]] = array(
					"BITRIX_ID" => $arItem["ShopProdID"],
					"CENEO_ID" => $arItem["CeneoProdID"],
				);
				
				if($arItem["OffersNumber"] > 0){
					$min_price = false;
					foreach($arItem["offers"]["offer"] as $k => $arOffer){
						$name = $arOffer["CustName"];
						$arResult["SHOPS"][$name] = $name;
									
						//ищем минимальную цену с учетом магазинов которые надо исключить
						if(!in_array($name, $arResult["CENEO_SHOP_HIDE"])){
							if(!$min_price){
								$min_price = $arOffer["Price"];
							}elseif($min_price && $arOffer["Price"] < $min_price){
								$min_price =$arOffer["Price"];
							}
						}
					}
					
					if($min_price){
						$arResult["ITEMS"][] = array(
							"ARTICLE" => $arArticle[$arItem["ShopProdID"]],
							"BITRIX_ID" => $arItem["ShopProdID"],
							"CENEO_ID" => $arItem["CeneoProdID"],
							"MIN_PRICE" => $min_price,
						);
					}
				}
			}

		//prent($arResult["CENEO_SHOP_HIDE"]);
		
			//prent($arResult["ITEMS"]);die;
			if(count($arResult["SHOPS"]) > 0){
				//пишем в базу
				$this->setShops($arResult["SHOPS"]);
			}
			if(count($arResult["LINKS"]) > 0){
				//пишем в базу
				$this->setLink($arResult["LINKS"]);
			}
			
			if(count($arResult["ITEMS"]) > 0){
				//пишем в базу
				$this->setPrices($arResult["ITEMS"]);
					
				$strSql = "SELECT COUNT(*) as cnt FROM ci_ceneo_price";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$result["status"] = "Y";
					CProSet::setOption("PARSE_CATALOG_CENEO", $row["cnt"]);
				}
			}else{
				$result["ERROR"][] = "Количество 0";
			}
			//
			/*
			$strSql = "SELECT el.ID FROM b_iblock_element el WHERE el.ACTIVE = 'Y' AND el.IBLOCK_ID IN ('16','17') AND el.ID <= {$ID} ORDER BY el.ID ASC";
			$leftBorderCnt = $DB->Query($strSql, false, $err_mess.__LINE__)->SelectedRowsCount();

			$p = round(100 * $leftBorderCnt / self::$allCnt, 2);*/
			
			$arFilter = Array(
				"IBLOCK_ID" => 16,
				"ACTIVE" => "Y", 
				">CATALOG_PRICE_3" => 0,
//				"=PROPERTY_SITE_ID" => "s3",
				">CATALOG_QUANTITY" => "0",
				"<=ID" => $lastID
			);
			$rsLeftBorder = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter);
			$leftBorderCnt = $rsLeftBorder->SelectedRowsCount();

			$p = round(100 * $leftBorderCnt / self::$allCnt, 2);
			if($p > 100) $p = 100;
	
			CProSet::setOption("PARSE_CENEO_PER", $p);// . " - " . memory_get_usage());
			CProSet::setOption("PARSE_CENEO_LAST_ID", $lastID);
			if($p >= 100){
				//ставим метку если успешно обновлено
				CProSet::setOption("PARSE_CENEO", "N");
			}
			//return array("PERCENT" => $p, "LAST_ID" => $lastID);
			$result["PERCENT"] = $p;
			$result["LAST_ID"] = $lastID;
			
		}else{
			$err = CProSet::getOption("PARSE_CENEO_ERROR");
			$err++;
			//$result["ERROR"][] = "Ошибка в ответе от ceneo " . serialize($res);
			
			CProSet::setOption("PARSE_CENEO_ERROR", $err);
			
			CLog::add2log(array("event" => "PC", "text" => "Ошибка в ответе от ceneo " . serialize($res) . ". Попытка {$err}"));
			
			if($err >= 5){
				CProSet::setOption("PARSE_CENEO", "Y");
				return false;
			}else{
				$result["LAST_ID"] = $ID;
			}
			

		}
		
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
	
	function setPrices($arItems = array()){
		global $DB;
		foreach($arItems as $arItem){
			$in = array(
				"name" => "'".addslashes($arItem["ARTICLE"])."'",
				"bitrix_id" => "'".addslashes($arItem["BITRIX_ID"])."'",
				"ceneo_id" => "'".addslashes($arItem["CENEO_ID"])."'",
				"minPrice" => "'".$arItem["MIN_PRICE"]."'",
				"type_price" => "'".addslashes("CENEO_API")."'",
			);
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
}
?>