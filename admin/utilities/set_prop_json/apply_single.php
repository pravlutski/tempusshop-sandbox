<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager')) return;

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups) && !in_array(6, $arGroups)) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен']);
    exit;
}
//echo json_encode(['success' => true]);exit;
$objContent = new CPanelContent;
$bxProps = $objContent->getProps();
//prent($bxProps);die; 
$elementId = (int)$_POST['element_id'];
$changes = $_POST['changes'] ?? [];

$logger = new TsLogger("/utils/set_prop_json/");
$logger->log("LOG", "POST", $_POST); 
if($elementId && !empty($changes)) {
    $propValues = [];
    
	$propIds = [];
    foreach($changes as $propId => &$value) {
		if (!$bxProps[$propId]) continue;
		$prop = $bxProps[$propId];
		
        if(is_array($value['VALUE'])) {
			$ar = [];
			foreach ($value['VALUE'] as $k => $v) {
				$ar[$k] = str_replace(['|rbracket1|', '|rbracket2|'], ["'", '"'], $v);
			}
            //$propValues[$propId] = $value;
            $propValues[$propId] = $ar;
        } else {
			$val = str_replace(['|rbracket1|', '|rbracket2|'], ["'", '"'], $value['VALUE']);

			if ($prop['is_multiple'] == 'Y') {
				$propValues[$propId] = [$val];
			} else {
				$propValues[$propId] = $val;
			}
        }
		
		$propIds[$propId] = $propId;
    }
	unset($value);
	/*unset($propValues['ENG_DESCRIPTION']);
	unset($propValues['HEIGHT']);
	unset($propValues['DIAMETER']);
	unset($propValues['THICKNESS']);
	unset($propValues['WEIGHT']);
	unset($propValues['CASE']);
	unset($propValues['MATERIAL']);
	unset($propValues['WR']);
	unset($propValues['FEATURES']);
	//unset($propValues['BACKLIGHT']);*/

	$logger->log("LOG", "Обновляем", ['elementId' => $elementId, 'propValues' => $propValues]); 
	
    $result = CIBlockElement::SetPropertyValuesEx($elementId, false, $propValues);
    
	\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(CProSet::IB_CATALOG, $elementId);
	// отправляем в темпус.
	if ($propIds && $elementId > 0) {
		require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');
		$syncHelper = new SyncHelper();

		$syncHelper->sendPropProduct([$elementId], $propIds);
		usleep(200);
	}
	echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Нет данных для обновления']);
}

exit;