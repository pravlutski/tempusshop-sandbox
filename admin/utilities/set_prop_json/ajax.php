<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
// Отвечаем только на Ajax
//if ($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {return;}

if(!CModule::IncludeModule('panel.manager'))return;
global $USER;

global $DB;
$objContent = new CPanelContent;
$objUtils = new CPanelUtils;
$objProduct = new CPanelProduct;
$objBrand = new CPanelBrand;
//$arResult["PROPS"] = $objContent->getProps();

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups) && !in_array(6, $arGroups))
{
    $arResult["ERROR"][] = "<p style='color:red;'>Доступ запрещен</p>";
    return ;
}

$arBrand = $objBrand->getList();
$tmp = $objContent->getProps();
$tmp = sort_nested_arrays($tmp, $args = array('sort2' => 'asc', 'sort' => 'asc'));
foreach($tmp as $arItem){
	if(is_array($arItem["values"]))
		$arResult["PROPS"][$arItem["id"]] = $arItem;
}

function findGoodArticle($article, $arBrand){
	$objUtils = new CPanelUtils;
	$article = mb_strtoupper($article);

	foreach($arBrand as $brand){
		if(strripos($article, $brand["name"]) !== false){
			$arClearStr = array();

			if (str_starts_with($brand["name"], $article)) {
				$arClearStr[] = mb_strtoupper($brand["name"]);
			}

			//альтернативные бренды
			if(strlen($brand["alt_name"]) > 0){
				$tmp = explode("|", $brand["alt_name"]);
				foreach($tmp as $key => &$name){
					$name = trim($name);
					if(strlen($name) > 0){
						$arClearStr[] = mb_strtoupper($name, "UTF-8");
					}
				}
				unset($name);
			}
			$article = str_replace($arClearStr, '', $article);

			$article = trim($article);
			//prent($article);
			if(strlen($brand["regular"]) > 2){
				preg_match($brand["regular"], $article, $matches);
				$matches = array_diff($matches, array(''));
				$matches = array_unique($matches);
				if($matches && count($matches) == 1 && strlen($matches[0]) > 0)
					$article = $matches[0];
			}

			$article = str_replace(array("  "), array(" "), $article);
			$article = trim($article);

			//если пятый символ -, то менять на J. если девятого символа нет - добавлять Y
			//if($brand_name == "Q&Q"){
			if($brand["id"] == 16){
				if($article[4] == "-") $article[4] = "J";
				if(strlen($article) == 8) $article[8] = "Y";
			}

			//для романсана 22 удаляем пробелы
			if($brand["id"] == 22){
				$article = str_replace(" ", "", $article);
			}

			//если поставщик 3. Денис (supplier_id = 39) и бренд Восток (brand_id = 38)
			if($brand["id"] == 38){
				$tmp = trim(array_pop(explode(" ", $article)));
				//$tmp = intval($tmp);
				//if($tmp > 0){
				if(strlen($tmp) > 0){
					$article = $tmp;

				}
			}

			//RA-KV0006Y10B
			//if($brand_name == "Orient"){
			if($brand["id"] == 2){
				if($article[2] == "-"){
					$article = substr($article, 0, 10);
				}else{
					$article = substr($article, 0, 9);
				}
			}elseif($brand["id"] == 14)
				$article = $article;
			elseif(strpos($article, " "))
				$article = strstr($article, " ", true);

			if($brand["id"] == 26 && $article[2] == "/"){
				$article = substr($article, 3);
			}

			//если Tissot то удаляем точку после буквы T
			if($brand["id"] == 20 && $article[0] == "T" && $article[1] == "."){
				$article = substr_replace($article, '', 1, 1);
			}
			break;
		}
	}

	//$alt_art = $objUtils->getArtnumber($art);
	$alt_art = $objUtils->getArtnumber($article);

	if($alt_art) return $alt_art; else return $article;
}

// Можно передавать в скрипт разный action и в соответствии с ним выполнять разные действия.

$set_profile = trim($_REQUEST["set_profile"]);
$filename_parse = trim($_REQUEST["filename_parse"]);

if($filename_parse){
	$tmp = file_get_contents($filename_parse);
	$arItems = json_decode($tmp, true);
}

if($set_profile){
	$dir_profile = $_SERVER["DOCUMENT_ROOT"] . "/admin/utilities/set_prop_json/profiles/";
	$profile_name = trim($_REQUEST["set_profile"]);
	$profile_path = $dir_profile . "{$profile_name}.txt";
	$tmp = file_get_contents($profile_path);
	$arProfile = json_decode($tmp, true);
}

$action = $_REQUEST['action'];

if (empty($action)) {return;}

if(!$arItems || (is_array($arItems) && count($arItems) <= 0)){
	$arResult["ERROR"][] = "<p style='color:red;'>Список товаров не определен</p>";
}

if(!$arProfile || (is_array($arProfile) && count($arProfile) <= 0)){
	$arResult["ERROR"][] = "<p style='color:red;'>Профиль не определен</p>";
}

