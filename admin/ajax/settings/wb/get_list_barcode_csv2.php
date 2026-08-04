<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;

$arGroups = CUser::GetUserGroup($USER->GetID());
if($USER->isAdmin() || in_array(6, $arGroups) || in_array(12, $arGroups) || in_array(13, $arGroups)):?>
		
<?
if(CModule::IncludeModule("panel.manager")){
	
	$strSql = "SELECT * FROM ci_barcode_update WHERE ACTIVE = 'Y'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$ar[$row["ID"]] = $row;
		if($row["PRODUCT_ID"]){
			$arIDs[$row["PRODUCT_ID"]] = $row["PRODUCT_ID"];
		}
	}

	//вытягиваем названия из битрикса
	//$arIDs[] = 3016;
	if(is_array($arIDs) && count($arIDs) > 0){
		$res = CIBlockElement::GetList(array(), array("ID" => $arIDs), array("ID", "NAME", "PROPERTY_WBARTICLE"));
		while($ar_fields = $res->GetNext()){
			$arBitrix[$ar_fields["ID"]] = $ar_fields;
		}
	}
	
	$filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/get_list_barcode.csv";

	require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';

	$arCol = array(0 => "A", 1 => "B", 2 => "C", 3 => "D", 4 => "E", 5 => "F", 6 => "G", 7 => "H", 8 => "I", 9 => "J", 10 => "K");
	$objPHPExcel = new PHPExcel();
	$objPHPExcel->setActiveSheetIndex(0);
	$sheet = $objPHPExcel->getActiveSheet();
				
	$sheet->setTitle("tempus");
	$sheet->getStyle("A:E")->getFont()->setName("Arial");
	$sheet->getStyle("A:E")->getFont()->setSize(10);
	
	
	$arResult = array();
	$arResult[] = array("Наименование","ШК 1","ШК 2");
	foreach($ar as $id => $arItem){


		$arResult[] = array($arBitrix[$arItem["PRODUCT_ID"]]["NAME"], $arItem["BARCODE"], $arItem["BARCODE_UPDATE"]);

		
	}
	
	$i = 1;

	foreach($arResult as $key => $arItem){
		foreach($arItem as $k => $val){
			$sheet->setCellValue("{$arCol[$k]}{$i}", $val);
		}
		$i++;
	}
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="barcode_' . date("Y-m-d_H-i-s") . '.xlsx"');
	header('Cache-Control: max-age=0');
				
	$writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');  
				
	ob_end_clean();
	$writer->save('php://output');


}
?>
<?endif?>
<?die;?>