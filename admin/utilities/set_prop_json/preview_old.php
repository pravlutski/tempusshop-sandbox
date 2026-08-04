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

$objContent = new CPanelContent;
$objBrand = new CPanelBrand;
$objProduct = new CPanelProduct;
$objUtils = new CPanelUtils;

$arBrand = $objBrand->getList();
$tmp = $objContent->getProps();
$tmp = sort_nested_arrays($tmp, $args = array('sort2' => 'asc', 'sort' => 'asc'));
$arResultProps = [];
foreach($tmp as $arItem){
    $arResultProps[$arItem["id"]] = $arItem;
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

	//$alt_art = $objUtils->getArtnumber($article);

	return $alternativeList[$article] ?? $article;
	//if($alt_art) return $alt_art; else return $article;
}


$data = [];
$arMatch = [];
$alternativeList = $objUtils->getArtnumberAll();
$articlesBX = getArticlesBX();

// Собираем все артикулы из файла
foreach($arItems as $arItem) {
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
prent($arProfile);prent($data);

$arNewData = [];
foreach ($data as $article => $item) {
	$productId = $item['bx_id'];
	$item_data = $item['item_data'];
	
	$props = [];
	foreach($arProfile as $profilePropCode => $values) {
		if (!$values) continue;
		
		foreach($values as $profilePropValue => $matchProp) {
			foreach($matchProp as $bxProp => $v) {
				if (!$v['VALUES']) continue;
				
				if (is_array($v['VALUES'])) {
					if (!is_array($props[$bxProp])) $props[$bxProp] = [];
					foreach ($v['VALUES'] as $v2) {
						if (!in_array($v2, $props[$bxProp])) $props[$bxProp][] = $v2;
					}
				} else {
					if ($bxProp == 'ENG_DESCRIPTION') {
						if ($props[$bxProp]) $props[$bxProp] .= '|';
						$props[$bxProp] .= $v['VALUES'];
					} else {
						$props[$bxProp] = $v['VALUES'];
					}
				}
				
				//
			}
		}
	}
	
	if ($props) {
		$arNewData[] = [
			'ID' => $productId,
			'PROPS' => $props,
		];
	}
	
}prent($arNewData);
die;

foreach($arItems as $arItem) {
    $item = $data[$arItem["model"]] ?? null;
    if (!$item || !isset($item['bx_id'])) continue;
    
    foreach($arItem as $code => $value) {
        if(in_array($code, ['date', 'desc', 'images', 'name', 'name2', 'model', 'Compatible band size'])) continue;
        
        if($code == "Case size (L× W× H)" && $arProfile[$code]) {
            for($i = 0; $i < 3; $i++) {
                if($arProfile[$code][$i]) {
                    $propId = $arProfile[$code][$i];
                    $propInfo = $arResultProps[$propId] ?? null;
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
            $propInfo = $arResultProps[$propId] ?? null;
            if ($propInfo) {
                $arAllProperties[$propId] = $propInfo['name'] ?? "Свойство $propId";
                $arPropertyConfigs[$propId] = [
                    'type' => 'weight',
                    'prop_info' => $propInfo
                ];
            }
        }
		elseif($arProfile[$code]) {
			// Для каждого ключа в профиле для этого кода
			foreach($arProfile[$code] as $profileKey => $profileMapping) {
				// Для каждого свойства в этом маппинге
				foreach($profileMapping as $bxCode => $ar) {
					$propInfo = $arResultProps[$bxCode] ?? null;
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
						//prent($arProfile[$code]);
					}
				}
			}
		}
    }
}
//prent($arPropertyConfigs);die;
$arCurrentProps = [];
$elementIds = [];

foreach($data as $itemData) {
    if (isset($itemData['bx_id'])) {
        $elementIds[] = $itemData['bx_id'];
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
					if (is_array($propValue['VALUE'])) {
						$arCurrentProps[$id][$propCode] = [
							'VALUE' => $propValue['VALUE'],
							'VALUE_ENUM_ID' => $propValue['VALUE_ENUM_ID'],
						];
					}
                } elseif($propValue['PROPERTY_TYPE'] == 'L') {
					$arCurrentProps[$id][$propCode] = [
						'VALUE' => $propValue['VALUE'],
						'VALUE_ENUM_ID' => $propValue['VALUE_ENUM_ID'],
					];
				} else {
                    $arCurrentProps[$id][$propCode] = $propValue['VALUE'] ?? '';
                }
            }
        }
    }
}

