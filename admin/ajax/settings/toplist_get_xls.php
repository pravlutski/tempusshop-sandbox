<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?

global $DB;
if(!CModule::IncludeModule("panel.manager")) return die("no module");

if(!isset($_REQUEST["site_id"]) || !in_array($_REQUEST["site_id"], array("s1", "s2", "s3", "wb"))) return die("no site");

$website = trim(htmlspecialchars($_REQUEST["site_id"]));

global $DB;

if($website == "wb"){
	$strSql = "SELECT bitrix_id, source, article as model FROM ci_wb_top";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["ITEMS"][] = $row;
	}
}else{
	$strSql = "SELECT * FROM ci_top_models WHERE site_id = '".$DB->ForSql($website)."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["ITEMS"][] = $row;
	}

}

// print_r($arResult["ITEMS"]);
// die();
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';

$arCol = array(0 => "A", 1 => "B", 2 => "C", 3 => "D");
$objPHPExcel = new PHPExcel();
$objPHPExcel->setActiveSheetIndex(0);
$sheet = $objPHPExcel->getActiveSheet();

$sheet->setTitle("TOP лист {$website}");
$sheet->getStyle("A:B")->getFont()->setName("Arial");
$sheet->getStyle("A:B")->getFont()->setSize(10);

$i = 1;

foreach($arResult["ITEMS"] as $key => $arItem){
	$col_num = 0;

	$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["bitrix_id"]);$col_num++;
	$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["model"]);$col_num++;

	if($website == "wb"){
		$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["source"]);$col_num++;
	} else {
		$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["sell_quantity"]);$col_num++;
	}
	$i++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="price_' . date("Y-m-d_H-i-s") . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');

ob_end_clean();
$writer->save('php://output');
exit;
				/*
header('Cache-Control: max-age=0');

// If you're serving to IE over SSL, then the following may be needed
$now = gmdate("D, d M Y H:i:s");
header("Expires: {$now} GMT");
header("Last-Modified: {$now} GMT");
header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
header ('Pragma: public'); // HTTP/1.0

require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel/Writer/Excel5.php';

mb_internal_encoding('latin1');

header ( "Content-type: application/vnd.ms-excel" );

$GLOBALS['APPLICATION']->RestartBuffer();

$objWriter = new PHPExcel_Writer_Excel5($objPHPExcel);

$objWriter->save('php://output');*/
?>
