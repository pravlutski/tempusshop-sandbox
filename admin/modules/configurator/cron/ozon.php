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
//// Создаем массив для чистки элементов
//$arFilter = array_merge(array(
//	"IBLOCK_ID" => 16,
//	"INCLUDE_SUBSECTIONS" => "Y",
//), []);
//
//// Получаем общее количество элементов для чистки
//$res = CIBlockElement::GetList(array(), $arFilter);
//$totalElementsToClean = $res->SelectedRowsCount();
//$cleanedElements = 0;
//
//echo "Начинаем чистку элементов..." . PHP_EOL;
//
//// Чистим теги
//$res = CIBlockElement::GetList(array(), $arFilter);
//while ($ob = $res->GetNextElement()) {
//	$arFields = $ob->GetFields();
//	// Чистим теги
//	CIBlockElement::SetPropertyValuesEx($arFields["ID"], 16, array('MARKETPLACE_OZON_TAGS' => ''));
//	$cleanedElements++;
//	$cleanProgress = round(($cleanedElements / $totalElementsToClean) * 100, 2);
//	echo "Элемент " . $arFields["ID"] . " очищен. Очищено " . $cleanedElements . " из " . $totalElementsToClean . " элементов (" . $cleanProgress . "%)." . PHP_EOL;
//	flush();
//	ob_flush();
//}
//
//echo "Чистка элементов завершена." . PHP_EOL;
//flush();
//ob_flush();
//
///**
// * Заполнение тегов OZON
// */
//$strSqlOzon = "SELECT * FROM `ci_configurator_tags` WHERE active = 'Y' AND resource = 'OZON' AND properties_json IS NOT NULL AND properties_json <> '' ORDER BY `sort_order` DESC";
//$tagsOzon = $DB->Query($strSqlOzon, false);
//
//$totalElementsToTag = 0;
//$taggedElements = 0;
//
//echo "Начинаем заполнение тегов OZON..." . PHP_EOL;
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
//		// Получаем общее количество элементов для обработки
//		$res = CIBlockElement::GetList(array(), $arFilter);
//		$totalElementsToTag += $res->SelectedRowsCount();
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
//						echo "Элемент " . $arFieldsDuplicate['ID'] . " обработан." . PHP_EOL;
//
//						$db_props = CIBlockElement::GetProperty(16, $arFieldsDuplicate["ID"], array("sort" => "asc"), Array("CODE"=>"MARKETPLACE_OZON_TAGS"));
//						if($ar_props = $db_props->Fetch()) {
//							$currentTags = $ar_props["VALUE"];
//						}
//
//						$newTags = $row["tag_name"]; // новые теги
//
//						if (strlen($currentTags) > 200) {
//							$tagsList = (!empty($currentTags)) ? $currentTags . ';' . $newTags : $newTags; // добавляем к существующим тегам новые
//						}
//
//						// Убедимся, что строка тегов не превышает 255 символов
//						if (strlen($tagsList) > 255) {
//							$tagsList = substr($tagsList, 0, 255); // обрезаем строку до 255 символов
//						}
//
//						// Устанавливаем новые теги
//						CIBlockElement::SetPropertyValuesEx($arFieldsDuplicate["ID"], 16, array('MARKETPLACE_OZON_TAGS' => $tagsList));
//						$taggedElements++;
//						$tagProgress = round(($taggedElements / $totalElementsToTag) * 100, 2);
//						echo "Элемент " . $arFieldsDuplicate['ID'] . " обработан. Обработано " . $taggedElements . " из " . $totalElementsToTag . " элементов (" . $tagProgress . "%)." . PHP_EOL;
//						flush();
//						ob_flush();
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
//				echo "Элемент " . $arFields['ID'] . " обработан." . PHP_EOL;
//
//				$db_props = CIBlockElement::GetProperty(16, $arFields["ID"], array("sort" => "asc"), Array("CODE"=>"MARKETPLACE_OZON_TAGS"));
//				if($ar_props = $db_props->Fetch()) {
//					$currentTags = $ar_props["VALUE"];
//				}
//
//				$newTags = $row["tag_name"]; // новые теги
//
//				if (strlen($currentTags) > 200) {
//					$tagsList = (!empty($currentTags)) ? $currentTags . ';' . $newTags : $newTags; // добавляем к существующим тегам новые
//				}
//
//				// Убедимся, что строка тегов не превышает 255 символов
//				if (strlen($tagsList) > 255) {
//					$tagsList = substr($tagsList, 0, 255); // обрезаем строку до 255 символов
//				}
//
//				// Устанавливаем новые теги
//				CIBlockElement::SetPropertyValuesEx($arFields["ID"], 16, array('MARKETPLACE_OZON_TAGS' => $tagsList));
//				$taggedElements++;
//				$tagProgress = round(($taggedElements / $totalElementsToTag) * 100, 2);
//				echo "Элемент " . $arFields['ID'] . " обработан. Обработано " . $taggedElements . " из " . $totalElementsToTag . " элементов (" . $tagProgress . "%)." . PHP_EOL;
//				flush();
//				ob_flush();
//			}
//		}
//	}
//endwhile;
//
//echo "Заполнение тегов OZON завершено." . PHP_EOL;
//flush();
//ob_flush();
