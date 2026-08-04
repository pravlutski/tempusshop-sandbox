<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
$obj = json_decode($_POST["ar_items"]);
foreach($obj as $key => $arItem){
	$arItems[] = (array) $arItem;
}
$price_id = false;
if(isset($_POST["website"]) && in_array($_POST["website"], array("ru", "by", "pl", "ya", "os", "wb", "sb", "kz","ozkz","ozti")))
	$price_id = $_POST["website"];
?>
<?if($price_id === false):?>
	<p>Выберите цену</p>
	<?die;?>
<?endif?>

<?if(!$arItems):?>
	<p>Не выбраны товары</p>
	<?die;?>
<?endif?>
<?
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$arLog = array();
	switch($price_id){
		case "ru":
			$round = -1;
			$currency = "RUB";
			$PRICE_TYPE_ID = 5;
			$url = "https://tempusshop.ru";
			$site_id = "s1";
			break;
		case "by":
			$round = -0;
			$currency = "BYN";
			$PRICE_TYPE_ID = 2;
			$url = "https://tempus.by";
			$site_id = "s2";
			break;
		case "pl":
			$round = 0;
			$currency = "PLN";
			$PRICE_TYPE_ID = 3;
			$url = "https://tempusshop.pl";
			$site_id = "s3";
			break;
		case "ya":
			$round = -1;
			$currency = "RUB";
			$PRICE_TYPE_ID = 1;
			$url = "https://tempusshop.ru";
			$site_id = "ya";
			break;
		case "os":
			$round = -1;
			$currency = "RUB";
			$url = "https://tempusshop.ru";
			break;
		case "sb":
			$round = -1;
			$currency = "RUB";
			$url = "https://tempusshop.ru";
			break;
		case "KZ":
			$round = -1;
			$currency = "KZT";
			$url = "https://tempuswatch.kz";
			break;
		case "ozkz":
			$round = -1;
			$currency = "KZT";
			$url = "https://tempuswatch.kz";
			break;
		case "ozti":
			$round = -1;
			$currency = "RUB";
			$url = "https://tempusshop.ru";
			break;
		case "wb":
			$round = -1;
			$currency = "RUB";
			$url = "https://tempusshop.ru";
			break;
		default:
			$round = -1;
			$currency = "RUB";
			$url = "https://tempusshop.ru";
			break;
	}
	$arSettings = array(
		"round" => $round
	);

	$time_start = debug_microtime_float();

	$_el = new CIBlockElement;
	foreach($arItems as $arItem){
		$class = "log-item";
		$text = "";

		/* если простой товар */
		if($arItem["skuid"] == false && $price_id != "wb" && $price_id != "os"){
			$arFilter = array(
				"IBLOCK_ID" => CProSet::IB_CATALOG,
				"ID" => $arItem["product_id"]
			);
			$res = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "ACTIVE", "PROPERTY_CML2_ARTICLE", "DETAIL_PAGE_URL"));
			if ($el = $res->GetNext()){

				$price = round($arItem["price"], $arSettings["round"]);
				$arFields = Array(
					"PRODUCT_ID" => $el["ID"],
					"CATALOG_GROUP_ID" => $PRICE_TYPE_ID,
					"PRICE" => $price,
					"CURRENCY" => $currency,
				);
				$p_res = CPrice::GetList(
					array(),
					array(
						"PRODUCT_ID" => $el["ID"],
						"CATALOG_GROUP_ID" => $PRICE_TYPE_ID
					)
				);

				if($price_id == "by"){
					$strSql = "SELECT id FROM ci_price WHERE model = '{$el["PROPERTY_CML2_ARTICLE_VALUE"]}' AND supplier_id = '44'";
					$results = $DB->Query($strSql, false, $err_mess.__LINE__);
					if ($row = $results->Fetch())
						$class .= " warning";
				}
				if ($arr = $p_res->Fetch()){
					$text = "В товаре <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> обновлена цена - " . $price;
					CPrice::Update($arr["ID"], $arFields);
				}else{
					$text = "На товар <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> добавлена цена - " . $price;
					CPrice::Add($arFields);
				}

				//$cache_manager = Bitrix\Main\Application::getInstance()->getTaggedCache();
				//$cache_manager->ClearByTag('product_' . $el['ID']);

				CExchange::updateProduct($el["ID"], CProSet::IB_CATALOG);
//$time_end = debug_microtime_float() - $time_start;
//AddMessage2Log($time_end);
				//обновляем данные во временной таблице
				$strSql = "SELECT * FROM ci_price_catalog WHERE product_id = '{$arItem["product_id"]}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){

					$arPrice = getAllPrice($arItem["product_id"]);
					file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/analysis/priceas.txt", print_r($arPrice, true) . "\r\n", FILE_APPEND);
					if($price_id == "ru" && is_array($arPrice["PRICE_RU"])){
						$in = array(
							"price_ru" => $arPrice["PRICE_RU"]["PRICE"],
							"price_discount_ru" => $arPrice["PRICE_RU"]["DISCOUNT_PRICE"],
							"set_price_ru" => false,
						);
					}elseif($price_id == "by" && is_array($arPrice["PRICE_BY"])){
						$in = array(
							"price_by" => $arPrice["PRICE_BY"]["PRICE"],
							"price_discount_by" => $arPrice["PRICE_BY"]["DISCOUNT_PRICE"],
							"set_price_by" => false,
						);
					}elseif($price_id == "pl" && is_array($arPrice["PRICE_PL"])){
						$in = array(
							"price_pl" => $arPrice["PRICE_PL"]["PRICE"],
							"price_discount_pl" => $arPrice["PRICE_PL"]["DISCOUNT_PRICE"],
							"set_price_pl" => false,
						);
					}elseif($price_id == "ya" && is_array($arPrice["PRICE_YA"])){
						$in = array(
							"price_ya" => $arPrice["PRICE_YA"]["PRICE"],
							"price_discount_ya" => $arPrice["PRICE_YA"]["DISCOUNT_PRICE"],
							"set_price_ya" => false,
						);
					}
					/*$ar = AHCatalog::OnGetOptimalPrice($arItem["product_id"], 1, array(), "N", array(), $site_id);
					if($price_id == "ru"){
						$b_price = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], "RUB");
						$b_price = round($b_price, -1);
						$b_price_dis = CCurrencyRates::ConvertCurrency($ar["RESULT_PRICE"]["DISCOUNT_PRICE"], $ar["RESULT_PRICE"]["CURRENCY"], "RUB");
						$b_price_dis = round($b_price_dis, -1);
						$in = array(
							"price_ru" => $b_price,
							"price_discount_ru" => $b_price_dis,
							"set_price_ru" => false,
						);
					}elseif($price_id == "by"){
						$b_price = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], "BYN");
						$b_price = round($b_price, 0);
						$b_price_dis = CCurrencyRates::ConvertCurrency($ar["RESULT_PRICE"]["DISCOUNT_PRICE"], $ar["RESULT_PRICE"]["CURRENCY"], "BYN");
						$b_price_dis = round($b_price_dis, 0);
						$in = array(
							"price_by" => $b_price,
							"price_discount_by" => $b_price_dis,
							"set_price_by" => false,
						);
					}elseif($price_id == "pl"){
						$b_price = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], "PLN");
						$b_price = round($b_price, 0);
						$b_price_dis = CCurrencyRates::ConvertCurrency($ar["RESULT_PRICE"]["DISCOUNT_PRICE"], $ar["RESULT_PRICE"]["CURRENCY"], "PLN");
						$b_price_dis = round($b_price_dis, 0);
						$in = array(
							"price_pl" => $b_price,
							"price_discount_pl" => $b_price_dis,
							"set_price_pl" => false,
						);
					}elseif($price_id == "ya"){
						$b_price = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], "RUB");
						$b_price = round($b_price, -1);
						$b_price_dis = CCurrencyRates::ConvertCurrency($ar["RESULT_PRICE"]["DISCOUNT_PRICE"], $ar["RESULT_PRICE"]["CURRENCY"], "RUB");
						$b_price_dis = round($b_price_dis, -1);
						$in = array(
							"price_ya" => $b_price,
							"price_discount_ya" => $b_price_dis,
							"set_price_ya" => false,
						);
					}*/
					$in["timestamp"] = "'".date("Y-m-d H:i:s")."'";
					$DB->Update("ci_price_catalog", $in, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);
					//обновляем элемент чтоб сработали все события
					//$rs = $_el->Update($el["ID"], array("ACTIVE" => $el["ACTIVE"]));

				}
