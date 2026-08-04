<?php
//
//$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
//$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
//
//define("NO_KEEP_STATISTIC", true);
//define("NOT_CHECK_PERMISSIONS", true);
//
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
//set_time_limit(0);
//
//global $DB;
//
///**
// * Заполнение тегов OZON
// */
//$strSqlOzon = "SELECT * FROM `ci_configurator_tags` WHERE active = 'Y' AND resource = 'OZON' AND properties_json IS NOT NULL AND properties_json <> '' ORDER BY `sort_order` DESC";
//$tagsOzon = $DB->Query($strSqlOzon, false);
//
//while ($row = $tagsOzon->Fetch()):
//	// Раскодировываем JSON
//	$sections = json_decode($row['sections_json']);
//	$properties = json_decode($row['properties_json'], true);
//
//	// Создаем массив properties для фильтра
//	$propertiesArray = array();
//	$duplicateProperties = array();
//
//	foreach ($properties as $property) {
//		if (isset($propertiesArray["PROPERTY_" . $property[0]])) {
//			// Если свойство уже встречалось, добавляем его в массив дублирующихся свойств
//			$duplicateProperties["PROPERTY_" . $property[0]][] = $property[1];
//		} else {
//			$propertiesArray["PROPERTY_" . $property[0]] = $property[1];
//		}
//	}
//
//	foreach($sections as $sectionId) {
//		// Создаем массив для фильтра
//		$arFilter = array_merge(array(
//			"IBLOCK_ID" => 16,
//			"SECTION_ID" => $sectionId,
//			"INCLUDE_SUBSECTIONS" => "Y",
//		), $propertiesArray);
//
//		// Если есть дублирующиеся свойства, фильтруем элементы отдельно по каждому значению
//		if (!empty($duplicateProperties)) {
//			foreach ($duplicateProperties as $propertyCode => $propertyValues) {
//				foreach ($propertyValues as $propertyValue) {
//					$arFilterDuplicate = $arFilter;
//					$arFilterDuplicate[$propertyCode] = $propertyValue;
//
//					// Получаем элементы, удовлетворяющие условиям фильтра с дублирующимся свойством
//					$resDuplicate = CIBlockElement::GetList(array(), $arFilterDuplicate);
//					while ($obDuplicate = $resDuplicate->GetNextElement()) {
//						$arFieldsDuplicate = $obDuplicate->GetFields();
//						// Вывод информации о найденных элементах
//						echo $arFieldsDuplicate['ID'] . "<br>";
//					}
//				}
//			}
//		} else {
//			// Если дублирующихся свойств нет, фильтруем элементы по общему фильтру
//			// Получаем элементы, удовлетворяющие условиям фильтра
//			$res = CIBlockElement::GetList(array(), $arFilter);
//			while ($ob = $res->GetNextElement()) {
//				$arFields = $ob->GetFields();
//				// Вывод информации о найденных элементах
//				echo $arFields['ID'] . "<br>";
//			}
//		}
//	}
//endwhile;