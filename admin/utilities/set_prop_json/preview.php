<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
if(!CModule::IncludeModule('panel.manager')) return;

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups) && !in_array(6, $arGroups)) {
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return;
}

function removeSquareBracketsRecursive($array) {
    $result = [];
    
    foreach ($array as $key => $value) {
        $cleanKey = is_string($key) ? str_replace(['[', ']', "'", '"'], ['', '', '|rbracket1', '|rbracket2|'], $key) : $key;
        
        if (is_array($value)) {
            $result[$cleanKey] = removeSquareBracketsRecursive($value);
        } elseif (is_string($value)) {
            $result[$cleanKey] = str_replace(['[', ']', "'", '"'], ['', '', '|rbracket1|', '|rbracket2|'], $value);
        } else {
            $result[$cleanKey] = $value;
        }
    }
    
    return $result;
}

$filename_parse = $_REQUEST["filename_parse"] ?? $_SESSION["preview_filename"];
$profile_name = $_REQUEST["set_profile"] ?? $_SESSION["preview_profile"];
$action = $_REQUEST["action"] ?? "preview";

$_SESSION["preview_filename"] = $filename_parse;
$_SESSION["preview_profile"] = $profile_name;

$tmp = file_get_contents($filename_parse);
$arItems = json_decode($tmp, true);

$dir_profile = $_SERVER["DOCUMENT_ROOT"] . "/admin/utilities/set_prop_json/profiles/";
$profile_path = $dir_profile . "{$profile_name}.txt";
$tmp = file_get_contents($profile_path);
$arProfile = json_decode($tmp, true);
$arProfile = removeSquareBracketsRecursive($arProfile);

$objContent = new CPanelContent;
$objBrand = new CPanelBrand;
$objProduct = new CPanelProduct;
$objUtils = new CPanelUtils;

$arBrand = $objBrand->getList();
$tmp = $objContent->getProps();
$tmp = sort_nested_arrays($tmp, $args = array('sort2' => 'asc', 'sort' => 'asc'));
$bxProps = [];
foreach($tmp as $arItem){
	if ($arItem["id"]) {
		$bxProps[$arItem["id"]] = $arItem;
	}
}

function getArticlesBX()
{
	global $DB;
	$strSql = "
		SELECT 
			be.ID as PRODUCT_ID,
			bep.PROPERTY_123 as ARTICLE
		FROM b_iblock_element be
		INNER JOIN b_iblock_element_prop_s16 bep ON be.ID = bep.IBLOCK_ELEMENT_ID
		WHERE be.IBLOCK_ID = 16 
			AND be.IBLOCK_ID = 16
	";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	$arArticle = array();
	while ($row = $results->Fetch()){
		$arArticle[$row["ARTICLE"]] = $row["PRODUCT_ID"];
	}
	return $arArticle;
}
	
function findGoodArticle($article, $arBrand, $alternativeList){
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

	//$alt_art = $objUtils->getArtnumber($article);

	return $alternativeList[$article] ?? $article;
	//if($alt_art) return $alt_art; else return $article;
}


$data = [];
$arMatch = [];
$alternativeList = $objUtils->getArtnumberAll();
$articlesBX = getArticlesBX();


// Собираем все артикулы из файла
foreach($arItems as &$arItem) {
	$arItem = removeSquareBracketsRecursive($arItem);
    //$article = findGoodArticle($arItem["model"], $arBrand, $alternativeList);
    if ($articlesBX[$arItem["model"]]) {
		//prent($arItem["model"]);
		$article = $arItem["model"];
	} else {
		$article = findGoodArticle($arItem["model"], $arBrand, $alternativeList);
	}
	
	$data[$arItem["model"]] = [
        'article' => $article,
        'item_data' => $arItem
    ];
    $arMatch[$article] = $arItem["model"];
}
unset($arItem);

$articleList = array_keys($arMatch);
$res = CIBlockElement::GetList(
    array(), 
    array(
        'IBLOCK_ID' => CProSet::IB_CATALOG, 
        'PROPERTY_CML2_ARTICLE' => $articleList
    ), 
    false, 
    false, 
    array('ID', 'PROPERTY_CML2_ARTICLE')
);

