#!/usr/bin/php
<?php
// чекаем свойства для всего каталога. отправляем в темпус
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("local_cron_rabbitmq_updater_props_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');

set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

Loader::includeModule("main");
Loader::includeModule("iblock");

function generateHash(array $data): string
{
	unset($data['PROP_HASH_PROPERTY']);
    array_walk_recursive($data, function(&$value, $key) {
        if (is_array($value)) {
            ksort($value);
        }
    });
    
    ksort($data);
    
    $serialized = serialize($data);
    return md5($serialized);
}

$syncHelper = new SyncHelper();

$checkedProp = [
	'HASH_PROPERTY', 'NEWEST', 'HIT', 'IN_STOCK', 'CASE', 'POPULAR_TAG'
]; // свойства которые чекаем

$arFilter = [
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	"!PROPERTY_CML2_ARTICLE" => false,
	"!IBLOCK_SECTION_ID" => 2458, // пропускаем Подарочные сертификаты
	//"ACTIVE" => "Y",
	//"ID" => 211152,  
];

$arSelect = [
	'ID', 'SORT', 'ACTIVE', 'IBLOCK_ID', 'NAME', 'CODE',
];
		
$res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

while ($ob = $res->GetNextElement()){
	$arFields = $ob->GetFields();
	
	$arData[$arFields['ID']] = [
		'SORT' => $arFields['SORT'],
		'ACTIVE' => $arFields['ACTIVE'],
		'SECTIONS' => [],
	];
	
	$rsSection = CIBlockElement::GetElementGroups($arFields['ID'], true, ['NAME', 'CODE']);
	while($arSection = $rsSection->Fetch()) {
		$arData[$arFields['ID']]['SECTIONS'][] = $arSection['CODE'];
	}

	$properties = $ob->GetProperties();
	
	
	
	$hashProp = '';
	foreach ($properties as $prop) {

		if (!$prop['CODE'] || !in_array($prop['CODE'], $checkedProp)) {
			continue;
		}

		$arData[$arFields['ID']]['PROP_' . $prop['CODE']] = $prop['VALUE'];
		
	}

}
//prent($arData);
//file_put_contents('/var/www/bitrix_logs/tempus/rabbitmq/updater_props', print_r(['arData' => $arData], true), 8);  
$arIds = [];
foreach ($arData as $productId => $arItem) {
	$currentHash = generateHash($arItem);
	//prent($currentHash);
	if ($currentHash != $arItem['PROP_HASH_PROPERTY']) {
		$arIds[] = $productId;
		CIBlockElement::SetPropertyValuesEx($productId, false, array('HASH_PROPERTY' => $currentHash));
	}
}

if (count($arIds) > 0) {
	foreach (array_chunk($arIds, 100) as $ids) {
		unset($checkedProp['PROP_HASH_PROPERTY']);
		$syncHelper->sendPropProduct($ids, $checkedProp, ['SORT', 'ACTIVE', 'SECTIONS']);
		
		file_put_contents('/var/www/bitrix_logs/tempus/rabbitmq/updater_props', print_r(['date' => date("Y-m-d H:i:s"), 'ids' => $ids], true), 8);
		sleep(1); 
	}
}
$workers->updateStatus("N");
?>
