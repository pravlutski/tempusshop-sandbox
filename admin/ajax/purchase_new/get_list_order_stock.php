<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2", "s3")))
	$website = array($_POST["website"]);
else
	$website = array("s1", "s2", "s3");
?>
<div class="row">
<?
global $USER;
$arGroups = $USER->GetUserGroupArray();
if($USER->isAdmin() || in_array(6, $arGroups)) $arResult["ACCESS"] = true;

if(!$arResult["ACCESS"]) return;

$start = debug_microtime_float();

if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$arResult = $arTmp = array();
	$objService = new OrderService;
	$objService->getPropOrderFlg = false;
	//prent($arResult["SUPPLIER_NAME"]);
	$arFilter = array(
		"LID" => $website,//array("s1", "s2"),
		"STATUS_ID" => array("SE", "TA", "CO", "CL"),//array("N"),//, "WT"),
		"!CANCELED" => "Y",
	);

	//$arOrder = $objService->getOrder(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);
	$arOrder = $objService->getOrderCache(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);

	foreach($arOrder as $key => $arItem){
		foreach($arItem["BASKET"] as $k => $basket){
			for($i = 1; $i <= $basket["QUANTITY"]; $i++){
				$arResult["ITEMS"][] = array(
					"ID" => $arItem["ID"],
					"PRODUCT_ID" => $basket["PRODUCT_ID"],
					"SITE_ID" => $arItem["LID"],
					"COMMENTS" => $arItem["COMMENTS"],
					"DELIVERY_ID" => $arItem["DELIVERY_ID"],
					"ORDER_NUMBER_ID" => $arItem["ORDER_ID"],
				);
			}
		}
	}

	//

	//prent($arPurchase);
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
		//prent($row);die;
		for($i = 1; $i <= $row["count"]; $i++){
			$arStock[$row["supplier_id"]][$row["model"]][] = $row;
		}

	}
	//prent($arResult["ITEMS"]);
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
			//if(isset($arStock[$supp_id][$arItem["ARTICLE"]])){

				unset($arResult["ITEMS"][$key]);

			}else{

			}

			if(is_array($arStock[$supp_id][$arItem["ARTICLE"]])){
				$key_stock = array_keys($arStock[$supp_id][$arItem["ARTICLE"]]);
				unset($arStock[$supp_id][$arItem["ARTICLE"]][$key_stock[0]]);
			}

			if(is_array($arStock[$supp_id][$arItem["ARTICLE"]]) && count($arStock[$supp_id][$arItem["ARTICLE"]]) == 0){
				unset($arStock[$supp_id][$arItem["ARTICLE"]]);
			}
		}else{
			unset($arResult["ITEMS"][$key]);
		}
	}
	unset($arItem);
	//prent($arStock);
	//prent($arResult["ITEMS"]);die;
	$arResult["SHIPMENT"] = array();
	foreach($arResult["ITEMS"] as $key => $arItem){
		$arResult["SHIPMENT"][$arItem["SITE_ID"]][$arItem["ARTICLE"]][] = $arItem;
	}
	//prent($arResult["SHIPMENT"]);
	if($_REQUEST["xls"] == "Y" || $USER->getID() == 11587){

		require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';

		$arCol = array(0 => "A", 1 => "B", 2 => "C", 3 => "D", 4 => "E");
		$objPHPExcel = new PHPExcel();
		$objPHPExcel->setActiveSheetIndex(0);
		$sheet = $objPHPExcel->getActiveSheet();

		$sheet->setTitle("tempus");
		$sheet->getStyle("A:D")->getFont()->setName("Arial");
		$sheet->getStyle("A:D")->getFont()->setSize(10);


		$i = 1;
		foreach($arResult["SHIPMENT"] as $site_id => $arPrice){
			foreach($arPrice as $key => $arItem){
				$col_num = 0;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["ARTICLE"]);$col_num++;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["ID"]);$col_num++;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["DELIVERY_ID"]);$col_num++;
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
		$writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		$writer->save("/var/www/bitrix/data/www/tempusshop.ru/upload/purchase.xlsx", __FILE__);

	//	$writer->save('php://output');
	//	exit;
	}
	if(is_array($arResult["SHIPMENT"]) && count($arResult["SHIPMENT"]) > 0):?>
		<div class="col-sm-12">
			<h4>Готовы к отгрузке</h4>
			<table class="table">
				<thead>
					<tr>
						<th style="width: 80%">Модель</th>
						<th style=""></th>
						<th style="width: 20%">Сайт</th>
					</tr>
				</thead>
				<tbody>
				<?foreach($arResult["SHIPMENT"] as $site_id => $arPrice):?>
					<?foreach($arPrice as $key => $arItems):?>
						<?foreach($arItems as $k => $arItem):?>
					<tr>
						<td><?=$arItem["ARTICLE"]?></td>
						<td><?=$arItem["ORDER_NUMBER_ID"]?></td>
						<td><?=$site_id?></td>
					</tr>
						<?endforeach?>
					<?endforeach?>
				<?endforeach?>
				</tbody>
			</table>

		</div>
	<?
	endif;
}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}

$end = debug_microtime_float();
prent($end - $start, 0, 1);

?>
</div>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
