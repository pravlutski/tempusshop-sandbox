<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main" class="col-sm-12 row">
<?
global $DB;
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';
$objUtils = new CPanelUtils;
$arResult['ITEMS'] = $objUtils->getAltAnList();
prent($arResult['ITEMS']);


$arCol = array(0 => "A", 1 => "B");

$objPHPExcel = new PHPExcel();
$objPHPExcel->setActiveSheetIndex(0);
$sheet = $objPHPExcel->getActiveSheet();
				
$sheet->setTitle("Альтернативные артикулы");
$sheet->getStyle("A:B")->getFont()->setName("Arial");
$sheet->getStyle("A:B")->getFont()->setSize(10);

$i = 1;

foreach($arResult['ITEMS'] as $article => $arItem){
	foreach($arItem as $k => $val){
		$sheet->setCellValue("A{$i}", $article);
		$sheet->setCellValue("B{$i}", $val);
		$i++;
	}
}

unset($arItem);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="article.xlsx"');
header('Cache-Control: max-age=0');
				
$writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');  

ob_end_clean();
$writer->save('php://output');
exit;
?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>