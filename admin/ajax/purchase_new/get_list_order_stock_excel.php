<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
if(isset($_POST["site_id"]) && in_array($_POST["site_id"], array("s1", "s2", "s3")))
	$website = array($_POST["site_id"]);
else
	$website = array("s1", "s2", "s3");
?>
<?
global $USER;
$website = "s1";
$arGroups = CUser::GetUserGroup($USER->GetID());
//if(!$USER->isAdmin() && !in_array(6, $arGroups) && !in_array(12, $arGroups) && !in_array(13, $arGroups)) return false;
echo "sdsds";
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){

	unlink("/var/www/bitrix/data/www/tempusshop.ru/upload/purchase.xlsx");

	$res = \Bitrix\Sale\Delivery\Services\Table::getList(array('filter' => array('ACTIVE' => 'Y')));
	while ($dev = $res->Fetch()) {
		$arDelivery[$dev["CODE"]] = $dev["NAME"];
	}
	//Готовы к отгрузке
	$arResult = $arTmp = array();
	$objService = new OrderService;

	$arFilter = array(
		"LID" => $website,//array("s1", "s2"),
		"STATUS_ID" => array("SE", "TA", "CO","CR", "CL"),//array("N"),//, "WT"), 
		"!CANCELED" => "Y",
	);
	
	//$arOrder = $objService->getOrder(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);
	$arOrder = $objService->getOrderCache(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);
	
	foreach($arOrder as $key => $arItem){
		foreach($arItem["BASKET"] as $k => $basket){
			for($i = 1; $i <= $basket["QUANTITY"]; $i++){
				$arResult["ITEMS"][] = array(
					"ID" => $arItem["ID"],
					"ORDER_ID" => $arItem["ORDER_ID"],
					"PRODUCT_ID" => $basket["PRODUCT_ID"],
					"SITE_ID" => $arItem["LID"],
					"COMMENTS" => $arItem["COMMENTS"],
					"DELIVERY_ID" => $arItem["DELIVERY_ID"],
				);
			}
		}
	}
	
	unset($arItem);
	foreach($arResult["ITEMS"] as $key => &$arItem){
		$objRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $arItem["PRODUCT_ID"]), false, false, array('PROPERTY_CML2_ARTICLE'));
        if ($res = $objRes->GetNext()){
			$arItem["ARTICLE"] = $res["PROPERTY_CML2_ARTICLE_VALUE"];
		}
	}
	unset($arItem);
	
	$strSql = "SELECT * FROM ci_price WHERE supplier_id IN ('47', '44', '71')";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arStock[$row["supplier_id"]][$row["model"]][] = $row;
	}
	
	/* очищаем от склада */
	foreach($arResult["ITEMS"] as $key => &$arItem){
		if($arItem["ARTICLE"]){
			//$supp_id = ($arItem["SITE_ID"] == "s1" ? 47 : ("s2" ? 44 : -1));
			switch($arItem["SITE_ID"]){
				case "s1":
					$supp_id = 47;
					break;
				case "s1":
					$supp_id = 44;
					break;
				case "s1":
					$supp_id = 71;
					break;
				default: break;
			}
			//if($arItem["ARTICLE"] == "GA-700-1A")
			//	prent($arStock[$supp_id][$arItem["ARTICLE"]]);
			if(!isset($arStock[$supp_id][$arItem["ARTICLE"]])){
				unset($arResult["ITEMS"][$key]);
				if(is_array($arStock[$supp_id][$arItem["ARTICLE"]])){
					$key_stock = array_keys($arStock[$supp_id][$arItem["ARTICLE"]]);
					unset($arStock[$supp_id][$arItem["ARTICLE"]][$key_stock[0]]);
				}

				if(is_array($arStock[$supp_id][$arItem["ARTICLE"]]) && count($arStock[$supp_id][$arItem["ARTICLE"]]) == 0){
					unset($arStock[$supp_id][$arItem["ARTICLE"]]);
				}
			}
		}else{
			unset($arResult["ITEMS"][$key]);
		}
	}
	unset($arItem);
	//prent($arResult["ITEMS"]);die;
	$arResult["ITEMS"] = sort_nested_arrays($arResult["ITEMS"], array("ARTICLE" => "asc"));
	
	$arResult["SHIPMENT"] = array();
	foreach($arResult["ITEMS"] as $key => $arItem){
		$arResult["SHIPMENT"][$arItem["SITE_ID"]][$arItem["ARTICLE"]] = $arItem;
	}
	//prent($arResult["SHIPMENT"]);die;
	
	//список справа
	
	$arResult["PURCHASE"] = $arr = array();
	
	$objService = new OrderService;
	$objSupplier = new CPanelSupplier;
	$arResult["SUPPLIER_LIST"] = $objSupplier->getList();
	foreach($arResult["SUPPLIER_LIST"] as $arSup)
		$arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
	$strSql = "SELECT * FROM ci_purchase WHERE active = 'Y' AND site_id = 's1'";// AND site_id = '".$psFilter["website"]."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["PURCHASE"][] = $row;
	}
	//prent($arResult["PURCHASE"]);
	//
	$ids = $arOrder = $sFilter = array();
	foreach($arResult["PURCHASE"] as $key => $arItem){
		if($arItem["order_id"] > 0)
			$ids[] = $arItem["order_id"];
		
		//$sFilter[$arItem["model"]] = $arItem["model"];
		$sFilter[md5($arItem["model"].$arItem["supp_id"])] = "(model = '{$arItem["model"]}' AND supplier_id = '{$arItem["supp_id"]}')";
	}
	//prent($sFilter);
	if(is_array($sFilter) && count($sFilter) > 0){
		$add_where = "((".implode(") OR (",$sFilter)."))";
		$strSql = "SELECT model FROM ci_price WHERE {$add_where} GROUP BY model";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arResult["ITEMS_STOCK"][$row["model"]] = $row["model"];
		}
		
	}

	//$tmp = $objService->getOrder(array(), $arFilter = array("ID" => $ids));
	$tmp = $objService->getOrderCache(array(), $arFilter = array("ID" => $ids));
	
	foreach($tmp as $key => $arItem){
		$arAllOrder[$arItem["ID"]] = $arItem;
	}
	//
	foreach($arResult["PURCHASE"] as $key => &$arItem){
		$arItem["supp_name"] = $arResult["SUPPLIER_NAME"][$arItem["supp_id"]];
		
		if(isset($arAllOrder[$arItem["order_id"]])){
			$arItem["item_active"] = "N";
			$arOrder = $arAllOrder[$arItem["order_id"]];
			$arItem["order_status_id"] = $arOrder["STATUS_ID"];
			$arItem["order_canceled"] = $arOrder["CANCELED"];
			
			$arItem["order_comment"] = $arOrder["COMMENTS"];
			$arItem["order_delivery_id"] = $arOrder["DELIVERY_ID"];
			$arItem["order_number_id"] = $arOrder["ORDER_ID"];
			//prent($arOrder);die;
			$tmp = explode(".", $arItem["order_basket_id"]);
			$order_basket_id = $tmp[0];
			//если товар отредактирован и товар удалил, то ставим флаг
			foreach($arOrder["BASKET"] as $k => $v){
				if($v["ID"] == $order_basket_id)
					$arItem["item_active"] = "Y";
			}
		}
		if($arResult["ITEMS_STOCK"][$arItem["model"]])
			$arItem["in_stock"] = "Y";
		else
			$arItem["in_stock"] = "N";
			
		/*if($arItem["order_id"]){
			$tmp = $objService->getOrder(array(), $arFilter = array("ID" => $arItem["order_id"]));
			$arItem["order_comment"] = $tmp[0]["COMMENTS"];
			$arItem["order_delivery_id"] = $tmp[0]["DELIVERY_ID"];
			//prent($tmp);die;
		}*/
	}
	unset($arItem);