// Функция для поиска совпадений с игнорированием регистра и пробелов
function findMatchingKey($value, $possibleKeys) {
    // Нормализуем искомое значение
    $normalizedValue = mb_strtolower(trim($value));
    $normalizedValue = preg_replace('/\s+/', ' ', $normalizedValue);
    $normalizedValue = preg_replace('/[^a-z0-9\s]/', '', $normalizedValue); // Убираем спецсимволы для более точного сравнения
    
    foreach ($possibleKeys as $key) {
        $normalizedKey = mb_strtolower(trim($key));
        $normalizedKey = preg_replace('/\s+/', ' ', $normalizedKey);
        $normalizedKey = preg_replace('/[^a-z0-9\s]/', '', $normalizedKey);
        
        // Проверяем точное совпадение после нормализации
        if ($normalizedValue === $normalizedKey) {
            return $key;
        }
        
        // Проверяем, содержит ли ключ значение или наоборот (только если длина больше 5 символов)
        $minLength = min(strlen($normalizedValue), strlen($normalizedKey));
        if ($minLength > 5) {
            if (strpos($normalizedKey, $normalizedValue) !== false || 
                strpos($normalizedValue, $normalizedKey) !== false) {
                // Проверяем, что совпадение составляет больше 70% строки
                $longer = strlen($normalizedValue) > strlen($normalizedKey) ? $normalizedValue : $normalizedKey;
                $shorter = strlen($normalizedValue) > strlen($normalizedKey) ? $normalizedKey : $normalizedValue;
                if (strpos($longer, $shorter) !== false) {
                    $matchPercent = strlen($shorter) / strlen($longer);
                    if ($matchPercent > 0.7) { // Совпадение больше 70%
                        return $key;
                    }
                }
            }
        }
    }
    return null;
}

