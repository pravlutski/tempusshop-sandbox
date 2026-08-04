<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Поиск ссылок на онлайнере");
?>

<?
/*
global $DB;
$arSelect = Array("ID", "NAME", "PROPERTY_TRADEICSBEL_ID", "IBLOCK_ID", "PROPERTY_VENDOR");
$arFilter = Array(
	"IBLOCK_TYPE" => "ns_catalog",
//	"IBLOCK_ID" => 172,
//	"=PROPERTY_VENDOR" => "SUPRA"
);
								  
$res = CIBlockElement::GetList(Array("ID" => "DESC"), $arFilter, false, array("iNumPage" => 1, "nPageSize" => 100), $arSelect);
$ar = array();
while($ar_fields = $res->GetNext()){
//	if($ar_fields["PROPERTY_VENDOR_VALUE"] < 738143)
		$ar[] = $ar_fields;

}
foreach($ar as $key => $arItem){
//	CIBlockElement::Delete($arItem["ID"]);
//	$DB->Update("ci_catalog_tradeicsbel", array("bitrix_id" => "NULL"), "WHERE code='".$arItem["PROPERTY_TRADEICSBEL_ID_VALUE"]."'", $err_mess.__LINE__);
}
prent($ar);
die;
*/
global $DB;
include_once('/home/bitrix/www/bitrix/modules/onlinerparser/classes/general/nokogiri.php');

function _gzdecode($file){
	ob_start();
	readgzfile($file);
	$d = ob_get_clean();
	return $d;
}

$arResult["ITEMS"] = array();
$strSql = "SELECT * FROM ci_catalog_tradeicsbel WHERE bitrix_id IS NULL";// AND id = '4743'";
$rs = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($rw = $rs->Fetch()){
	$arResult["ITEMS"][] = 	$rw;
}
//prent($arResult["ITEMS"]);die;
foreach($arResult["ITEMS"] as $key => $arItem){
	
	$text_search = $arItem["name"];//"Supra SDT-92";
	$text_search = str_replace(array("(1к)", "(2к)", "(3к)", "(4к)", "(СТБ)", "(USB)", "(oem)", "(ADSL2+)"), array("", "", "", "", "", "", "", ""), $text_search);
	$text_search = trim($text_search);
	$ch = curl_init("https://catalog.onliner.by/search?query=" . $text_search);
	$fp = fopen("/tmp/onliner_tmp/_search.txt", "w");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_TIMEOUT, 240);
	curl_setopt($ch, CURLOPT_HEADER, true);
	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	//prent($output);prent($info);
	curl_close($ch);
	fclose($fp);
	
	if($info["http_code"] == "200"){
		$html = _gzdecode("/tmp/onliner_tmp/_search.txt");
		$saw = new nokogiri($html);
		unset($html); $html = NULL;
		$arSearch = $saw->get('.search ul.search__results li.search__result a.product__title-link')->toArray();
		unset($saw); $saw = NULL;

		if(count($arSearch) == 1){
			//ищем в таблице запись
			$url = $arSearch[0]["href"];
			$url = str_replace(array("https://", "http://"), "", $url);
			if(strlen($url) > 10){
				//$url = "http://catalog.onliner.by/terrestrial/supra/suprasdt92";
				$strSql = "SELECT id FROM ci_catalog_onliner WHERE url LIKE '%".mysql_real_escape_string($url)."'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$onliner_id = $row["id"];
					$name = $arItem["name"];
					$code = $arItem["code"];
					
					$out = createItemOnliner($code, $name, $onliner_id);
					if($out["status"] == "ok"){
						echo "<p style='color:green;'>Добавлен товар - {$name}</p>";
					}else{
						echo "<p style='color:red;'>Ошибка во время записи - {$name}</p>";
					}
					//prent($out);
				}else{
					echo "<p style='color:red;'>Не найдена ссылка в базе - {$url}</p>";
				}
			}else{
				echo "<p style='color:red;'>Битая ссылка - {$text_search}</p>";
			}

		}else{
			echo "<p style='color:red;'>Найденных товаров больше чем одна. Искали - {$text_search}</p>";
		}
	//			$link = $this->parseLinkFromPageSearch($arSearch);
	}else{
		echo "<p style='color:red;'>Не удалось найти {$text_search}.</p>";
	}
}

