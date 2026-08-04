<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule('panel.manager')) return;
global $DB;
//$DB->Query("DELETE from ci_log WHERE event = 'P' ORDER BY id desc LIMIT 6", false, $err_mess.__LINE__);die;
/*
$arFilter = Array(
	"IBLOCK_ID"		=> CProSet::IB_CATALOG,
	"!PROPERTY_YANDEX_MODEL_ID" => false,
);

$res = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, false, array("ID", "PROPERTY_YANDEX_MODEL_ID"));
while($ar_fields = $res->GetNext()){

	$strSql = "SELECT * FROM ci_yandex_link WHERE bitrix_id = '".$ar_fields["ID"]."' AND yandex_id = '".$ar_fields["PROPERTY_YANDEX_MODEL_ID_VALUE"]."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if (!$row = $results->Fetch()){
		$in = array(
			"bitrix_id" => "'".$ar_fields["ID"]."'",
			"yandex_id" => "'".$ar_fields["PROPERTY_YANDEX_MODEL_ID_VALUE"]."'",
			"is_parse" => "'N'",
		);
		$DB->Insert("ci_yandex_link", $in, $err_mess.__LINE__);
	}

}

die;*/
function _gzdecode($file){
	ob_start();
	readgzfile($file);
	$d = ob_get_clean();
	return $d;
}
$limit = 1000;
$page = 6;
$start = $limit * $page;
$strSql = "SELECT * FROM ci_yandex_link WHERE is_parse='N' LIMIT {$start},{$limit}";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while($row = $results->Fetch()){
	$arFilter = Array(
		"IBLOCK_ID"		=> CProSet::IB_CATALOG,
		"ID"			=> $row["bitrix_id"],
	);
	$res = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, array("nPageSize" => 1), array("NAME"));
	if($ar_fields = $res->GetNext()){
		$arResult["ITEMS"][] = array(
			"ID" 			=> $row["bitrix_id"],
			"NAME"			=> $ar_fields["NAME"],
			"YANDEX_ID" 	=> $row["yandex_id"],
		);
	}
}

$last = CProSet::getOption("LAST_PARSE_YANDEX_ID");

$html = "";
require_once("/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/nokogiri.php");
foreach($arResult["ITEMS"] as $key => $arItem){
	$filename = "/tmp/yandex_tmp/{$arItem["YANDEX_ID"]}.txt";
	if (file_exists($filename)) {
		$html = _gzdecode($filename);

		$saw = new nokogiri($html);
		unset($html); $html = NULL;

		$ar = $saw->get('.layout__col_size_p75 .n-product-review-item')->toArray();
/*		foreach($ar as $k => $v){
			$arTmp = $v["div"][0];
			$description = $author = $date = $rating = false;
			foreach($arTmp as $key => $arItem){
				if($key == "meta"){
					foreach($arItem as $arMeta){
						if($arMeta["itemprop"] == "datePublished")
							$date = $arMeta["content"];
						if($arMeta["itemprop"] == "author")
							$author = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $arMeta["content"]);
						if($arMeta["itemprop"] == "description")
							$description = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $arMeta["content"]);
					}
				}
				if($key == "div"){
					$rating = $arItem[0]["meta"][0]["content"];
				}
			}
			if($description && $author && $date && $rating){
				$arReview = array(
					"NAME" => $author,
					"RATING" => $rating,
					"DESCRIPTION" => $description,
					"MINUS" => $minus,
					"COMMENT" => $comment,
				);
			}
			prent($arReview);
		}*/
		//$arReviewName = $saw->get('.layout__col_size_p75')->toArray();
		$arReview = false;
		foreach($ar as $k => $v){
			$name = $rating = $plus = $minus = $comment = false;
			foreach($v["div"] as $key => $arRev){
				if($key == 0){
					$date = $arRev["meta"][0]["content"];
					$date = date("d.m.Y h:i:s", strtotime($date)); 
				}
				if($arRev["class"] == "n-product-review-user i-bem" || $arRev["class"] == "n-product-review-user"){
					if(isset($arRev["span"]))
						$name = $arRev["span"][0]["#text"][0];
					elseif(isset($arRev["a"]))
						$name = $arRev["a"][0]["#text"][0];
				}
				if($arRev["class"] == "n-product-review-item__stat"){
					$rating = $arRev["div"][0]["date-rate"];
				}
			}
			//if($v["data-review-id"] == 72042569) prent($v["dl"]);
			foreach($v["dl"] as $key => $arRev){
				$col = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $arRev["dt"][0]["#text"][0]);
				$col = trim(str_replace(":", "", $col));
				if(!empty($col)){
					if($col == "Достоинства"){
						$plus = $arRev["dd"][0]["#text"][0];
					}
					if($col == "Недостатки"){
						$minus = $arRev["dd"][0]["#text"][0];
					}
					if($col == "Комментарий"){
						$comment = $arRev["dd"][0]["#text"][0];
					}
				}else{
					$comment = $arRev["dd"][0]["#text"][0];
				}
			}

			//if($comment === false) prent($v);

			if($name && $rating && (strlen($plus) > 0 || strlen($minus) > 0 || strlen($comment) > 0)){
				$name = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $name);
				$rating = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $rating);
				$plus = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $plus);
				$minus = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $minus);
				$comment = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $comment);
				$arReview[] = array(
					"NAME" => $name,
					"RATING" => $rating,
					"DATE" => $date,
					"PLUS" => $plus,
					"MINUS" => $minus,
					"COMMENT" => $comment,
				);
			}else{
				prent($arItem["YANDEX_ID"]);//$name = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $name);
				//prent($v);
			}