$arPreview = [];
$totalInFile = count($arItems);
$foundOnSite = 0;
$willBeUpdated = 0;
prent($arPropertyConfigs);
$notFound = [];
foreach($arItems as $arItem) {
    $itemData = $data[$arItem["model"]] ?? null;
    if (!$itemData || !isset($itemData['bx_id'])) {
		$notFound[] = $arItem;
		continue;
	}

    $article = $itemData['article'];
    $ELEMENT_ID = $itemData['bx_id'];
    $foundOnSite++;
    
    $arItemPreview = [
        'article' => $article,
        'element_id' => $ELEMENT_ID,
        'properties' => []
    ];
    
    $hasChanges = false;
    $itemHasMapping = false; // Флаг наличия хотя бы одного найденного соответствия

    foreach($arAllProperties as $propId => $propName) {
        $propConfig = $arPropertyConfigs[$propId] ?? null;
        if (!$propConfig) continue;
        
        $propInfo = $propConfig['prop_info'] ?? null;
		
		$oldValue = $valuesFromFile = null;
		if ($propInfo['property_type'] == 'L') {
			if ($propInfo['is_multiple'] == 'Y') {
				$oldValue = $arCurrentProps[$ELEMENT_ID][$propId]['VALUE_ENUM_ID'] ?? null;
			} else {
				$oldValue = $arCurrentProps[$ELEMENT_ID][$propId]['VALUE_ENUM_ID'] ?? null;
			}
		} else {
			$oldValue = $arCurrentProps[$ELEMENT_ID][$propId] ?? null;
		}

        $newValue = null;
        $mappingFound = false;
        $matchedKey = null;
        $searchValues = [];
        
        switch ($propConfig['type']) {
            case 'case_size':
                $index = $propConfig['index'];
                if (isset($arItem["Case size (L× W× H)"][0])) {
                    $size = $arItem["Case size (L× W× H)"][0];
                    if ($size && preg_match('/(\d+\.?\d*)\s×\s(\d+\.?\d*)\s×\s(\d+\.?\d*)/', $size, $matches)) {
                        $newValue = (float)($matches[$index + 1] ?? 0);
                        $mappingFound = true;
                        $itemHasMapping = true; // Это свойство найдено
                    }
                }
                break;
                
            case 'weight':
                if (isset($arItem["Weight"][0])) {
                    $val = $arItem["Weight"][0];
                    if ($val && preg_match('/(\d+\.?\d*)\sg/', $val, $matches)) {
                        $newValue = (float)$matches[1];
                        $mappingFound = true;
                        $itemHasMapping = true; // Это свойство найдено
                    }
                }
                break;
                
			case 'property':
				$sourceCode = $propConfig['source_code'];
				$profileMappings = $propConfig['profile_mappings'] ?? [];
				
				if (isset($arItem[$sourceCode]) && !empty($profileMappings)) {
					if (!is_array($arItem[$sourceCode]))
						$valuesFromFile = [$arItem[$sourceCode]];
					else
						$valuesFromFile = $arItem[$sourceCode];
					
					$searchValues = $valuesFromFile;
					$matchedConfig = null;
					$matchedKey = null;
					

					//prent($propInfo['property_type']);die;
					if ($propInfo['property_type'] == 'S') {
						//prent($profileMappings);
						//prent($valuesFromFile);
					}
					
					prent($valuesFromFile);
					foreach ($valuesFromFile as $fileValue) {
						if ($profileMappings[$fileValue]) {
							
							//prent($profileMappings);
							$matchedKey = $fileValue;
						} else {
							$matchedKey = findMatchingKey($fileValue, array_keys($profileMappings));
						}
						
						if ($matchedKey !== null && isset($profileMappings[$matchedKey][$propId])) {
							// Дополнительная проверка: если ключ найден, но он слишком общий - пропускаем
							$matchedConfig = $profileMappings[$matchedKey][$propId];
//prent('sssssssss');prent($propConfig);prent($profileMappings);
							// Проверяем, что найденное значение действительно соответствует
							$normalizedFileValue = mb_strtolower(trim($fileValue));
							$normalizedFileValue = preg_replace('/\s+/', ' ', $normalizedFileValue);
							$normalizedKey = mb_strtolower(trim($matchedKey));
							$normalizedKey = preg_replace('/\s+/', ' ', $normalizedKey);
							
							// Если файловое значение значительно короче ключа и не является его началом - пропускаем
							if (strlen($normalizedFileValue) < strlen($normalizedKey) * 0.5) {
								// Слишком короткое совпадение - это может быть ложное срабатывание
								continue;
							}
							
							$itemHasMapping = true;
							break;
						}
					}
					
					if ($matchedConfig) {
						if ($propInfo['property_type'] == 'S') {
							prent($matchedConfig['VALUE']);
							$newValue = $matchedConfig['VALUE'];
						} else {
							$newValue = $matchedConfig['VALUES'] ?? [];
						}
						
						if (!is_array($newValue) && $propInfo['is_multiple'] == 'Y') {
							$newValue = [$newValue];
						}
						
						if ($propInfo['is_multiple'] == 'Y' && is_array($newValue)) {
							sort($newValue);
						}
						$mappingFound = true;
					} else {
						// Маппинг не найден - пропускаем это свойство
						continue 2;
					}
				} else {
					// Нет данных для маппинга - пропускаем
					continue 2;
				}
				break;
        }
        
		$shouldAddToPreview = false;
		$newValueForPreview = null;

		if ($propInfo['is_multiple'] == 'Y') {
			$oldArray = is_array($oldValue) ? $oldValue : [];
			$newArray = is_array($newValue) ? $newValue : [];
			
			sort($oldArray);
			sort($newArray);

			if ($oldArray != $newArray) {
				$shouldAddToPreview = true;
				$newValueForPreview = $newArray;
			}
		} else {
			if ($oldValue != $newValue) {
				$shouldAddToPreview = true;
				$newValueForPreview = $newValue;
			}
		}

		if ($shouldAddToPreview) {
			$hasChanges = true;
			$arItemPreview['properties'][$propId] = [
				'old' => $oldValue,
				'new' => $newValueForPreview,
				'prop_info' => $propInfo,
				'config' => $propConfig,
				'mapping_found' => $mappingFound,
				'mapping_issue' => !$mappingFound,
				'search_values' => $searchValues,
				'matched_key' => $matchedKey,
			];
		}
    }
    
    // Если есть хотя бы одно найденное соответствие И есть изменения - добавляем в preview
    if ($itemHasMapping && $hasChanges) {
        $willBeUpdated++;
        $arPreview[] = $arItemPreview;
    }
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
            <p>Свойств: <br><strong style="font-size: 24px;"><?= count($arAllProperties) ?></strong></p>
        </div>
    </div>
</div>

<?php if(!empty($notFound)): ?>
	<?
	$notFound = sort_nested_arrays($notFound, ['model' => 'asc']);
	?>
	<div style="max-height: 200px;overflow: scroll;border: 1px solid black;padding: 20px;">
	<?foreach($notFound as $item):?>
		<p style="margin: 0 0 0 0;font-size: 13px;"><strong style=""><?= $item['model'] ?></strong></p>
	<?endforeach?>
	</div>
<?php endif; ?>

<?php if(!empty($arPreview)): ?>
<form id="previewForm" method="post" action="/admin/utilities/set_prop_json/apply_changes.php">
    <input type="hidden" name="filename_parse" value="<?= htmlspecialchars($filename_parse) ?>">
    <input type="hidden" name="profile_name" value="<?= htmlspecialchars($profile_name) ?>">
    
    <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
        <table class="table table-bordered table-striped" style="font-size: 12px;">
            <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                <tr>
					<th class="fixed-column-1" style="min-width: 150px;">Артикул</th>
					<th class="fixed-column-2" style="min-width: 100px;">ID</th>
                    <?php foreach($arAllProperties as $propId => $propName): 
                        $propInfo = $arResultProps[$propId] ?? [];
                    ?>
                        <th colspan="2" style="text-align: center; background: #e8f4fd; <?= $propInfo['is_multiple'] == 'Y' ? 'color: #dc3545;' : '' ?>">
                            <?= htmlspecialchars($propName) ?>
                            <?php if($propInfo['is_multiple'] == 'Y'): ?>
                                <br><small style="color: #dc3545;">(Множественное)</small>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th style="position: sticky; left: 0; background: white; z-index: 11;"></th>
                    <th style="position: sticky; left: 150px; background: white; z-index: 11;"></th>
                    <?php foreach($arAllProperties as $propId => $propName): ?>
                        <th style="background: #fff3cd; min-width: 150px;">Старое</th>
                        <th style="background: #d4edda; min-width: 150px;">Новое</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($arPreview as $item): ?>
                    <tr>
						<td class="fixed-column-1"><?= htmlspecialchars($item['article']) ?></td>
						<td class="fixed-column-2"><?= $item['element_id'] ?></td>
                        <?php foreach($arAllProperties as $propId => $propName): 
                            $propData = $item['properties'][$propId] ?? null;
                            $propInfo = $arResultProps[$propId] ?? [];
                            $isMultiple = ($propInfo['is_multiple'] ?? 'N') == 'Y';
                            
                            $oldValue = '';
                            if ($propData) {
                                $old = $propData['old'];

                                if ($isMultiple && is_array($old)) {
                                    $oldNames = [];
                                    foreach ($old as $enumId) {
                                        $oldNames[] = $propInfo['values'][$enumId] ?? $enumId;
                                    }
                                    $oldValue = implode('<br>', $oldNames);
                                } else {
                                    if ($isMultiple) {
                                        $oldValue = $old;
                                    } else {
                                        $oldValue = $propInfo['values'][$old] ?? $old;
                                    }
                                }
                            }
                            
                            $newValue = $propData['new'] ?? null;
                        ?>
                            <td style="background: #fff3cd; vertical-align: middle;">
                                <?php if($propData): ?>
                                    <span class="badge badge-warning">ИЗМЕНИТСЯ</span><br>
                                <?php endif; ?>
                                <?= $oldValue ?>
                            </td>
							<td style="background: #d4edda; vertical-align: middle;">
								<?php if($propData): ?>
									<?php 
									$mappingFound = $propData['mapping_found'] ?? true;
									$newValue = $propData['new'] ?? null;
									$isMultiple = $propData['prop_info']['is_multiple'] == 'Y' ? true : false;
									$propInfo = $propData['prop_info'] ?? [];
									$searchValues = $propData['search_values'] ?? [];
									$matchedKey = $propData['matched_key'] ?? null;
									?>
									
									<?php if($mappingFound): ?>
										<!-- Маппинг найден - отображаем нормально -->
										<?php if($matchedKey): ?>
											<small style="display: block; color: #28a745; font-size: 9px;">
												✓ Совпало с: "<?= htmlspecialchars($matchedKey) ?>"
											</small>
										<?php endif; ?>
										
										<?php if(!$isMultiple && ($propInfo['property_type'] ?? '') == 'N'): ?>
											<!-- Числовое свойство -->
											<input type="number" step="0.01" 
												   name="changes[<?= $item['element_id'] ?>][<?= $propId ?>]" 
												   value="<?= htmlspecialchars($newValue ?? '') ?>"
												   class="form-control form-control-sm">
										
										<?php elseif(!$isMultiple && isset($propInfo['values'])): ?>
											<!-- Одиночный выбор из списка -->
											<select name="changes[<?= $item['element_id'] ?>][<?= $propId ?>]" 
													class="form-control form-control-sm">
												<?php 
												$currentValue = '';
												if (is_array($newValue)) {
													$currentValue = $newValue[0] ?? '';
												} else {
													$currentValue = $newValue ?? '';
													if ($isMultiple && is_array($newValue) && count($newValue) > 0) {
														$currentValue = $newValue[0];
													}
												}
												?>
												<option value="">-- Не выбрано --</option>
												<?php foreach($propInfo['values'] as $id => $name): ?>
													<option value="<?= $id ?>" 
															<?= ($id == $currentValue) ? 'selected' : '' ?>>
														<?= htmlspecialchars($name) ?>
													</option>
												<?php endforeach; ?>
											</select>
										
										<?php elseif($isMultiple && isset($propInfo['values'])): ?>
											<!-- Множественный выбор -->
											<div style="max-height: 120px; overflow-y: auto; padding: 3px; border: 1px solid #ccc;">
												<?php 
												$selectedValues = [];
												if (is_array($newValue)) {
													$selectedValues = $newValue;
												} elseif (!empty($newValue)) {
													$selectedValues = [$newValue];
												}
												
												$oldValues = [];
												if ($propData['config']['insert_to'] ?? false) {
													foreach ($propData['old'] as $oldValue) {
														$selectedValues[] = $oldValue;
														$oldValues[] = $oldValue;
													}
												}
												$selectedValues = array_filter($selectedValues, function($v) {
													return !empty($v);
												});
												?>
												
												<?php if($propData['config']['insert_to'] ?? false): ?>
													<input type="hidden" 
														   name="changes_config[<?= $item['element_id'] ?>][<?= $propId ?>][INSERT_TO]" 
														   value="Y">
												<?php endif; ?>
												
												<?php foreach($propInfo['values'] as $id => $name): ?>
													<?
													$isOld = in_array($id, $oldValues) ?? false;
													?>
													<div class="form-check <?if($isOld):?> old_value<?endif?>">
														<input type="checkbox" 
															   class="form-check-input" 
															   name="changes[<?= $item['element_id'] ?>][<?= $propId ?>][]" 
															   value="<?= $id ?>"
															   <?= (in_array($id, $selectedValues)) ? 'checked' : '' ?>>
														<label class="form-check-label" style="font-size: 11px;">
															<?= htmlspecialchars($name) ?><?if($isOld):?> (old)<?endif?>
														</label>
													</div>
												<?php endforeach; ?>
											</div>
										
										<?php else: ?>
											<!-- Текстовое поле -->
											<input type="text" 
												   name="changes[<?= $item['element_id'] ?>][<?= $propId ?>]" 
												   value="<?= htmlspecialchars(is_array($newValue) ? implode(', ', $newValue) : ($newValue ?? '')) ?>"
												   class="form-control form-control-sm">
										<?php endif; ?>
										
										<?php if($propData['config']['insert_to'] ?? false): ?>
											<small class="text-info" style="font-size: 15px; display: block; margin-top: 2px;">
												<i class="fa fa-info-circle"></i> Добавится к существующим значениям
											</small>
										<?php endif; ?>
										
									<?php else: ?>
										<!-- Свойство с ненайденным маппингом не должно сюда попадать -->
										<div style="background: #f8d7da; padding: 5px; border: 1px solid #f5c6cb; border-radius: 4px;">
											<small style="color: #721c24;">Ошибка: маппинг не найден</small>
										</div>
									<?php endif; ?>
									
								<?php else: ?>
									<!-- Нет изменений для этого свойства -->
									<span class="text-muted">Нет изменений</span>
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
                if(colIndex % 2 == 0) {
                    $(this).css('background-color', '#fff3cd');
                } else {
                    $(this).css('background-color', '#d4edda');
                }
            });
        }
    );
    
    $('.table-responsive').on('change', 'input[type="checkbox"]', function() {
        var container = $(this).closest('div');
        var checkedCount = container.find('input[type="checkbox"]:checked').length;
        var maxHeight = checkedCount > 3 ? '200px' : '120px';
        container.css('max-height', maxHeight);
    });
});

