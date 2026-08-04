<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';

use Bitrix\Main\Application,
		Bitrix\Main\Loader;
global $DB;

$filepath = "/var/www/bitrix/data/www/tempusshop.ru/admin/test/log_ozon.xlsx";
$objPHPExcel = new PHPExcel();
$objPHPExcel->setActiveSheetIndex(0);
$sheet = $objPHPExcel->getActiveSheet();
$title = 'Лог цен товров ОЗОН';
$sheet->setTitle($title);




$rows = 2;
$column = 0;
$strSql = "SELECT * FROM ci_log WHERE price_id = 'OS'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$i=0;
while ($row = $results->Fetch()){
	$ddt = explode('</a>',$row['text']);
	$ddt = $ddt[1];
	$ddtd = explode('>>',$ddt);
	$oldPrice = trim($ddtd[0]);
	$newPrice = trim($ddtd[1]);
	$article = $row['search'];
	$date = $row['timestamp'];
	$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column, $rows, $article);
  $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+1, $rows, $oldPrice);
	$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+2, $rows, $newPrice);
	$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+3, $rows, $date);
	$rows++;
}


$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$objWriter->save($filepath);