while ($ob = $res->GetNext()) {
    $originalModel = $arMatch[$ob["PROPERTY_CML2_ARTICLE_VALUE"]] ?? '';
    if ($originalModel) {
        $data[$originalModel]['bx_id'] = $ob['ID'];
    }
}

$arAllProperties = [];
$arPropertyConfigs = [];



foreach($arItems as $arItem) {
    $item = $data[$arItem["model"]] ?? null;
    if (!$item || !isset($item['bx_id'])) continue;
    
    foreach($arItem as $code => $value) {
		$code = removeSquareBrackets($code);
        if(in_array($code, ['date', 'desc', 'images', 'name', 'name2', 'model', 'Compatible band size'])) continue;
        
        if($code == "Case size (L× W× H)" && $arProfile[$code]) {
            for($i = 0; $i < 3; $i++) {
                if($arProfile[$code][$i]) {
                    $propId = $arProfile[$code][$i];
                    $propInfo = $bxProps[$propId] ?? null;
                    if ($propInfo) {
                        $arAllProperties[$propId] = $propInfo['name'] ?? "Свойство $propId";
                        $arPropertyConfigs[$propId] = [
                            'type' => 'case_size',
                            'index' => $i,
                            'prop_info' => $propInfo
                        ];
                    }
                }
            }
        }
        elseif($code == "Weight" && $arProfile[$code]) {
            $propId = $arProfile[$code];
            $propInfo = $bxProps[$propId] ?? null;
            if ($propInfo) {
                $arAllProperties[$propId] = $propInfo['name'] ?? "Свойство $propId";
                $arPropertyConfigs[$propId] = [
                    'type' => 'weight',
                    'prop_info' => $propInfo
                ];
            }
        }
		elseif($arProfile[$code]) {
			foreach($arProfile[$code] as $profileKey => $profileMapping) {
				foreach($profileMapping as $bxCode => $ar) {
					$propInfo = $bxProps[$bxCode] ?? null;
					if ($propInfo) {
						$arAllProperties[$bxCode] = $propInfo['name'] ?? "Свойство $bxCode";
						if (!$arPropertyConfigs[$bxCode]) {
							$arPropertyConfigs[$bxCode] = [
								'type' => 'property',
								'source_code' => $code,
								'possible_keys' => array_keys($arProfile[$code]), // Все возможные ключи
								'prop_info' => $propInfo,
								'profile_mappings' => [], // Все маппинги для этого источника
								'insert_to' => $ar['INSERT_TO'] == 'Y' ? true : false,
							];
						}
						foreach ($arProfile[$code] as $k => $v) {
							$arPropertyConfigs[$bxCode]['profile_mappings'][$k] = $v;
						}
					}
				}
			}
		}
    }
}

$arCurrentProps = [];
$elementIds = [];

foreach($data as $itemData) {
    if (isset($itemData['bx_id'])) {
        $elementIds[] = $itemData['bx_id'];
    }
}






function removeSquareBrackets($key) {
    return str_replace(['[', ']'], '', $key);
}


$allProperties = [];
$elementIds = [];

$arNewData = [];
$notFound = [];

