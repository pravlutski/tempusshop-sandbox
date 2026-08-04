<?
use Bitrix\Main\Loader;
class TsIblock{
	// создаем обработчик события "OnAfterIBlockSectionUpdate"
	public static function OnAfterIBlockSectionUpdate(&$arFields){
        if($arFields["IBLOCK_ID"] == CProSet::IB_CATALOG && !in_array($arFields["ID"], array(375,376,370))){
			$arFilter = Array('IBLOCK_ID'=>CProSet::IB_CATALOG, 'ID' => $arFields["ID"]);
			$db_list = CIBlockSection::GetList(Array(), $arFilter, false,array("ID", "UF_SITE_ID", "UF_AVAIL_SWISS"));
			if($ar_result = $db_list->GetNext()){
				$ar_site_id = $ar_result["UF_SITE_ID"];
				$pr_swiss = $ar_result["UF_AVAIL_SWISS"];
				/* обновляем свойство у подразделов */
				$arSubFilter = Array('IBLOCK_ID'=>CProSet::IB_CATALOG, 'GLOBAL_ACTIVE'=>'Y', 'SECTION_ID' => $ar_result["ID"]);
				$db_sublist = CIBlockSection::GetList(Array(), $arSubFilter, false,array("NAME", "CODE", "ID"));
				while($res = $db_sublist->GetNext()){
					$bs = new CIBlockSection;
					$in = Array(
						"UF_SITE_ID" => $ar_site_id,
						"UF_AVAIL_SWISS" => $pr_swiss,
					);

					$bs->Update($res["ID"], $in);
				}
				/* обновляем свойство у товаров */
				$arSITE = false;
				foreach($ar_site_id as $site){
					if($site == 65)
						$arSITE[] = "s1";
					elseif($site == 66)
						$arSITE[] = "s2";
					elseif($site == 67)
						$arSITE[] = "s3";
				}
				$arFilterEl = Array("IBLOCK_ID" => CProSet::IB_CATALOG, "SECTION_ID" => $ar_result["ID"]);//, "INCLUDE_SUBSECTIONS" => "Y");
				$resEl = CIBlockElement::GetList(Array(), $arFilterEl, false, false, array("ID"));
				while($obEl = $resEl->GetNextElement()){
					$arEl = $obEl->GetFields();
					CIBlockElement::SetPropertyValueCode($arEl["ID"], "SITE_ID", $arSITE);
				}
			}
        }
		//prent($ar_site_id);
		//return $arFields;
    }


