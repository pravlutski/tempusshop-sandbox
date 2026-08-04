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

$arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_PRICE_OZTI","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD");
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_CATALOG,
);

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

while ($el = $result->GetNext()){
	$arEl[$el['PROPERTY_CML2_ARTICLE_VALUE']] = $el['ID'];
}


	$objPHPExcel = new PHPExcel();
	$objPHPExcel->setActiveSheetIndex(0);
	$sheet = $objPHPExcel->getActiveSheet();
	$title = 'ID TOVAROV';
	$sheet->setTitle($title);
	$sheet->getColumnDimension("A")->setWidth(20);
	$sheet->setCellValue("A1", "ID");
	$sheet->getColumnDimension("B")->setWidth(40);
	$sheet->setCellValue("B1", "Модель");
	$row = 2;
	//print_r($models);
	foreach ($arEl as $key => $model) {

			$sheet->setCellValue('A'.$row, $model);
			$sheet->setCellValue('B'.$row, $key);

			$row++;
	}

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$name = 'TOV_ID';
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