foreach ($data as $article => $item) {
	$productId = $item['bx_id'];
	if (!$productId) {
		$notFound[] = $article;
		continue;
	}
	$article = $item['article'];
	$item_data = $item['item_data'];
	
	$props = [];
	foreach($arProfile as $profilePropCode => $values) {
		if (!$values) continue;
		if (!$item_data[$profilePropCode]) continue;
		
		if (is_array($item_data[$profilePropCode]))
			$item_values = [implode('. ', $item_data[$profilePropCode])];
		else
			$item_values = [$item_data[$profilePropCode]];
		
		if ($profilePropCode == 'Case size (L× W× H)' && is_array($values) && count($values) == 3) {
			
			$size = false;
			if (is_array($item_data["Case size (L× W× H)"])) {
				$size = $item_data["Case size (L× W× H)"][0] ?? false;
			} else {
				$size = $item_data["Case size (L× W× H)"];
			}
			if ($size && preg_match('/(\d+\.?\d*)\s×\s(\d+\.?\d*)\s×\s(\d+\.?\d*)/', $size, $matches)) {
				$newValue = (float)($matches[$index + 1] ?? 0);
				//prent($matches);
				$props[$values[0]]['VALUE'] = (float)$matches[1];
				$props[$values[1]]['VALUE'] = (float)$matches[2];
				$props[$values[2]]['VALUE'] = (float)$matches[3];
			}
			
			$allProperties[$values[0]] = $bxProps[$values[0]]['name'] ?? "Свойство $bxCode";
			$allProperties[$values[1]] = $bxProps[$values[1]]['name'] ?? "Свойство $bxCode";
			$allProperties[$values[2]] = $bxProps[$values[2]]['name'] ?? "Свойство $bxCode";
			//prent($profilePropCode);prent($values);
		} elseif ($profilePropCode == 'Weight') {
			//$props[$values] = $item_data['Weight'];
			//prent($profilePropCode);prent($values);
            //        [Case size (L× W× H)] => 52.5 × 46.4 × 13.8 mm
             //       [Weight] => 73 g
			
			$val = false;
			if (is_array($item_data['Weight'])) {
				$val = $item_data['Weight'][0] ?? false;
			} else {
				$val = $item_data['Weight'];
			}
			if ($val && preg_match('/(\d+\.?\d*)\sg/', $val, $matches)) {
				$props[$values]['VALUE'] = (float)$matches[1];
			}
			$bxProp = $values;
			$allProperties[$bxProp] = $bxProps[$bxProp]['name'] ?? "Свойство $bxCode";
		} else {
			foreach($values as $profilePropValue => $matchProp) {
				if (!in_array($profilePropValue, $item_values)) continue;
				
				foreach($matchProp as $bxProp => $v) {
					if (!$v['VALUES']) continue;
					
					//prent($v);
					if (is_array($v['VALUES'])) {
						if (!is_array($props[$bxProp])) $props[$bxProp] = ['VALUE' => []];
						foreach ($v['VALUES'] as $v2) {
							if (!in_array($v2, $props[$bxProp]['VALUE'])) $props[$bxProp]['VALUE'][] = $v2;
						}
						$allProperties[$bxProp] = $bxProps[$bxProp]['name'] ?? "Свойство $bxCode";
					} else {
						if (!is_array($props[$bxProp])) $props[$bxProp] = ['VALUE' => ''];
						if ($bxProp == 'ENG_DESCRIPTION') {
							if ($props[$bxProp]['VALUE']) $props[$bxProp]['VALUE'] .= '|';
							$props[$bxProp]['VALUE'] .= $v['VALUES'];
						} else {
							$props[$bxProp]['VALUE'] = $v['VALUES'];
						}
						$allProperties[$bxProp] = $bxProps[$bxProp]['name'] ?? "Свойство $bxCode";
					}
					
					if ($v['INSERT_TO'] && $v['INSERT_TO'] == 'Y') $props[$bxProp]['INSERT_TO'] = 'Y';
				}
			}
		}

	}
	
	if ($props) {
		/*foreach ($props as $bxProp => &$v) {
			if ($bxProp == 'ENG_DESCRIPTION' && $v) {
				$tmp = explode('|', $v['VALUE']);
				$tmp = array_unique($tmp);
				$v['VALUE'] = implode('|', $tmp);
			}
		}
		unset($v);*/
		$arNewData[$productId] = [
			'ID' => $productId,
			'ARTICLE' => $article,
			'PROPS' => $props,
		];
		
		$elementIds[] = $productId;
	} else {
		
	}
	
}