	public static function setPropAvailable(&$arFields){
		if(!Loader::includeModule('iblock')) return;
		//return $arFields;
		/* не обновляем свойства если редиктируется раздел для мужчин, женщин, унисекс */
		/*
		$arSec = $arFields["IBLOCK_SECTION"];
		if(($key = array_search(375, $arSec)) !== FALSE){
			unset($arSec[$key]);
		}
		if(($key = array_search(376, $arSec)) !== FALSE){
			unset($arSec[$key]);
		}
		if(($key = array_search(370, $arSec)) !== FALSE){
			unset($arSec[$key]);
		}
		$arSec = array_values($arSec);
		$IBLOCK_SECTION = intval($arSec[0]);

		if(!$IBLOCK_SECTION && $arFields["ID"] > 0){
			global $DB;
			$rs = $DB->Query("SELECT IBLOCK_SECTION_ID FROM b_iblock_element WHERE ID = '{$arFields["ID"]}'", false, $err_mess.__LINE__)->Fetch();
			if ($rs){
				$IBLOCK_SECTION = intval($rs["IBLOCK_SECTION_ID"]);
			}
		}
		if($arFields["IBLOCK_ID"] == CProSet::IB_CATALOG && $arFields["ID"] > 0 && $IBLOCK_SECTION > 0){
			$arFilter = Array('IBLOCK_ID'=>CProSet::IB_CATALOG, 'ID' => $IBLOCK_SECTION);
			$db_list = CIBlockSection::GetList(Array("NAME"=>"asc"), $arFilter, false,array("ID", "UF_SITE_ID"));
			if($ar_result = $db_list->GetNext()){
				$ar_site_id = $ar_result["UF_SITE_ID"];
				// обновляем свойство у товаров
				$arSITE = false;
				foreach($ar_site_id as $site){
					if($site == 65)
						$arSITE[] = "s1";
					elseif($site == 66)
						$arSITE[] = "s2";
					elseif($site == 67)
						$arSITE[] = "s3";
				}
				CIBlockElement::SetPropertyValueCode($arFields["ID"], "SITE_ID", $arSITE);
			}
		}*/
		//пишим цены в свойство/ и доступность на сайте
		if($arFields["IBLOCK_ID"] == CProSet::IB_CATALOG && $arFields["ID"] > 0){
			$arFilter = Array(
				"IBLOCK_ID" => CProSet::IB_CATALOG,
				"ID" => $arFields["ID"],
			);
			//AddMessage2Log($arFilter);
			$result = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "CATALOG_GROUP_1", "CATALOG_GROUP_2", "CATALOG_GROUP_3", "CATALOG_GROUP_5"));
			if($el = $result->GetNext()){
				if($el["CATALOG_PRICE_5"]){
					$discounts = \CCatalogDiscount::GetDiscount($el["ID"], CProSet::IB_CATALOG, array(5), array(2), 'N', "s1", array());
					$discountPrice = \CCatalogProduct::CountPriceWithDiscount($el["CATALOG_PRICE_5"], $el["CATALOG_CURRENCY_5"], $discounts);
				//	CIBlockElement::SetPropertyValuesEx($el["ID"], CProSet::IB_CATALOG, array("MINIMUM_PRICE" => $discountPrice, "MAXIMUM_PRICE" => $el["CATALOG_PRICE_1"]));
					$arSet["MINIMUM_PRICE"] = round($discountPrice, -1);
					$arSet["MAXIMUM_PRICE"] = round($el["CATALOG_PRICE_5"], -1);
					//prent($arSet);die;
				}else{
					$arSet["MINIMUM_PRICE"] = $arSet["MAXIMUM_PRICE"] = false;
				}

				if($el["CATALOG_PRICE_2"]){
					$discounts = \CCatalogDiscount::GetDiscount($el["ID"], CProSet::IB_CATALOG, array(2), array(2), 'N', "s2" ,array());
					$discountPrice = \CCatalogProduct::CountPriceWithDiscount($el["CATALOG_PRICE_2"], $el["CATALOG_CURRENCY_2"], $discounts);
				//	CIBlockElement::SetPropertyValuesEx($el["ID"], CProSet::IB_CATALOG, array("MINIMUM_PRICE_RB" => $discountPrice, "MAXIMUM_PRICE_RB" => $el["CATALOG_PRICE_2"]));
					$arSet["MINIMUM_PRICE_RB"] = round($discountPrice, 0);
					$arSet["MAXIMUM_PRICE_RB"] = round($el["CATALOG_PRICE_2"], 0);
				}else{
					$arSet["MINIMUM_PRICE_RB"] = $arSet["MAXIMUM_PRICE_RB"] = false;
				}

				if($el["CATALOG_PRICE_3"]){
					$discounts = \CCatalogDiscount::GetDiscount($el["ID"], CProSet::IB_CATALOG, array(3), array(2), 'N', "s3" ,array());
					$discountPrice = \CCatalogProduct::CountPriceWithDiscount($el["CATALOG_PRICE_3"], $el["CATALOG_CURRENCY_3"], $discounts);
				//	CIBlockElement::SetPropertyValuesEx($el["ID"], CProSet::IB_CATALOG, array("MINIMUM_PRICE_PL" => $discountPrice, "MAXIMUM_PRICE_PL" => $el["CATALOG_PRICE_3"]));
					$arSet["MINIMUM_PRICE_PL"] = round($discountPrice, 0);
					$arSet["MAXIMUM_PRICE_PL"] = round($el["CATALOG_PRICE_3"], 0);
				}else{
					$arSet["MINIMUM_PRICE_PL"] = $arSet["MAXIMUM_PRICE_PL"] = false;
				}

				//prent($el["ID"]);
				//prent(CProSet::IB_CATALOG);
				//prent($arSet);die;
				CIBlockElement::SetPropertyValuesEx($el["ID"], CProSet::IB_CATALOG, $arSet);
				//AddMessage2Log($arSet);
				//AddMessage2Log($el["ID"]);
				//AddMessage2Log(CProSet::IB_CATALOG);
			}