//	prent($arResult["SHIPMENT"]);
//prent($arResult["PURCHASE"]);die;
	$arResult["PURCHASE"] = sort_nested_arrays($arResult["PURCHASE"], array("supp_name" => "asc", "model" => "asc"));
	
	$i = 1;
	
	require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';
	
	$arCol = array(0 => "A", 1 => "B", 2 => "C", 3 => "D", 4 => "E");
	$objPHPExcel = new PHPExcel();
	$objPHPExcel->setActiveSheetIndex(0);
	$sheet = $objPHPExcel->getActiveSheet();
				
	$sheet->setTitle("tempus");
	$sheet->getStyle("A:E")->getFont()->setName("Arial");
	$sheet->getStyle("A:E")->getFont()->setSize(10);

	$sheet->getStyle('D:E')->getAlignment()->setWrapText(true);
	
	//$sheet->getColumnDimension("A")->setWidth(20);
	//$sheet->getColumnDimension("C")->setWidth(20);
	//$sheet->getColumnDimension("D")->setWidth(35);
	//$sheet->getColumnDimension("E")->setWidth(25);

	//	$sheet->getColumnDimension("A")->setWidth(20);
	$sheet->getColumnDimension("A")->setWidth(17);
	//$sheet->getColumnDimension("B")->setWidth(15);
	$sheet->getColumnDimension("C")->setWidth(7);
	$sheet->getColumnDimension("D")->setWidth(20);
	$sheet->getColumnDimension("E")->setWidth(20);
	
	$sheet->getStyle('A')->getAlignment()->applyFromArray(
		array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT)
	);
	$sheet->getStyle('B')->getAlignment()->applyFromArray(
		array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT)
	);
	

	
	$sheet->setCellValue("A{$i}", "На складе");
	$sheet->getStyle("A{$i}")->getFont()->setSize(20);
	$i++;
	
	if(is_array($arResult["SHIPMENT"]) && count($arResult["SHIPMENT"]) > 0){

		foreach($arResult["SHIPMENT"] as $site_id => $arPrice){
			foreach($arPrice as $key => $arItem){
				$col_num = 0;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["ARTICLE"]);$col_num++;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["ORDER_ID"]);$col_num++;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", ($arDelivery[$arItem["DELIVERY_ID"]] ? $arDelivery[$arItem["DELIVERY_ID"]] : $arItem["DELIVERY_ID"]));$col_num++;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["COMMENTS"]);$col_num++;
				$i++;
			}
		}
		/*
		foreach($arPrice as $key => $arItem){
			$col_num = 0;
			
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["article"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["b_price"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["price"]);$col_num++;
			
			if($_POST["price_competitors"] == "Y") {$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["price_platform"]);$col_num++;}
			if($_POST["price_competitors"] == "Y") {$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["price_platform_av"]);$col_num++;}
			
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["revenue"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["revenue_p"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arSupName[$arItem["supplier_id"]]);$col_num++;

			$i++;
		}
		*/	

		
	//	$writer->save('php://output');
	//	exit;
		$i+=2;
	}
	
	if(is_array($arResult["PURCHASE"]) && count($arResult["PURCHASE"]) > 0){
		$sheet->setCellValue("A{$i}", "Закупка");$col_num++;
		$sheet->getStyle("A{$i}")->getFont()->setBold(true);
		$sheet->getStyle("A{$i}")->getFont()->setSize(20);
		$i++;
		/*
		$i = 0;
		$objPHPExcel->createSheet();
		$objPHPExcel->setActiveSheetIndex(1);
	  
		$sheet = $objPHPExcel->getActiveSheet();
					
		$sheet->setTitle("Отгрузка");
		$sheet->getStyle("A:D")->getFont()->setName("Arial");
		$sheet->getStyle("A:D")->getFont()->setSize(10);
		*/
		
		foreach($arResult["PURCHASE"] as $key => $arItem){
			$col_num = 0;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["model"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["order_number_id"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", ($arDelivery[$arItem["order_delivery_id"]] ? $arDelivery[$arItem["order_delivery_id"]] : $arItem["order_delivery_id"]));$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["order_comment"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["supp_name"]);$col_num++;
			$i++;
		}

	}
	
	$sheet->getStyle("A1:E{$i}")->applyFromArray(
		   array(
			   'borders' => array(
				   'allborders' => array(
					   'style' => PHPExcel_Style_Border::BORDER_THIN,
					   //'color' => array('rgb' => 'fff')
				   )
			   )
		   )
	);
	
	//$sheet->getPageSetup()->SetPaperSize(PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4);
	
/*	$writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');  
	$writer->save("/var/www/bitrix/data/www/tempusshop.ru/upload/purchase.xlsx", __FILE__);

	$GLOBALS['APPLICATION']->RestartBuffer();
	header('Cache-Control: max-age=0');
	
	$now = gmdate("D, d M Y H:i:s");
	header("Expires: {$now} GMT");
	header("Last-Modified: {$now} GMT");
	header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
	header ('Pragma: public'); // HTTP/1.0
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="purchase.xlsx"');
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
*/
				header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
				header('Content-Disposition: attachment;filename="purchase.xlsx"');
				header('Cache-Control: max-age=0');
				
				$writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');  
				
				ob_end_clean();
				$writer->save('php://output');
				exit;
	?>
	<?
}
?>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');