if (!empty($elementIds)) {
    $arFilter = ["IBLOCK_ID" => CProSet::IB_CATALOG, "ID" => $elementIds];
    $res = CIBlockElement::GetList([], $arFilter, false, false, array("ID", "IBLOCK_ID"));
    
    while ($ob = $res->GetNextElement()){
        $arFields = $ob->GetFields();
        $id = $arFields['ID'];
        $properties = $ob->GetProperties();

        foreach ($properties as $propCode => $propValue) {
            if (isset($arAllProperties[$propCode])) {
                if ($propValue['PROPERTY_TYPE'] == 'L' && $propValue['MULTIPLE'] == 'Y') {
					//if (is_array($propValue['VALUE'])) {
						$arCurrentProps[$id][$propCode] = [
							'VALUE' => $propValue['VALUE'],
							'VALUE_ENUM_ID' => $propValue['VALUE_ENUM_ID'],
							'PROPERTY_TYPE' => $propValue['PROPERTY_TYPE'],
							'MULTIPLE' => $propValue['MULTIPLE'],
						];
					//}
                } elseif($propValue['PROPERTY_TYPE'] == 'L') {
					$arCurrentProps[$id][$propCode] = [
						'VALUE' => $propValue['VALUE'],
						'VALUE_ENUM_ID' => $propValue['VALUE_ENUM_ID'],
						'PROPERTY_TYPE' => $propValue['PROPERTY_TYPE'],
						'MULTIPLE' => $propValue['MULTIPLE'],
					];
				} else {
                    $arCurrentProps[$id][$propCode] = [
						'VALUE' => $propValue['VALUE'],
						'PROPERTY_TYPE' => $propValue['PROPERTY_TYPE'],
						'MULTIPLE' => $propValue['MULTIPLE'],
					];
                }

            }
        }
    }
}

$arPreview = [];
$totalInFile = count($data);
$foundOnSite = count($arNewData);
$willBeUpdated = 0;
//prent($arCurrentProps);die;
foreach($arNewData as $elementId => $itemData) {
    $arItemPreview = [
        'element_id' => $elementId,
        'article' => $itemData['ARTICLE'],
        'properties' => []
    ];
    
    $hasChanges = false;
    
    foreach($allProperties as $propCode => $propName) {
        // Получаем текущее значение
        $oldValue = null;
        $oldValueEnumId = null;
        $propType = 'S';
        $isMultiple = 'N';
        
        if (isset($arCurrentProps[$elementId][$propCode])) {
            $propData = $arCurrentProps[$elementId][$propCode];
            $propType = $propData['PROPERTY_TYPE'] ?? 'S';
            $isMultiple = $propData['MULTIPLE'] ?? 'N';
            
            if ($propType == 'L' && $isMultiple == 'Y') {
                //$oldValue = $propData['VALUE'] ?? [];
				$oldValue = $propData['VALUE_ENUM_ID'] ?? [];
                $oldValueEnumId = $propData['VALUE_ENUM_ID'] ?? [];
            } elseif ($propType == 'L') {
                //$oldValue = $propData['VALUE'] ?? '';
                $oldValue = $propData['VALUE_ENUM_ID'] ?? '';
                $oldValueEnumId = $propData['VALUE_ENUM_ID'] ?? '';
            } else {
				if (is_array($propData['VALUE']) && $propData['VALUE']['TEXT']) { 
					$oldValue = $propData['VALUE']['TEXT'];
				} else {
					$oldValue = $propData['VALUE'] ?? '';
				}
            }
        }
        
        // Получаем новое значение
        $newValue = null;
        $insertTo = false;
        
        if (isset($itemData['PROPS'][$propCode])) {
            $propNewData = $itemData['PROPS'][$propCode];
            
            if ($propType == 'L' && $isMultiple == 'Y') {
                // Для множественных свойств
                $newValue = $propNewData['VALUE'] ?? [];
                $insertTo = ($propNewData['INSERT_TO'] ?? '') == 'Y' ? true : false;
                
                // Сортируем для сравнения
                $oldSorted = is_array($oldValue) ? $oldValue : [];
                sort($oldSorted);
                $newSorted = is_array($newValue) ? $newValue : [];
                sort($newSorted);
                
                // Если INSERT_TO = Y, то добавляем к существующим
                if ($insertTo) {
                    // Объединяем старые и новые значения
                    $merged = array_merge($oldSorted, $newSorted);
                    $merged = array_unique($merged);
                    sort($merged);
                    $newValue = $merged;
                }
                
                // Проверяем, есть ли изменения
                if ($oldSorted != $newSorted) {
                    $hasChanges = true;
                }
                
            } elseif ($propType == 'L') {
                // Для одиночных свойств списка
                $newValue = $propNewData['VALUE'] ?? '';
                
                if ($oldValue != $newValue) {
                    $hasChanges = true;
                }
                
            } else {
                // Для текстовых/числовых свойств
                $newValue = $propNewData['VALUE'] ?? '';
                
                if ($oldValue != $newValue) {
                    $hasChanges = true;
                }
            }
        }
        
        // Добавляем свойство в preview (даже если нет изменений)
        $arItemPreview['properties'][$propCode] = [
            'old' => $oldValue,
            'old_enum_id' => $oldValueEnumId,
            'new' => $newValue,
            'prop_info' => [
                'property_type' => $propType,
                'is_multiple' => $isMultiple,
                'values' => $bxProps[$propCode]['values'] ?? []
            ],
            'insert_to' => $insertTo,
            'has_changes' => (isset($oldValue) || isset($newValue)) // Показываем всегда, если есть хоть какие-то данные
        ];
    }
    
    if ($hasChanges) {
        $willBeUpdated++;
    }
    
    $arPreview[] = $arItemPreview;
}
?>

