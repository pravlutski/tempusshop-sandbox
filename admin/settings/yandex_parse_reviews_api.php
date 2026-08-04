<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule('panel.manager')) return;

$filepath = $_SERVER["DOCUMENT_ROOT"] . "/dev/yandex_model_id.csv";
$filename = "yandex_model_id.csv";
$fp = fopen($filepath, 'w');
$arFilter = Array(
	"IBLOCK_ID"		=> CProSet::IB_CATALOG,
	"!PROPERTY_YANDEX_MODEL_ID" => false,
);

$res = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, false, array("ID", "NAME", "PROPERTY_YANDEX_MODEL_ID"));
while($arFields = $res->GetNext()){
	$arItem[0] = $arFields["ID"];
	$arItem[1] = $arFields["NAME"];
	$arItem[2] = $arFields["PROPERTY_YANDEX_MODEL_ID_VALUE"];
	$str_csv = implode(";", $arItem) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
}

die;
/*
include_once('/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/nokogiri.php');
$url = "https://market.yandex.ru/product/13578853/reviews?track=tabs";
$temp = file_get_contents($url);
$saw = new nokogiri($temp);
$arPage = $saw->get('.reviews-layout .n-product-review-item')->toArray();
prent($arPage);die;
if($arPage){
	if(count($arRes) > 0)
					$arRes = array_merge($this->parseTable($arPage, $element["BRAND"]), $arRes);
				else
					$arRes = $this->parseTable($arPage, $element["BRAND"]);
			}
*/
$last = CProSet::getOption("LAST_PARSE_YANDEX_ID");
$arFilter = Array(
	"IBLOCK_ID"		=> CProSet::IB_CATALOG,
//	"SECTION_ID"	=> 223,
//	"INCLUDE_SUBSECTIONS" => "Y",
	"!PROPERTY_YANDEX_MODEL_ID" => false,
	">CATALOG_QUANTITY" => 0,
//	"ID" => 1001, 
	">ID"			=> $last,
);

$res = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, array("nPageSize" => 1), array("ID", "NAME", "XML_ID", "PROPERTY_YANDEX_MODEL_ID"));
while($ar_fields = $res->GetNext()){
	$arResult["ITEMS"][] = $ar_fields;
}
//prent($arResult);die; 
 
$html = "";
foreach($arResult["ITEMS"] as $key => $arItem){
	$yandexRev = new YandexApi();
	$arReviews = $yandexRev->get_reviews_model($arItem["PROPERTY_YANDEX_MODEL_ID_VALUE"]);
	if(count($arReviews) > 0){
		foreach($arReviews as $k => $arRev){
			$date = date("d.m.Y h:i:s", $arRev["date"] / 1000);//$arRev["date"];
			$xml_id_rev = md5($arRev["author"] . "_" . $arRev["text"] . "_" . $arItem["ID"]);
			$rsItems = CIBlockElement::GetList(array(),array('IBLOCK_ID' =>CProSet::IB_REVIEWS,'=XML_ID' => $xml_id_rev),false,false,array('ID'));
			if($revItem = $rsItems->GetNext()){
				// есть такой элемент
				$html .= "<p style='color: blue;'>Отзыв к элементу - " . $arItem["NAME"] . " - " . $arRev["author"] . " уже есть</p>";
			}else{
				//если нет то добавляем
				$EL = new CIBlockElement;
												
				switch($arRev["grade"]){
					case 2: $rating = 5; break;
					case 1: $rating = 4; break;
					case 0: $rating = 3; break;
					case -1: $rating = 2; break;
					case -2: $rating = 1; break;
				}
				if(strlen($arRev["author"]) > 0) $author = $arRev["author"]; else $author = "Аноним";
				$arLoadCodeArray = Array(
					"IBLOCK_SECTION_ID" => false,          // элемент лежит в корне раздела
					"IBLOCK_ID"      	=> CProSet::IB_REVIEWS,
					"PROPERTY_VALUES"	=> array(
						"NAME" 			=> $author,
						"PLUS" 			=> $arRev["pro"],
						"MINUS" 		=> $arRev["contra"],
						"RATING" 		=> $rating,
						"DATE" 			=> $date,
						"FLG_YANDEX"	=> "Y",
						"ITEMS"			=> $arItem["ID"],
					),
					"NAME"          	=> $arItem["NAME"] . " - " . $author,
					"XML_ID"         	=> $xml_id_rev,
					"ACTIVE"         	=> "Y",//(($rating >= 3) ? "Y" : "N"),
					"DETAIL_TEXT"    	=> $arRev["text"],
				);

				if($PRODUCT_ID = $EL->Add($arLoadCodeArray)){
					$html .= "<p style='color: green;'>Добавлен отзыв ID: ".$PRODUCT_ID."</p>";
					$cnt++;
				}else{
					$html .= "<p style='color: red;'>Error: ".$EL->LAST_ERROR."</p>";
				}
			}
			CProSet::setOption("LAST_PARSE_YANDEX_ID", $arItem["ID"]);
		}
	}
	$arError = $yandexRev->arError;
	foreach($arError as $k => $v)
		$html .= "<p style='color: red;'>{$arItem["NAME"]} - {$arItem["ID"]} {$v}</p>";
	unset($yandexRev);
//	prent($arError);
}
prent($html);
die;

//$arReviews = $yandexRev->get_reviews_model(13578853);


//prent($arReport);

//header('Content-Type: application/json;charset=UTF-8');
//die();