if($action == "stop"){
	$arResult["ERROR"][] = "<p style='color:red;'>Отмена</p>";
}

if($arResult["ERROR"]){
	$output = Array('offset' => 0, 'success' => 1, 'error' => $arResult["ERROR"], 'info' => '');

	echo json_encode($output, JSON_UNESCAPED_UNICODE);

	header('Content-Type: application/json;charset=UTF-8');
	die();
}

$count = count($arItems);
$step = 50;

// Получаем от клиента номер итерации
$offset = $_POST['offset'];

if($offset == 0){
	$_SESSION["STATUS_SET_ELEMENT_CNT"] = 0;
}

$arItemsSlice = array_slice($arItems, $offset, $step);

ob_start();

if(is_array($arItemsSlice) && count($arItemsSlice) > 0){
	$el = new CIBlockElement;
	$arResult["ITEMS"] = array();
	$arSkip = [
		"date", "desc", "images",
		"name", "name2", "model",
		"Compatible band size",
	];
	foreach($arItemsSlice as $key => $arItem){
		$article = findGoodArticle($arItem["model"], $arBrand);
		if($ELEMENT_ID = CPanelProduct::findArticle($article)){
			$prop = [];
			foreach($arItem as $code => $value){
				if(in_array($code, $arSkip)) continue;
				if($code == "Case size (L× W× H)" && $arProfile[$code]){
					$size = $value[0];
					if($size){
						$width = $height = $depth = false;
						if (preg_match('/(\d+\.?\d*)\s×\s(\d+\.?\d*)\s×\s(\d+\.?\d*)/', $size, $matches)) {
							$width = (float)$matches[1];
							$height = (float)$matches[2];
							$depth = (float)$matches[3];
						}
						if($arProfile[$code][0] && $width){
							$prop[$arProfile[$code][0]] = $width;
						}
						if($arProfile[$code][1] && $height){
							$prop[$arProfile[$code][1]] = $height;
						}
						if($arProfile[$code][2] && $depth){
							$prop[$arProfile[$code][2]] = $depth;
						}
						//prent([]);
					}
				}elseif($code == "Weight" && $arProfile[$code]){
					$val = $value[0];
					$weight = false;
					if (preg_match('/(\d+\.?\d*)\sg/', $val, $matches)) {
						$weight = (float)$matches[1];
					}
					if($arProfile[$code] && $weight){
						$prop[$arProfile[$code]] = $weight;
					}
				}elseif($arProfile[$code]){
					foreach($value as $f_value){
						if($arProfile[$code][$f_value]){
							foreach($arProfile[$code][$f_value] as $bxCode => $ar){
								if($ar["INSERT_TO"] == "Y"){
									$arFilterEl = Array("IBLOCK_ID" => CProSet::IB_CATALOG, "ID" => $ELEMENT_ID);
									$resEl = CIBlockElement::GetList(Array(), $arFilterEl, false, false, array("ID", "PROPERTY_{$bxCode}"));

									$oldList = array();
									while($obEl = $resEl->getNext()){
										$oldList = array_keys($obEl["~PROPERTY_" . mb_strtoupper($bxCode) . "_VALUE"]);
									}
									foreach($ar["VALUES"] as $k => $v)
										if(!in_array($v, $oldList))
											$oldList[] = $v;

									$arValues = $oldList;
								}else{
									$arValues = $ar["VALUES"];
								}
								$prop[$bxCode] = $arValues;
							}
						}
					}
				}
			}
			//prent($arItem);prent($prop);
			//die;
			if(count($prop) > 0){
				CIBlockElement::SetPropertyValuesEx($ELEMENT_ID, false, $prop);
				$arResult["INFO"][] = "<p style='color:green;'>{$article} - {$ELEMENT_ID} обновлен</p>";
				$_SESSION["STATUS_SET_ELEMENT_CNT"]++;
			}
		}else{
			$arResult["ERROR"][] = "<p style='color:red;'>{$article} - не найден ID товара</p>";
		}
	}
}

// Проверяем, все ли строки обработаны
$offset = $offset + $step;

if ($offset >= $count) {
	$success = 1;

	$arCount = array_count_values ($arItems);
	foreach($arCount as $val => $cnt){
		if($cnt > 1){
			$arResult["INFO"][] = "<p style='color:red;'>{$val} - количество повторений {$cnt}</p>";
		}
	}
	$arResult["INFO"][] = "<p>Всех элементов в списке - " . count($arItems) . ". Установлено для {$_SESSION["STATUS_SET_ELEMENT_CNT"]}</p>";
} else {
	$success = round($offset / $count, 2);
}

ob_end_clean();
// И возвращаем клиенту данные (номер итерации и сообщение об окончании работы скрипта)
$output = Array('offset' => $offset, 'success' => $success, 'error' => $arResult["ERROR"], 'info' => $arResult["INFO"]);

echo json_encode($output, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