<h1 class="page-header">Превью изменений свойств товаров</h1>

<div class="summary-box" style="background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
    <h3>Сводка:</h3>
    <div class="row">
        <div class="col-md-3">
            <p>В файле: <br><strong style="font-size: 24px;"><?= $totalInFile ?></strong> товаров</p>
        </div>
        <div class="col-md-3">
            <p>Найдено на сайте: <br><strong style="font-size: 24px;"><?= $foundOnSite ?></strong> товаров</p>
        </div>
        <div class="col-md-3">
            <p>Будет изменено: <br><strong style="font-size: 24px; color: #e67e22;"><?= $willBeUpdated ?></strong> товаров</p>
        </div>
        <div class="col-md-3">
            <p>Свойств: <br><strong style="font-size: 24px;"><?= count($allProperties) ?></strong></p>
        </div>
    </div>
</div>
<?php if(!empty($notFound)): ?>
	<?
	//$notFound = sort_nested_arrays($notFound, ['model' => 'asc']);
	asort($notFound);
	?>
	<div style="max-height: 200px;overflow: scroll;border: 1px solid black;padding: 20px;">
	
	<?foreach($notFound as $article):?>
		<p style="margin: 0 0 0 0;font-size: 13px;"><strong style=""><?= $article ?></strong></p>
	<?endforeach?>
	</div>
<?php endif; ?>

