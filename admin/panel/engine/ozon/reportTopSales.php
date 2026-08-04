<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);
require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';
CModule::IncludeModule('panel.manager');
use Bitrix\Main\Application,
	Bitrix\Main\Loader;
if (isset($_GET['cabinet']) || !empty($_GET['cabi
net'])) {
	$CABINET = $_GET['cabinet'];
} else if (isset($argv[1])) {
	$CABINET = $argv[1];
} else {
	die('WRONG CABINET');
}

if (empty($CABINET)) {
	die('WRONG CABINET');
}

if ($CABINET == 'TI') {
	$prefix_old = '_new';
} else {
	$prefix_old = '';
}

$CurDB = new DBPanel();

$result = $CurDB->query("SELECT * FROM ozon_main_settings_{$CABINET}");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
	$arSetting[$row['name']] = $row['value'];
}
$url = $arSetting['api_url'];
$client_id = $arSetting['client_id'];
$token = $arSetting['key'];
unset($result);
unset($rows);

// $result = $CurDB->query("SELECT * FROM ozon_sales_pi_{$CABINET} WHERE pi_sets = 'main'");
// $rows = $CurDB->fetchAll($result);
// foreach ($rows as $row) {
// 	$tops = json_decode($row['tops']);
// }
$result = $CurDB->query("SELECT * FROM ozon_top_models");
$rows = $CurDB->fetchAll($result);
$tops = [];
foreach ($rows as $row) {
	$tops[] = $row['model'];
}
unset($result);
unset($rows);
if ( empty($tops) ) die('Таблица ozon_top_models пуста');

$result = $CurDB->query("SELECT * FROM ozon_fbo_stock_{$CABINET}");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
	$checkFBOPrice[$row['article']] = $row['article'];
}
unset($result);
unset($rows);



foreach ($tops as $ka => $va) {
	$execCurl[] = $va;
}

$arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_PRICE_OZTI","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD");
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	"=PROPERTY_CML2_ARTICLE" => $execCurl,
);

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

$index = 0;
while ($el = $result->GetNext()){
	$arEl[$el['PROPERTY_WBARTICLE_VALUE']] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
}

if (!empty($arEl)) {
	$data = [
		"filter" => array("offer_id" => array_keys($arEl)),
		"limit" => 1000
	];
	$data_string = json_encode($data);
	$ch = curl_init($url . '/v5/product/info/prices');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Api-Key:' . $token,
			'Client-Id:' . $client_id,
			'Content-Type:application/json'
		));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		$res = curl_exec($ch);
		curl_close($ch);

		$res = json_decode($res, true);

	file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$CABINET}/last.reportTopSales.txt", print_r($res, true));
}



foreach ($res['items'] as $result) {

	$arPrice[$arEl[$result['offer_id']]] = ['our' => $result['price']['marketing_seller_price'], 'sell' =>	$result['price']['marketing_price']];
}

// print_r($checkFBOPrice);
// die();
$tmp = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/'.$CABINET.'/sales/top.txt');
$arLog = json_decode($tmp,true);

if (!empty($arLog)) {

	$objPHPExcel = new PHPExcel();
	$objPHPExcel->setActiveSheetIndex(0);
	$sheet = $objPHPExcel->getActiveSheet();
	$title = 'Модели ТОП в Акциях';
	$sheet->setTitle($title);
	$sheet->getColumnDimension("A")->setWidth(20);
	$sheet->setCellValue("A1", "Модель");
	$sheet->getColumnDimension("B")->setWidth(40);
	$sheet->setCellValue("B1", "Акция");
	$sheet->getColumnDimension("C")->setWidth(20);
	$sheet->setCellValue("C1", "ФБО");
	$sheet->getColumnDimension("D")->setWidth(20);
	$sheet->setCellValue("D1", "Наша цена");
	$sheet->getColumnDimension("E")->setWidth(20);
	$sheet->setCellValue("E1", "Цена продажи");
	$sheet->getColumnDimension("F")->setWidth(20);
	$sheet->setCellValue("F1", "СОИНВЕСТ (%)");
	$row = 2;
	//print_r($models);
	foreach ($tops as $model) {
			 $sheet->setCellValue('A'.$row, $model);
			 if (isset($arLog[$model])) {
				 $sheet->setCellValue('B'.$row, $arLog[$model]['sale']);
			 } else {
				  $sheet->setCellValue('B'.$row, 'Не участвует');
			}
			if (isset($checkFBOPrice[$model])) {
				$sheet->setCellValue('C'.$row, 'да');
			} else {
				$sheet->setCellValue('C'.$row, 'нет');
			}

			$sheet->setCellValue('D'.$row, $arPrice[$model]['our']);
			$sheet->setCellValue('E'.$row, $arPrice[$model]['sell']);
			$differencePercentage = ((floatval($arPrice[$model]['our']) - floatval($arPrice[$model]['sell'])) / floatval($arPrice[$model]['our'])) * 100;
			$sheet->setCellValue('F'.$row, round($differencePercentage),0);
			unset($differencePercentage);

			$row++;
	}

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$name = 'TopSales' .$CABINET;
	$objWriter->save('/var/www/bitrix/data/www/tempusshop.ru/upload/nakladnie_cache/'.$name.'.xlsx');
	$file_path = '/var/www/bitrix/data/www/tempusshop.ru/upload/nakladnie_cache/'.$name.'.xlsx';

	if (file_exists($file_path)) {
	    // Закрываем все открытые буферы вывода
	    while (ob_get_level()) {
	        ob_end_clean();
	    }

	    // Убеждаемся, что до функции header() не было никакого вывода
	    header('Content-Description: File Transfer');
	    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
	    header('Expires: 0');
	    header('Cache-Control: must-revalidate');
	    header('Pragma: public');
	    header('Content-Length: ' . filesize($file_path));
	    // Убеждаемся, что нет пробелов перед readfile()
	    readfile($file_path);
	    exit;
	} else {
	    echo 'Файл не найден.';
	}
}