//AddMessage2Log($time_end);
				//удаляем папку с композитом
				//clearDirCompositeCache($el["DETAIL_PAGE_URL"]);
			}else{
				$text = "Товар с ID - " . $arItem["product_id"] . " не найден";
			}
		}elseif($price_id == "wb"){
			$arFilter = array(
				"IBLOCK_ID" => CProSet::IB_CATALOG,
				"ID" => $arItem["product_id"]
			);
			$res = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "ACTIVE", "PROPERTY_CML2_ARTICLE", "DETAIL_PAGE_URL"));
			if ($el = $res->GetNext()){

//$time_end = debug_microtime_float() - $time_start;
//AddMessage2Log($time_end);

				$price = round($arItem["price"], $arSettings["round"]);

				CIBlockElement::SetPropertyValuesEx($el["ID"], false, array("WBPRICE" => $price));

				//обновляем данные во временной таблице
				$strSql = "SELECT * FROM ci_price_catalog WHERE product_id = '{$arItem["product_id"]}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){

					$in = array(
						"price_wb" => $price,
					);

					if ($row["price_wb"] != $price){
						$text = "В товаре <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> обновлена цена - {$row["price_wb"]} >> " . $price;
					}else{
						$text = "На товар <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> добавлена цена - " . $price;
					}

					$in["timestamp"] = "'".date("Y-m-d H:i:s")."'";
					$DB->Update("ci_price_catalog", $in, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);

				}

			}else{
				$text = "Товар с ID - " . $arItem["product_id"] . " не найден";
			}
		}elseif($price_id == "os"){
			$arFilter = array(
				"IBLOCK_ID" => CProSet::IB_CATALOG,
				"ID" => $arItem["product_id"]
			);
			$res = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "ACTIVE", "PROPERTY_CML2_ARTICLE", "DETAIL_PAGE_URL"));
			if ($el = $res->GetNext()){
				$price = round($arItem["price"], $arSettings["round"]);

				CIBlockElement::SetPropertyValuesEx($el["ID"], false, array("OZSB_PRICE" => $price));

				//обновляем данные во временной таблице
				$strSql = "SELECT * FROM ci_price_catalog WHERE product_id = '{$arItem["product_id"]}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){

					$in = array(
						"price_os" => $price,
					);

					if ($row["price_os"] != $price){
						$text = "В товаре <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> обновлена цена - {$row["price_os"]} >> " . $price;
					}else{
						$text = "На товар <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> добавлена цена - " . $price;
					}

					$in["timestamp"] = "'".date("Y-m-d H:i:s")."'";
					$DB->Update("ci_price_catalog", $in, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);

				}

			}else{
				$text = "Товар с ID - " . $arItem["product_id"] . " не найден";
			}
		}elseif($price_id == "sb"){
			$arFilter = array(
				"IBLOCK_ID" => CProSet::IB_CATALOG,
				"ID" => $arItem["product_id"]
			);
			$res = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "ACTIVE", "PROPERTY_CML2_ARTICLE", "DETAIL_PAGE_URL"));
			if ($el = $res->GetNext()){
				$price = round($arItem["price"], $arSettings["round"]);

				CIBlockElement::SetPropertyValuesEx($el["ID"], false, array("SBER_PRICE" => $price));

				//обновляем данные во временной таблице
				$strSql = "SELECT * FROM ci_price_catalog WHERE product_id = '{$arItem["product_id"]}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){

					$in = array(
						"price_sb" => $price,
					);

					if ($row["price_sb"] != $price){
						$text = "В товаре <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> обновлена цена - {$row["price_sb"]} >> " . $price;
					}else{
						$text = "На товар <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> добавлена цена - " . $price;
					}

					$in["timestamp"] = "'".date("Y-m-d H:i:s")."'";
					$DB->Update("ci_price_catalog", $in, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);

				}

			}else{
				$text = "Товар с ID - " . $arItem["product_id"] . " не найден";
			}
		} elseif($price_id == "kz"){
			$arFilter = array(
				"IBLOCK_ID" => CProSet::IB_CATALOG,
				"ID" => $arItem["product_id"]
			);
			$res = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "ACTIVE", "PROPERTY_CML2_ARTICLE", "DETAIL_PAGE_URL"));
			if ($el = $res->GetNext()){
				$price = round($arItem["price"], $arSettings["round"]);

				CIBlockElement::SetPropertyValuesEx($el["ID"], false, array("PRICE_KZ" => $price));

				//обновляем данные во временной таблице
				$strSql = "SELECT * FROM ci_price_catalog WHERE product_id = '{$arItem["product_id"]}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){

					$in = array(
						"price_kz" => $price,
					);

					if ($row["price_kz"] != $price){
						$text = "В товаре <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> обновлена цена - {$row["price_kz"]} >> " . $price;
					}else{
						$text = "На товар <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> добавлена цена - " . $price;
					}

					$in["timestamp"] = "'".date("Y-m-d H:i:s")."'";
					$DB->Update("ci_price_catalog", $in, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);

				}

			}else{
				$text = "Товар с ID - " . $arItem["product_id"] . " не найден";
			}
		}elseif($price_id == "ozkz"){
		  $arFilter = array(
		    "IBLOCK_ID" => CProSet::IB_CATALOG,
		    "ID" => $arItem["product_id"]
		  );
		  $res = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "ACTIVE", "PROPERTY_CML2_ARTICLE", "DETAIL_PAGE_URL"));
		  if ($el = $res->GetNext()){
		    $price = round($arItem["price"], $arSettings["round"]);

		    CIBlockElement::SetPropertyValuesEx($el["ID"], false, array("PRICE_OZKZ" => $price));

		    //обновляем данные во временной таблице
		    $strSql = "SELECT * FROM ci_price_catalog WHERE product_id = '{$arItem["product_id"]}'";
		    $results = $DB->Query($strSql, false, $err_mess.__LINE__);
		    if ($row = $results->Fetch()){

		      $in = array(
		        "price_ozkz" => $price,
		      );

		      if ($row["price_ozkz"] != $price){
		        $text = "В товаре <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> обновлена цена - {$row["price_ozkz"]} >> " . $price;
		      }else{
		        $text = "На товар <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> добавлена цена - " . $price;
		      }

		      $in["timestamp"] = "'".date("Y-m-d H:i:s")."'";
		      $DB->Update("ci_price_catalog", $in, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);

		    }

		  }else{
		    $text = "Товар с ID - " . $arItem["product_id"] . " не найден";
		  }
		}elseif($price_id == "ozti"){
		  $arFilter = array(
		    "IBLOCK_ID" => CProSet::IB_CATALOG,
		    "ID" => $arItem["product_id"]
		  );
		  $res = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "ACTIVE", "PROPERTY_CML2_ARTICLE", "DETAIL_PAGE_URL"));
		  if ($el = $res->GetNext()){
		    $price = round($arItem["price"], $arSettings["round"]);

		    CIBlockElement::SetPropertyValuesEx($el["ID"], false, array("PRICE_OZTI" => $price));

		    //обновляем данные во временной таблице
		    $strSql = "SELECT * FROM ci_price_catalog WHERE product_id = '{$arItem["product_id"]}'";
		    $results = $DB->Query($strSql, false, $err_mess.__LINE__);
		    if ($row = $results->Fetch()){

		      $in = array(
		        "price_ozti" => $price,
		      );

		      if ($row["price_ozti"] != $price){
		        $text = "В товаре <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> обновлена цена - {$row["price_ozti"]} >> " . $price;
		      }else{
		        $text = "На товар <a href='".$url."".$el["DETAIL_PAGE_URL"]."' target='_blank'>" . $el["PROPERTY_CML2_ARTICLE_VALUE"] . "</a> добавлена цена - " . $price;
		      }

		      $in["timestamp"] = "'".date("Y-m-d H:i:s")."'";
		      $DB->Update("ci_price_catalog", $in, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);

		    }

		  }else{
		    $text = "Товар с ID - " . $arItem["product_id"] . " не найден";
		  }
		}


		$arLog[] = "<div class='{$class}'>" . $text . "</div>";
		CLog::add2log(array("event" => "UI", "text" => $text, "price_id" => $price_id));

//$time_end = debug_microtime_float() - $time_start;
//AddMessage2Log("1- ". $time_end);
	}

	foreach($arLog as $log){
		echo $log;
		//echo "<p style='margin: 0 0 3px;font-size:10px;'>" . $log . "</p>";
	}
}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