<?php if(!empty($arPreview)): ?>
<form id="previewForm" method="post" action="/admin/utilities/set_prop_json/apply_changes.php">
    <input type="hidden" name="filename_parse" value="<?= htmlspecialchars($filename_parse ?? '') ?>">
    <input type="hidden" name="profile_name" value="<?= htmlspecialchars($profile_name ?? '') ?>">
    
    <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
        <table class="table table-bordered table-striped" style="font-size: 12px;">
            <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                <tr>
                    <th class="fixed-column-1" style="min-width: 100px;">Артикул</th>
                    <th class="fixed-column-1" style="min-width: 50px;">ID товара</th>
                    <?php foreach($allProperties as $propCode => $propName): 
                        $propInfo = $bxProps[$propCode] ?? [];
                        $isMultiple = ($propInfo['is_multiple'] ?? 'N') == 'Y';
                    ?>
                        <th colspan="2" style="text-align: center; background: #e8f4fd; <?= $isMultiple ? 'color: #dc3545;' : '' ?>">
                            <?= htmlspecialchars($propName) ?>
                            <?php if($isMultiple): ?>
                                <br><small style="color: #dc3545;">(Множественное)</small>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th style="position: sticky; left: 0; background: white; z-index: 11;"></th>
                    <th style="position: sticky; left: 0; background: white; z-index: 11;"></th>
                    <?php foreach($allProperties as $propCode => $propName): ?>
                        <th style="background: #fff3cd; min-width: 150px;">Старое</th>
                        <th style="background: #d4edda; min-width: 150px;">Новое</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($arPreview as $item): ?>
                    <tr>
						<td class="fixed-column-1"><?= $item['article'] ?></td>
                        <td class="fixed-column-1"><?= $item['element_id'] ?></td>
                        <?php foreach($allProperties as $propCode => $propName): 
                            $propData = $item['properties'][$propCode] ?? null;
                            $propInfo = $propData['prop_info'] ?? [];
                            $isMultiple = ($propInfo['is_multiple'] ?? 'N') == 'Y';
                            $propType = $propInfo['property_type'] ?? 'S';
                            $propValues = $propInfo['values'] ?? [];
                            if ($item['article'] == 'LQ-139L-2B') {
								//prent($propData);//die'
							}
                            // Форматируем старое значение
                            $oldValue = '';
                            if ($propData && isset($propData['old'])) {
                                $old = $propData['old'];
                                
                                if ($isMultiple && is_array($old)) {
                                    $oldNames = [];
                                    foreach ($old as $itemId) {
                                        $oldNames[] = $propValues[$itemId] ?? $itemId;
                                    }
                                    $oldValue = implode('<br>', $oldNames);
                                } elseif ($propType == 'L' && !$isMultiple) {
                                    $oldValue = $propValues[$old] ?? $old;
                                } else {
									/*if (is_array($old) && $old['TEXT']) { 
										$oldValue = $old['TEXT'];
									} else {
										
									}*/
                                    $oldValue = $old;
                                }
                            }
                            
                            // Форматируем новое значение
                            $newValue = '';
                            $hasChanges = false;
                            if ($propData && isset($propData['new'])) {
                                $new = $propData['new'];
                                $insertTo = $propData['insert_to'] ?? false;
                                
                                if ($isMultiple && is_array($new)) {
                                    $newNames = [];
                                    foreach ($new as $itemId) {
                                        $newNames[] = $propValues[$itemId] ?? $itemId;
                                    }
                                    $newValue = implode('<br>', $newNames);
                                } elseif ($propType == 'L' && !$isMultiple) {
                                    $newValue = $propValues[$new] ?? $new;
                                } else {
                                    $newValue = $new;
                                }
                                
                                // Проверяем, есть ли изменения
                                if ($propData['old'] != $propData['new']) {
                                    $hasChanges = true;
                                }
                            }
                        ?>
                            <td style="background: #fff3cd; vertical-align: middle;">
                                <?php if($hasChanges): ?>
                                    <span class="badge badge-warning">ИЗМЕНИТСЯ</span><br>
                                <?php endif; ?>
								<?
								//prent($oldValue); 
								?>
                                <?/*<div><?= $oldValue ?: '<span style="color: #999; font-style: italic;">(пусто)</span>' ?></div>*/?>
								<?if ($propType == 'S'):?>
								<textarea class="form-control form-control-sm" style="max-width: 150px;max-height: 150px;" disabled><?= $oldValue?></textarea>
								<?else:?>
								<?= $oldValue ?: '<span style="color: #999; font-style: italic;">(пусто)</span>' ?>
								<?endif?>
							</td>
							<td style="background: #d4edda; vertical-align: middle;">
								<?php if($propData && $propData['new'] !== null): ?>
									<?php if($hasChanges): ?>
										<?php if($insertTo): ?>
											<small class="text-info" style="display: block; font-size: 9px;">
												<i class="fa fa-info-circle"></i> Добавится к существующим
											</small>
										<?php endif; ?>
										
										<?php if($isMultiple && $propType == 'L'): ?>
											<!-- Множественное свойство - чекбоксы -->
											<div style="max-height: 150px; overflow-y: auto; padding: 5px; border: 1px solid #ccc; border-radius: 4px; background: white;">
												<?php 
												$selectedValues = [];
												$oldValues = [];
												
												// Получаем новые значения
												if (isset($propData['new']) && is_array($propData['new'])) {
													$selectedValues = $propData['new'];
												} elseif (isset($propData['new']) && !empty($propData['new'])) {
													$selectedValues = [$propData['new']];
												}
												//prent($selectedValues); 
												// Если INSERT_TO = Y, добавляем старые значения в выбранные
												/*if ($insertTo && isset($propData['old']) && is_array($propData['old'])) {
													$oldValues = $propData['old'];
													//prent($oldValues); 
													foreach ($oldValues as $oldId) {
														if (!in_array($oldId, $selectedValues)) {
															//$selectedValues[] = $oldId;
														}
													}
												}*/
												
												// Сортируем для удобства
												sort($selectedValues);
												?>
												
												<?php foreach($propValues as $id => $name): ?>
													<?php 
													$isChecked = in_array($id, $selectedValues);
													$isOld = ($insertTo && !empty($oldValues) && in_array($id, $oldValues));
													$isNew = ($isChecked && !$isOld);
													?>
													<div class="form-check" style="<?= $isOld ? 'background: #e8f4fd; border-left: 3px solid #0066cc;' : ($isNew ? 'background: #d4edda; border-left: 3px solid #28a745;' : '') ?>">
														<input type="checkbox" 
															   class="form-check-input" 
															   name="changes[<?= $item['element_id'] ?>][<?= $propCode ?>][VALUE][]" 
															   value="<?= $id ?>"
															   <?= $isChecked ? 'checked' : '' ?>>
														<label class="form-check-label" style="font-size: 11px;">
															<?= htmlspecialchars($name) ?>
															<?php if($isOld): ?>
																<small style="color: #0066cc; font-weight: bold;">(существующее)</small>
															<?php elseif($isNew): ?>
																<small style="color: #28a745; font-weight: bold;">(новое)</small>
															<?php endif; ?>
														</label>
													</div>
												<?php endforeach; ?>
											</div>
											<?php if($insertTo): ?>
												<input type="hidden" name="changes[<?= $item['element_id'] ?>][<?= $propCode ?>][INSERT_TO]" value="Y">
												<small class="text-info" style="display: block; margin-top: 3px; font-size: 9px;">
													<i class="fa fa-info-circle"></i> Режим добавления: новые значения добавятся к существующим
												</small>
											<?php endif; ?>
											
										<?php elseif(!$isMultiple && $propType == 'L'): ?>
											<!-- Одиночное свойство список - выпадающий список -->
											<select name="changes[<?= $item['element_id'] ?>][<?= $propCode ?>][VALUE]" 
													class="form-control form-control-sm" style="min-width: 150px;">
												<option value="">-- Не выбрано --</option>
												<?php 
												$currentValue = $propData['new'] ?? '';
												?>
												<?php foreach($propValues as $id => $name): ?>
													<option value="<?= $id ?>" 
															<?= ($id == $currentValue) ? 'selected' : '' ?>>
														<?= htmlspecialchars($name) ?>
													</option>
												<?php endforeach; ?>
											</select>
											
										<?php elseif($propType == 'N'): ?>
											<!-- Числовое свойство -->
											<input type="number" step="0.01" 
												   name="changes[<?= $item['element_id'] ?>][<?= $propCode ?>][VALUE]" 
												   value="<?= htmlspecialchars($propData['new'] ?? '') ?>"
												   class="form-control form-control-sm" style="min-width: 150px;">
												   
										<?php elseif($propType == 'F'): ?>
											<!-- Файловое свойство -->
											<input type="text" 
												   name="changes[<?= $item['element_id'] ?>][<?= $propCode ?>][VALUE]" 
												   value="<?= htmlspecialchars($propData['new'] ?? '') ?>"
												   placeholder="ID файла"
												   class="form-control form-control-sm" style="min-width: 150px;">
												   
										<?php else: ?>
											<!-- Текстовое свойство -->
											<?if($propCode == 'ENG_DESCRIPTION'):?>
											<textarea name="changes[<?= $item['element_id'] ?>][<?= $propCode ?>][VALUE]" 
												   class="form-control form-control-sm" style="min-width: 150px;min-height: 150px;"><?= (!is_array($propData['new']) ? htmlspecialchars($propData['new'])  : '') ?></textarea>
											<?else:?>
											<input type="text" 
												   name="changes[<?= $item['element_id'] ?>][<?= $propCode ?>][VALUE]" 
												   value="<?= (!is_array($propData['new']) ? htmlspecialchars($propData['new'])  : '') ?>"
												   class="form-control form-control-sm" style="min-width: 150px;">
											<?endif?>
										<?php endif; ?>
										
									<?php else: ?>
										<span style="color: #999;">(без изменений)</span>
										<?/*<!-- Все равно отправляем текущее значение -->
										<input type="hidden" name="changes[<?= $item['element_id'] ?>][<?= $propCode ?>][VALUE]" 
											   value="<?= htmlspecialchars(is_array($propData['new']) ? implode('|', $propData['new']) : $propData['new']) ?>">*/?>
									<?php endif; ?>
								<?php else: ?>
									<span style="color: #999; font-style: italic;">(нет данных)</span>
								<?php endif; ?>
							</td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div style="position: sticky; bottom: 0; background: white; padding: 15px 0; border-top: 2px solid #ddd;">
        <div class="row">
            <div class="col-md-12 text-right">
                <button type="submit" name="apply_changes" class="btn btn-success btn-lg">
                    <i class="fa fa-check"></i> Применить изменения (<?= $willBeUpdated ?> товаров)
                </button>
                <a href="/admin/utilities/set_prop_json/" class="btn btn-default btn-lg">
                    <i class="fa fa-arrow-left"></i> Назад
                </a>
            </div>
        </div>
    </div>
