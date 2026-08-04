<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$arResult["ITEMS"] = $arResult["SIMILAR_ITEMS"] = array();
$article = addslashes(trim($_REQUEST["article"]));
$model = addslashes(trim($_REQUEST["model"]));
//$model = iconv("windows-1251", "utf-8", $model);
//$arResult[]["id"] = serialize($_REQUEST);
if (CModule::IncludeModule('main') && CModule::IncludeModule('iblock') && strlen($article) > 0){
	/* пытаемся найти */
	if(strlen($model) > 0){
		//$strSql = "SELECT c.id as id, c.section as section, c.brand as brand, c.model as model, c.url as url, a.article as article FROM ci_catalog_onliner c, ci_onliner_articles a WHERE c.brand = '".$model."' AND a.article = '".$article."' AND a.id=c.id";
		
		$strSql = "SELECT id, category, vendor, article FROM ci_onliner_articles WHERE vendor = '".$model."' AND article = '".$article."'";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$arResult["ITEMS"][] = array(
				"id" => $row["id"],
				//"section" => $row["category"],
				"brand" => $row["vendor"],
				"model" => $row["article"],
				"name" => "",
			);
		}else{
			if($model == "Q&Q"){
				if($article[4] == "J") $article[4] = "-";
				if(strlen($article) == 9) $article = substr($article, 0, -1);
			//			if($arItem["article"][4] == "-") $arItem["article"][4] = "J";
			//			if(strlen($arItem["article"]) == 8) $arItem["article"][8] = "Y";
			}
			$strSql = "SELECT id, category, vendor, article FROM ci_onliner_articles WHERE vendor = '".$model."' AND article LIKE '%".$article."%' LIMIT 0,5";
			
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$arResult["SIMILAR_ITEMS"][] = array(
					"id" => $row["id"],
					//"section" => $row["category"],
					"brand" => $row["vendor"],
					"model" => $row["article"],
					"name" => "",
				);
			}
			if(!$arResult["SIMILAR_ITEMS"]){
				$strSql = "SELECT id, vendor, name FROM ci_onliner_articles WHERE vendor = '".$model."' AND name LIKE '%".$article."%' LIMIT 0,5";
				
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					$arResult["SIMILAR_ITEMS"][] = array(
						"id" => $row["id"],
						//"section" => $row["category"],
						"brand" => $row["vendor"],
						"model" => "",
						"name" => $row["name"],
					);
				}
				if(is_array($arResult["SIMILAR_ITEMS"]) && count($arResult["SIMILAR_ITEMS"]) == 1)
					$arResult["ITEMS"] = $arResult["SIMILAR_ITEMS"];
			}
		}
		//$strSql = "SELECT c.id as id, c.section as section, c.brand as brand, c.model as model, c.url as url, a.article as article FROM ci_catalog_onliner c JOIN ci_onliner_articles a WHERE c.brand = '".$model." AND a.article = '".$article." AND a.id=c.id LIMIT 0,5";
		
	}

}
header('Content-Type: application/json;charset=UTF-8');
echo json_encode($arResult);
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');