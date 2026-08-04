<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;

$arGroups = CUser::GetUserGroup($USER->GetID());
if(CModule::IncludeModule("panel.manager") && ($USER->isAdmin())):?>
<?require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';?>
<?

	$obj = new CExchange("msk");

	$strSql = "SELECT * FROM ci_suppliers WHERE id = '103'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
			$arSupp = $row;
			$arSupp["settings"] = json_decode( $arSupp["settings"], true );
			$arSupp["settings_pricelist"] = json_decode( $arSupp["settings_pricelist"], true );
			$marginPrice = $arSupp["settings_pricelist"]['margin'];
	}


	foreach ($obj->getForCHR() as $key => $arItem){

		if (!empty($marginPrice)) {

						$extraDays = floor( $arItem['stockDays'] ) - 20;
						if ( $extraDays > 0 ){
								if ( $extraDays > 40){
										$discPrice = 40;
								} else{
										$discPrice = $extraDays;
								}
								$price = $arItem["PRICE"] * (1 - $discPrice / 100);
						} else {
								$price = $arItem["PRICE"] * (1 + $marginPrice / 100);
						}

		} else {
			$price = $arItem["price"] . ' (цена без изменений)';
		}

		$arResult[] = [
			'name' => $arItem['name'],
			'bitrix_id' => $arItem['XML_ID'],
			'stockDays' => $arItem['stockDays'],
			'stock' => $arItem['stock'],
			'price' => round($price,0) / 100,
		];
	}


	//prent($arGroup);prent($arBitrix);die;
	$filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/get_chr.xlsx";

		$objPHPExcel = new PHPExcel();
		$objPHPExcel->setActiveSheetIndex(0);
		$sheet = $objPHPExcel->getActiveSheet();
		$title = 'Отчет по складам ХРОНОС';
		//print_r($title);
		//die();
		$sheet->setTitle($title);
		$sheet->getColumnDimension("A")->setWidth(30);
		$sheet->getColumnDimension("B")->setWidth(15);
		$sheet->getColumnDimension("C")->setWidth(15);
		$sheet->getColumnDimension("D")->setWidth(15);
		$sheet->getColumnDimension("E")->setWidth(15);
		$sheet->setCellValue("A1", "Модель");
		$sheet->getStyle("A1")->getFont()->setBold(true);
		$sheet->setCellValue("B1", "Дней на складе");
		$sheet->getStyle("B1")->getFont()->setBold(true);
		$sheet->setCellValue("C1", "Кол-во на складе");
		$sheet->getStyle("C1")->getFont()->setBold(true);
		$sheet->setCellValue("D1", "Цена");
		$sheet->getStyle("D1")->getFont()->setBold(true);
		$sheet->setCellValue("E1", "XML_ID в системе");
		$sheet->getStyle("E1")->getFont()->setBold(true);
		$row = 2;
		foreach($arResult as $key => $arItem){
			$column = 0;
			$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column, $row, $arItem['name']);
			$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+1, $row, $arItem['stockDays']);
			$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+2, $row, $arItem['stock']);
			$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+3, $row, $arItem['price']);
			$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+4, $row, $arItem['bitrix_id']);
			$row++;

		}
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		$objWriter->save($filepath);

	file_force_download($filepath);

?>
<?endif?>
<?die;?>