function createItemOnliner($code, $name, $onliner_id){
	global $DB;
	$flg_add = false;
	$rs = CIBlockElement::GetList(array(), array('IBLOCK_TYPE' => "ns_catalog", "PROPERTY_TRADEICSBEL_ID" => $code), false, false, array("ID", "IBLOCK_ID"));
	$arBrand = array();
	if ($ar = $rs->GetNext()){
		if($ar["IBLOCK_ID"] != CProSet::IB_ONLINER_ITEMS){
			$strSql = "SELECT * FROM ci_catalog_onliner WHERE id = '" . $onliner_id . "'";
			$resDB = $DB->Query($strSql, false, $err_mess.__LINE__);
			if($row = $resDB->Fetch()){
				CIBlockElement::SetPropertyValueCode($ar["ID"], "LINK_ONLINER", $row["url"]);
				CIBlockElement::SetPropertyValueCode($ar["ID"], "PRODUCT_NO_PRICE", "Y");
				CIBlockElement::SetPropertyValueCode($ar["ID"], "FLG_PARSE_CHR", "Y");
				CIBlockElement::SetPropertyValueCode($ar["ID"], "FLG_PARSE_IMAGES", "Y");
				$el = new CIBlockElement;
				$arLoadProductArray = Array(
					"ACTIVE"	=> "Y",
				);
				$rs = $el->Update($ar["ID"], $arLoadProductArray);
				$res["data"] = "Товар с кодом {$code} обновлен";
					
				$DB->Update("ci_catalog_tradeicsbel", array("bitrix_id" => "'".$ar["ID"]."'"), "WHERE code='".$code."'", $err_mess.__LINE__);
			}else{
				$res["data"] = "Товар с кодом {$code} обновить не удалось";
			}
		}else{
			$res["data"] = "Товар с кодом {$code} удалили из общего инфоблока<br>";
			CIBlockElement::Delete($ar["ID"]);
			$flg_add = true;
		}
		$res["status"] = "error";
	}else{
		$flg_add = true;
	}
	
	if($flg_add === true){
		$strSql = "SELECT * FROM ci_catalog_onliner WHERE id = '" . $onliner_id . "'";
		$resDB = $DB->Query($strSql, false, $err_mess.__LINE__);
		if($row = $resDB->Fetch()){
			$brand = $row["brand"];
			$model = $row["model"];
			$url = $row["url"];
			$s1 = "SELECT `ID`, `CODE` FROM `b_iblock` WHERE NAME = '{$row["section"]}'";
			$resDB2 = $DB->Query($s1, false, $err_mess.__LINE__);
			if($row1 = $resDB2->Fetch()){
				$iblock_id = $row1["ID"];
				$iblock_code = $row1["CODE"];
				$sym_code = $iblock_code . "-" . abs($code * 3 - 10200000);
				$brend_id = mb_strtoupper($brand);
				$arProp = array(
					"VENDOR" => $brend_id, 
					"MODEL" => $model, 
					"FLG_PARSE_CHR" => "Y",
					"FLG_PARSE_IMAGES" => "Y",
					"FLG_CONFORMITY_ONLINER" => "Y",
					"LINK_ONLINER" => $url,
					"TRADEICSBEL_ID" => $code,
					"PRODUCT_NO_PRICE" => "Y",
				);
				$el = new CIBlockElement;
				$arLoadCodeArray = Array(
					"MODIFIED_BY"    	=> $GLOBALS['USER']->GetID(),
					"IBLOCK_ID"      	=> $iblock_id,
					"CODE"   			=> $sym_code,
					"NAME"           	=> $name,
					"PROPERTY_VALUES"	=> $arProp,
					"XML_ID" 			=> abs($code * 3 - 10200000),
					"ACTIVE"         	=> "Y",
				);
				prent($arLoadCodeArray);
				if($CODE_ID = $el->Add($arLoadCodeArray)){
					$res["status"] = "ok";
					$res["data"] .= "Добавлен товар - " . $name;
					$DB->Update("ci_catalog_tradeicsbel", array("bitrix_id" => "'".$CODE_ID."'"), "WHERE code='".$code."'", $err_mess.__LINE__);
					//$res["data"] = array("id" => $onliner_id);
				}else{
					$res["status"] = "error";
					$res["data"] .= "Не удалось добавить товар - " . $name;
				}

			}else{
				$res["status"] = "error";
				$res["data"] .= "Не найден инфоблок - " . $row["section"];
			}

		}else{
			$res["status"] = "error";
			$res["data"] .= "Удалить не найти в таблице онлайнера";
		}
	}
	return $res;
}

?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>