//			prent($arReview);
			//prent($name);prent($rating);prent($plus);prent($minus);prent($comment);
		}
		if($arReview){
			$cnt_true = $cnt_bad = $cnt_is = 0;
			foreach($arReview as $arRev){
				$xml_id_rev = md5($arRev["NAME"] . "_" . $arRev["COMMENT"] . "_" . $arItem["ID"]);
				$rsItems = CIBlockElement::GetList(array(),array('IBLOCK_ID' =>CProSet::IB_REVIEWS,'=XML_ID' => $xml_id_rev),false,false,array('ID'));
				if($revItem = $rsItems->GetNext()){
					// есть такой элемент
					$cnt_is++;
					$html .= "<p style='color: blue;'>Отзыв к элементу - " . $arItem["NAME"] . " - " . $arRev["NAME"] . " уже есть</p>";
				}else{
					//если нет то добавляем
					$EL = new CIBlockElement;
													
					if(strlen($arRev["NAME"]) > 0 && $arRev["NAME"] != "Пользователь скрыл свои данные") $author = $arRev["NAME"]; else $author = "Аноним";
					$arLoadCodeArray = Array(
						"IBLOCK_SECTION_ID" => false,          // элемент лежит в корне раздела
						"IBLOCK_ID"      	=> CProSet::IB_REVIEWS,
						"PROPERTY_VALUES"	=> array(
							"NAME" 			=> $author,
							"PLUS" 			=> $arRev["PLUS"],
							"MINUS" 		=> $arRev["MINUS"],
							"RATING" 		=> $arRev["RATING"],
							"DATE" 			=> $arRev["DATE"],
							"FLG_YANDEX"	=> "Y",
							"ITEMS"			=> $arItem["ID"],
						),
						"NAME"          	=> $arItem["NAME"] . " - " . $author,
						"XML_ID"         	=> $xml_id_rev,
						"ACTIVE"         	=> "Y",//(($rating >= 3) ? "Y" : "N"),
						"DETAIL_TEXT"    	=> $arRev["COMMENT"],
					);
//	prent($arLoadCodeArray);
					if($PRODUCT_ID = $EL->Add($arLoadCodeArray)){
						$html .= "<p style='color: green;'>Добавлен отзыв ID: ".$PRODUCT_ID.". {$arItem["NAME"]}</p>";
						$cnt_true++;
					}else{
						$cnt_bad++;
						$html .= "<p style='color: red;'>Error: ".$EL->LAST_ERROR."</p>";
					}
				}
			}
			if($cnt_true > 0){
				$DB->Update("ci_yandex_link", array("is_parse" => "'Y'"), "WHERE bitrix_id='".$arItem["ID"]."' AND yandex_id='".$arItem["YANDEX_ID"]."'", $err_mess.__LINE__);
			}
			$html = $arItem["NAME"] . " добавлено {$cnt_true}, с ошибками {$cnt_bad}, отзывов существует - {$cnt_is}";
			prent($html);
//			CLog::add2log(array("event" => "P", "text" => $html));
		}else{
			$html = "<p style='color: red;'>У товара {$arItem["NAME"]} ID - {$arItem["ID"]} YANDEX_MODEL_ID - <a href='https://market.yandex.ru/product/{$arItem["YANDEX_ID"]}/reviews?track=tabs' target='_blank'>{$arItem["YANDEX_ID"]}</a> отзывов найти не удалось</p>";
			prent($html);
//			CLog::add2log(array("event" => "P", "text" => $html));
		}
		
		
	}
}

die;