</form>

<script>
$(document).ready(function() {
    $('tbody tr').hover(
        function() {
            $(this).find('td').css('background-color', '#f0f8ff');
        },
        function() {
            $(this).find('td').each(function() {
                var colIndex = $(this).index();
                if(colIndex % 2 == 1) {
                    $(this).css('background-color', '#fff3cd');
                } else if(colIndex % 2 == 0 && colIndex > 0) {
                    $(this).css('background-color', '#d4edda');
                }
            });
        }
    );
});

$(document).ready(function() {
    function updateFixedColumns() {
        $('.fixed-column-1').css('left', '0px');
    }
    
    updateFixedColumns();
    
    $('.table-responsive').scroll(function() {
        updateFixedColumns();
    });
    
    $(window).resize(function() {
        updateFixedColumns();
    });
});
</script>

<style>
.table th, .table td {
    border: 3px solid black !important;
    white-space: nowrap;
    vertical-align: middle !important;
}
.table th[colspan="2"] {
    text-align: center;
    font-weight: bold;
}
.table-responsive {
    border: 1px solid #dee2e6;
}
.badge {
    font-size: 10px;
    padding: 2px 5px;
}
.form-check {
    margin-bottom: 2px;
    min-height: 18px;
    padding: 2px 5px;
    border-radius: 3px;
    transition: background 0.2s;
}
.form-check:hover {
    background: #f8f9fa !important;
}
.form-check-input {
    margin-top: 0;
    width: 14px;
    height: 14px;
    margin-right: 5px;
    cursor: pointer;
}
.form-check-label {
    font-size: 11px;
    margin-left: 5px;
    cursor: pointer;
}
.form-check small {
    font-size: 9px;
}
.form-check {
    margin-bottom: 2px;
    min-height: 18px;
    padding: 2px 5px;
}
.form-check-input {
    margin-top: 0;
    width: 14px;
    height: 14px;
    margin-right: 5px;
}
.form-check-label {
    font-size: 11px;
    margin-left: 5px;
    cursor: pointer;
}
.form-control-sm {
    height: 28px;
    padding: 2px 5px;
    font-size: 12px;
    min-width: 150px;
}
select.form-control-sm {
    height: 28px;
    padding: 2px 5px;
}
.fixed-column-1 {
    position: sticky;
    left: 0;
    background: white;
    z-index: 10;
}
.table th.fixed-column-1 {
    z-index: 11;
}
</style>

<?php else: ?>
<div class="alert alert-info">
    <h4>Нет данных для отображения</h4>
    <p>Нет товаров</p>
    <a href="/admin/utilities/set_prop_json/" class="btn btn-primary">
        <i class="fa fa-arrow-left"></i> Вернуться назад
    </a>
</div>
<?php endif; ?>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");