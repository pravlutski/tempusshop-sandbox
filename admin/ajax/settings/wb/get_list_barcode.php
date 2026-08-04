<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;

$arGroups = CUser::GetUserGroup($USER->GetID());
if($USER->isAdmin() || in_array(6, $arGroups) || in_array(12, $arGroups) || in_array(13, $arGroups)):?>
		
<?
if(CModule::IncludeModule("panel.manager")){
	
	$strSql = "SELECT * FROM ci_catalog_barcode";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$ar[] = $row;

		$arArticle[$row["ARTICLE"]] = $row["ARTICLE"];

	}

	//вытягиваем названия из битрикса
	if(is_array($arArticle) && count($arArticle) > 0){
		$res = CIBlockElement::GetList(array(), array("PROPERTY_CML2_ARTICLE" => $arArticle), array("ID", "NAME", "PROPERTY_CML2_ARTICLE", "PROPERTY_WBARTICLE", "PROPERTY_PROP_MAXYSS_WB"));
		while($ar_fields = $res->GetNext()){
			$arBitrix[$ar_fields["PROPERTY_CML2_ARTICLE_VALUE"]] = $ar_fields;
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
	$arResult[] = array("Бренд","Артикул поставщика","Размер","Артикул","Штрихкод товара","Минимальный вес ювелирных изделий, гр");
	foreach($ar as $key => $arItem){

		$tmp = CUtil::JsObjectToPhp($arBitrix[$arItem["ARTICLE"]]["~PROPERTY_PROP_MAXYSS_WB_VALUE"]);
		$tmp = array_values($tmp);
		$brand = $tmp[0]["params"][0]["value"];
		
		$arResult[] = array($brand, $arBitrix[$arItem["ARTICLE"]]["~PROPERTY_WBARTICLE_VALUE"], 0, $arItem["ARTICLE"], $arItem["BARCODE"], "");

		
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