$(document).ready(function() {
    function updateFixedColumns() {
        $('.fixed-column-2').css('left', '150px');
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
.form-control-sm {
    height: 28px;
    padding: 2px 5px;
    font-size: 12px;
    min-width: 120px;
}
.badge {
    font-size: 10px;
    padding: 2px 5px;
}
.form-check.old_value {
    background: #eee;
}
.form-check {
    margin-bottom: 2px;
    min-height: 18px;
}
.form-check-input {
    margin-top: 0;
    width: 14px;
    height: 14px;
}
.form-check-label {
    font-size: 11px;
    margin-left: 5px;
}
input[type="checkbox"] {
    transform: scale(0.9);
}
.table-responsive {
    --scroll-left: 0px;
    --col1-width: 150px;
    --col2-width: 100px;
}

.fixed-column-1 {
    position: sticky;
    left: 0;
    background: white;
    z-index: 10;
}

.fixed-column-2 {
    position: sticky;
    background: white;
    z-index: 10;
}

.table th.fixed-column-1,
.table th.fixed-column-2 {
    z-index: 11;
}
</style>

<?php else: ?>
<div class="alert alert-info">
    <h4>Нет изменений для применения</h4>
    <p>Все свойства товаров уже соответствуют значениям из профиля.</p>
    <a href="/admin/utilities/set_prop_json/" class="btn btn-primary">
        <i class="fa fa-arrow-left"></i> Вернуться назад
    </a>
</div>
<?php endif; ?>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");