			if(class_exists('\Bitrix\Iblock\PropertyIndex\Manager')){
				\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(CProSet::IB_CATALOG, $el["ID"]);
			}
			$arFields["SET_PRICES"] = $arSet;
		}
		/*
		if($arFields["IBLOCK_ID"] == CProSet::IB_CATALOG && $arFields["ID"] > 0 && 1==2){
			//AddMessage2Log($arFields);
			$ar = AHCatalog::OnGetOptimalPrice($arFields["ID"], 1, array(), "N", array(), "s1");
			$b_price = $b_price_n = 0;//false;
			if($ar["RESULT_PRICE"]["DISCOUNT_PRICE"]){
				if($ar["RESULT_PRICE"]["CURRENCY"] != "RUB"){
					$b_price = CCurrencyRates::ConvertCurrency($ar["RESULT_PRICE"]["DISCOUNT_PRICE"], $ar["RESULT_PRICE"]["CURRENCY"], "RUB");
				}else{
					$b_price = $ar["RESULT_PRICE"]["DISCOUNT_PRICE"];
				}
			}elseif($ar["PRICE"]["PRICE"]){
				if($ar["PRICE"]["CURRENCY"] != "RUB"){
					$b_price = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], "RUB");
				}else{
					$b_price = $ar["PRICE"]["PRICE"];
				}
			}
			if($ar["PRICE"]["PRICE"]){
				if($ar["PRICE"]["CURRENCY"] != "RUB"){
					$b_price_n = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], "RUB");
				}else{
					$b_price_n = $ar["PRICE"]["PRICE"];
				}
			}
			//if($b_price > 0){
				$b_price = round($b_price, -1);
				$b_price_n = round($b_price_n, -1);
				//CIBlockElement::SetPropertyValueCode($arFields["ID"], "MINIMUM_PRICE", $b_price);
				CIBlockElement::SetPropertyValuesEx($arFields["ID"], CProSet::IB_CATALOG, array("MINIMUM_PRICE" => $b_price, "MAXIMUM_PRICE" => $b_price_n));
			//}
			///* BY
			$b_price = $b_price_n = 0;//false;
			$ar = AHCatalog::OnGetOptimalPrice($arFields["ID"], 1, array(), "N", array(), "s2");

			if($ar["RESULT_PRICE"]["DISCOUNT_PRICE"]){
				if($ar["RESULT_PRICE"]["CURRENCY"] != "BYN"){
					$b_price = CCurrencyRates::ConvertCurrency($ar["RESULT_PRICE"]["DISCOUNT_PRICE"], $ar["RESULT_PRICE"]["CURRENCY"], "BYN");
				}else{
					$b_price = $ar["RESULT_PRICE"]["DISCOUNT_PRICE"];
				}
			}elseif($ar["PRICE"]["PRICE"]){
				if($ar["PRICE"]["CURRENCY"] != "BYN"){
					$b_price = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], "BYN");
				}else{
					$b_price = $ar["PRICE"]["PRICE"];
				}
			}
			if($ar["PRICE"]["PRICE"]){
				if($ar["PRICE"]["CURRENCY"] != "BYN"){
					$b_price_n = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], "BYN");
				}else{
					$b_price_n = $ar["PRICE"]["PRICE"];
				}
			}
			//if($b_price > 0){
				$b_price = round($b_price, 0);
				$b_price_n = round($b_price_n, 0);
				//CIBlockElement::SetPropertyValueCode($arFields["ID"], "MINIMUM_PRICE_RB", $b_price);
				CIBlockElement::SetPropertyValuesEx($arFields["ID"], CProSet::IB_CATALOG, array("MINIMUM_PRICE_RB" => $b_price, "MAXIMUM_PRICE_RB" => $b_price_n));
			//}

			///* PL
			$b_price = $b_price_n = 0;//false;
			$ar = AHCatalog::OnGetOptimalPrice($arFields["ID"], 1, array(), "N", array(), "s3");

			if($ar["RESULT_PRICE"]["DISCOUNT_PRICE"]){
				if($ar["RESULT_PRICE"]["CURRENCY"] != "PLZ"){
					$b_price = CCurrencyRates::ConvertCurrency($ar["RESULT_PRICE"]["DISCOUNT_PRICE"], $ar["RESULT_PRICE"]["CURRENCY"], "PLZ");
				}else{
					$b_price = $ar["RESULT_PRICE"]["DISCOUNT_PRICE"];
				}
			}elseif($ar["PRICE"]["PRICE"]){
				if($ar["PRICE"]["CURRENCY"] != "PLZ"){
					$b_price = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], "PLZ");
				}else{
					$b_price = $ar["PRICE"]["PRICE"];
				}
			}
			if($ar["PRICE"]["PRICE"]){
				if($ar["PRICE"]["CURRENCY"] != "PLZ"){
					$b_price_n = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], "PLZ");
				}else{
					$b_price_n = $ar["PRICE"]["PRICE"];
				}
			}
			//if($b_price > 0){
				$b_price = round($b_price, 0);
				$b_price_n = round($b_price_n, 0);
				//CIBlockElement::SetPropertyValueCode($arFields["ID"], "MINIMUM_PRICE_RB", $b_price);
				CIBlockElement::SetPropertyValuesEx($arFields["ID"], CProSet::IB_CATALOG, array("MINIMUM_PRICE_PL" => $b_price, "MAXIMUM_PRICE_PL" => $b_price_n));
			//}
		}*/
	}

	// создаем обработчик события "OnAfterIBlockSectionAdd". После добавления смотрим родителя и устанавливаем свойства

	public static function OnAfterIBlockSectionAdd(&$arFields){
		/*
        if($arFields["IBLOCK_ID"] == CProSet::IB_CATALOG && $arFields["ID"] > 0 && !in_array($arFields["IBLOCK_SECTION_ID"], array(375,376,370))){
			$arFilter = Array('IBLOCK_ID'=>CProSet::IB_CATALOG, 'ID' => $arFields["IBLOCK_SECTION_ID"]);
			$db_list = CIBlockSection::GetList(Array(), $arFilter, false,array("ID", "UF_SITE_ID", "UF_AVAIL_SWISS"));
			if($ar_result = $db_list->GetNext()){
				$ar_site_id = $ar_result["UF_SITE_ID"];
				$pr_swiss = $ar_result["UF_AVAIL_SWISS"];
				// обновляем свойство у созданного раздела
				$bs = new CIBlockSection;
				$in = Array(
					"UF_SITE_ID" => $ar_site_id,
					"UF_AVAIL_SWISS" => $pr_swiss,
				);

				$bs->Update($arFields["ID"], $in);
			}
		}*/

	}

	public static function OnBeforeIBlockSectionAdd(&$arFields){
		// проверка Внутреннего кода и добавления раздела для обмена
		if($arFields["IBLOCK_ID"] == CProSet::IB_CATALOG && CModule::IncludeModule("iblock")){
			if(!$arFields["XML_ID"]){
				$GLOBALS['APPLICATION']->ThrowException("Внешний код обязательное поле");
				return false;
			}else{
				//смотрим нет ли уже такого внешнего кода
				$arFilter = array('IBLOCK_ID' => CProSet::IB_CATALOG, 'XML_ID' => $arFields["XML_ID"]);
				$rsSect = CIBlockSection::GetList(array(),$arFilter);
				if ($arSect = $rsSect->GetNext()){
					$GLOBALS['APPLICATION']->ThrowException("Внешний код используется в другом разделе. NAME - {$arSect["NAME"]}, ID - {$arSect["ID"]}. Задайте другой уникальный код");
					return false;
				}
			}
			//добавляем с таблицу ci_sync. для обмена с PW
			/*$arSync = array(
				"XML_ID" => $arFields["XML_ID"],
				"TYPE" => "SECTION",
				"ACTION" => "ADD",
			);
			CSyncPW::addSync($arSync);*/
		}
	}

	public static function OnBeforeIBlockSectionUpdate(&$arFields){
		global $APPLICATION;
		if($arFields["IBLOCK_ID"] == CProSet::IB_CATALOG && CModule::IncludeModule("iblock")){

			// ищем если не пришел
			if(!$arFields["XML_ID"] && $arFields["ID"]){
				$arFilter = array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $arFields["ID"]);
				$rsSect = CIBlockSection::GetList(array(),$arFilter);
				if ($arSect = $rsSect->GetNext()){
					$arFields["XML_ID"] = $arSect["XML_ID"];
					//file_put_contents("/home/bitrix/logs/OnBeforeIBlockSectionUpdate.txt", print_r($arSect, true));
				}
			}


			if(!$arFields["XML_ID"]){
				$GLOBALS['APPLICATION']->ThrowException("Внешний код обязательное поле");
				return false;
			}else{
				//смотрим нет ли уже такого внешнего кода
				$arFilter = array('IBLOCK_ID' => CProSet::IB_CATALOG, '!ID' => $arFields["ID"], 'XML_ID' => $arFields["XML_ID"]);
				$rsSect = CIBlockSection::GetList(array(),$arFilter);
				if ($arSect = $rsSect->GetNext()){
					$GLOBALS['APPLICATION']->ThrowException("Внешний код используется в другом разделе. NAME - {$arSect["NAME"]}, ID - {$arSect["ID"]}. Задайте другой уникальный код");
					return false;
				}
			}
			//добавляем с таблицу ci_sync. для обмена с PW
			/*$arSync = array(
				"XML_ID" => $arFields["XML_ID"],
				"TYPE" => "SECTION",
				"ACTION" => "UPDATE",
			);
			CSyncPW::addSync($arSync);*/
		}
	}

	public static function OnBeforeIBlockSectionDelete($ID){
		if($ID > 0){
			$arFilter = array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $ID);
			$rsSect = CIBlockSection::GetList(array(),$arFilter);
			if ($arSect = $rsSect->GetNext()){
				//добавляем с таблицу ci_sync. для обмена с PW
				/*$arSync = array(
					"XML_ID" => $arSect["XML_ID"],
					"TYPE" => "SECTION",
					"ACTION" => "DELETE",
				);
				CSyncPW::addSync($arSync);*/
			}
		}

	}

	public static function OnBeforeIBlockElementDelete($ID){
		if($ID > 0){
			/*
			$arFilter = array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $ID);
			$arSelect = array("ID","XML_ID");
			$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
			if($arFld = $res->GetNext()){
				//добавляем с таблицу ci_sync. для обмена с PW
				$arSync = array(
					"XML_ID" => $arFld["XML_ID"],
					"TYPE" => "ELEMENT",
					"ACTION" => "DELETE",
				);
				CSyncPW::addSync($arSync);
			}
			*/
			global $CACHE_MANAGER;
			$CACHE_MANAGER->ClearByTag("product_" . $ID);
		}

	}

	public static function OnAfterIBlockElementUpdate(&$arFields){
		if (!Loader::includeModule('iblock')) return;
		
		$arSkipUrl = [
			"/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/calculateSort.php",
			"/admin/content/add-apply/index.php", 
		];
		
		if (
			$GLOBALS['RABBITMQ_SKIP_ACTION'] !== true && 
			$arFields["IBLOCK_ID"] == CProSet::IB_CATALOG && 
			!in_array($_SERVER["SCRIPT_NAME"], $arSkipUrl)
		) {
			$arBacktrace = \Bitrix\Main\Diag\Helper::getBackTrace(20, true);
			file_put_contents("/home/bitrix/logs/OnAfterIBlockElementUpdate.txt", print_r(["date" => date("Y-m-d H:i:s"), $_SERVER["SCRIPT_NAME"], $arFields["ID"]], true) . "\r\n", FILE_APPEND);
		
			require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/RabbitMQConnector.php');
			require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');
			
			$syncHelper = new SyncHelper();
			$syncHelper->sendProduct([$arFields["ID"]]);
		}
		
		/*if($arFields["IBLOCK_ID"] == CProSet::IB_CATALOG && $arFields["RESULT"]){
			global $CACHE_MANAGER;
			$CACHE_MANAGER->ClearByTag("product_" . $arFields["ID"]);

			if(!empty($arFields["IBLOCK_SECTION"]) && count($arFields["IBLOCK_SECTION"] > 0)){
				foreach($arFields["IBLOCK_SECTION"] as $section_id){
					$CACHE_MANAGER->ClearByTag("iblock_section_id_" . $section_id);
				}
			}
			//добавляем с таблицу ci_sync. для обмена с PW
			
			$arSync = array(
				"XML_ID" => $arFields["XML_ID"],
				"TYPE" => "ELEMENT",
				"ACTION" => "UPDATE",
			);
			CSyncPW::addSync($arSync);
			
		}*/

	}

	public static function OnAfterIBlockElementAdd(&$arFields){
		if (!Loader::includeModule('iblock')) return;
		if ($GLOBALS['RABBITMQ_SKIP_ACTION'] !== true && $arFields["IBLOCK_ID"] == CProSet::IB_CATALOG) {
			$arBacktrace = \Bitrix\Main\Diag\Helper::getBackTrace(20, true);
			file_put_contents("/home/bitrix/logs/OnAfterIBlockElementAdd.txt", print_r(["date" => date("Y-m-d H:i:s"), $_SERVER["SCRIPT_NAME"], $arFields["ID"]], true) . "\r\n", FILE_APPEND);
		
		
			require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/RabbitMQConnector.php');
			require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');
			
			$syncHelper = new SyncHelper();
			$syncHelper->sendProduct([$arFields["ID"]]);
		}
		/*if($arFields["IBLOCK_ID"] == CProSet::IB_CATALOG){
			//добавляем с таблицу ci_sync. для обмена с PW
			
			$arSync = array(
				"XML_ID" => $arFields["XML_ID"],
				"TYPE" => "ELEMENT",
				"ACTION" => "ADD",
			);
			CSyncPW::addSync($arSync);
			
		}*/
	}

	public static function OnAfterIBlockPropertyAddHandler(&$arFields){
		//file_put_contents("/home/bitrix/logs/ttttest.txt", print_r(["add", $arFields], true), 8);
		if($arFields["IBLOCK_ID"] == 16 && CModule::IncludeModule('panel.manager')){
			$objContent = new CPanelContent;
			$objContent->syncProps();
		}
	}
	public static function OnAfterIBlockPropertyDeleteHandler($arProperty){
		//file_put_contents("/home/bitrix/logs/ttttest.txt", print_r(["Delete", $arProperty], true), 8);
		if($arProperty["IBLOCK_ID"] == 16 && CModule::IncludeModule('panel.manager')){
			$objContent = new CPanelContent;
			$objContent->syncProps();
		}
	}
	public static function OnAfterIBlockPropertyUpdateHandler(&$arFields){
		//file_put_contents("/home/bitrix/logs/ttttest.txt", print_r(["Update", $arFields], true), 8);
		if($arFields["IBLOCK_ID"] == 16 && CModule::IncludeModule('panel.manager')){
			$objContent = new CPanelContent;
			$objContent->syncProps();
		}
	}
	
}
?>