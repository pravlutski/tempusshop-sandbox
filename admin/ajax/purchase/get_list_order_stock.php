<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
use Bitrix\Sale\Order;
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

	CModule::IncludeModule('sale');

	$platforms = [
	    4 => 'Яндекс.Маркет: TEMPUS - Наручные часы',
	    6 => 'WB',
	    7 => 'SBER',
	    8 => 'OZON',
	    9 => 'Авито',
	    10 => 'Маркетплейс Маркета DBS',
	    11 => 'YMFBS',
	    12 => 'YMDBS',
	    13 => 'SITES',
	    15 => 'ONLINER',
	    19 => 'WBBY',
	];

	// $arOrder = $objService->getOrder(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);
	$arOrder = $objService->getOrderCache(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);

	foreach($arOrder as $key => $arItem) {
	    try {
	        $orderArm = Order::load($arItem["ID"]);

	        if ($orderArm) {
	            $source = '';
	            $tradeBindingCollection = $orderArm->getTradeBindingCollection();
	            if ($tradeBindingCollection && count($tradeBindingCollection) > 0) {
	                $bindings = $tradeBindingCollection->toArray();
					if (isset($platforms[$bindings[0]['TRADING_PLATFORM_ID']])) {
						 $source = $platforms[$bindings[0]['TRADING_PLATFORM_ID']];
					}

	            }
	            foreach($arItem["BASKET"] as $k => $basket) {
	                for($i = 1; $i <= $basket["QUANTITY"]; $i++) {
	                    $arResult["ITEMS"][] = array(
	                        "ID" => $arItem["ID"],
	                        "PRODUCT_ID" => $basket["PRODUCT_ID"],
	                        "SITE_ID" => $arItem["LID"],
	                        "COMMENTS" => $arItem["COMMENTS"],
	                        "DELIVERY_ID" => $arItem["DELIVERY_ID"],
	                        "ORDER_NUMBER_ID" => $arItem["ORDER_ID"],
	                        "DATE_CREATE" => $arItem['DATE_INSERT'],
	                        "SOURCE" => $source,
	                    );
	                }
	            }
	        }
	    } catch (\Exception $e) {
	        AddMessage2Log("Error loading order ID {$arItem["ID"]}: " . $e->getMessage());
	    }
	}

	$arResult["ITEMS"] = sort_nested_arrays($arResult["ITEMS"], array("ID" => "asc"));

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
	/*foreach($arResult["ITEMS"] as $key => &$arItem){
		if($arItem["ARTICLE"]){
			//$supp_id = ($arItem["SITE_ID"] == "s1" ? 47 : ("s2" ? 44 : -1));
			$suppS1 = $suppS2 = [];
			switch($arItem["SITE_ID"]){
				case "s1":
					$supps = array(47,128);
					$suppS1 = [47,128];
					break;
				case "s2":
					$supps = array(44);
					$suppS2 = [44];
					break;
				//case "s1":
				//	$supps = array(71);
				//	break;
				default:
					$supps = [];
					break;
			}

			//print_r($supp_id);
			$supp_id = false;

			if ($arItem["SITE_ID"] == 's1' && $suppS1) {
				foreach ($suppS1 as $k => $suppId) {
					if(!isset($arStock[$suppId][$arItem["ARTICLE"]])){
						$supp_id = $suppId;
					}
				}
			} elseif($arItem["SITE_ID"] == 's2' && $suppS2) {
				foreach ($suppS2 as $k => $suppId) {
					if(!isset($arStock[$suppId][$arItem["ARTICLE"]])){
						$supp_id = $suppId;
					}
				}
			}
			//prent($supp_id);
			//	prent($arStock[$supp_id][$arItem["ARTICLE"]]);
			//foreach ($supps as $k => $value) {
			//	if(!isset($arStock[$value][$arItem["ARTICLE"]])){
			//		$supp_id = $value;
			//	}
			//}

			//if($arItem["ARTICLE"] == "78370671"){
			//	prent($arStock[$supp_id][$arItem["ARTICLE"]]);
			//}
			if($supp_id && !isset($arStock[$supp_id][$arItem["ARTICLE"]])){
			//if(isset($arStock[$supp_id][$arItem["ARTICLE"]])){

				unset($arResult["ITEMS"][$key]);

			}else{

			}

			if($supp_id && is_array($arStock[$supp_id][$arItem["ARTICLE"]])){
				$key_stock = array_keys($arStock[$supp_id][$arItem["ARTICLE"]]);
				unset($arStock[$supp_id][$arItem["ARTICLE"]][$key_stock[0]]);
			}

			if($supp_id && is_array($arStock[$supp_id][$arItem["ARTICLE"]]) && count($arStock[$supp_id][$arItem["ARTICLE"]]) == 0){
				unset($arStock[$supp_id][$arItem["ARTICLE"]]);
			}
		}else{
			unset($arResult["ITEMS"][$key]);
		}
	}
	unset($arItem);*/

	$siteSuppliersMap = [
		's1' => [47, 128],
		's2' => [44],
	];

	$activeSiteSuppliersMap = [];
	foreach ($website as $siteId) {
		if (isset($siteSuppliersMap[$siteId])) {
			$activeSiteSuppliersMap[$siteId] = $siteSuppliersMap[$siteId];
		}
	}

	foreach ($arResult["ITEMS"] as $key => &$arItem) {
		if (empty($arItem["ARTICLE"])) {
			unset($arResult["ITEMS"][$key]);
			continue;
		}

		$siteId = $arItem["SITE_ID"];
		$availableSuppliers = $activeSiteSuppliersMap[$siteId] ?? [];

		if (empty($availableSuppliers)) {
			unset($arResult["ITEMS"][$key]);
			continue;
		}

		$foundSupplier = null;
		foreach ($availableSuppliers as $supplierId) {
			if (isset($arStock[$supplierId][$arItem["ARTICLE"]]) &&
				!empty($arStock[$supplierId][$arItem["ARTICLE"]])) {
				$foundSupplier = $supplierId;
				break;
			}
		}

		if (!$foundSupplier) {
			unset($arResult["ITEMS"][$key]);
			continue;
		}
		if ($arItem['ORDER_NUMBER_ID'] == 742875) {
			prent($arItem);
			prent($foundSupplier);
			prent($arStock[$foundSupplier][$arItem["ARTICLE"]]);
		}
		if (is_array($arStock[$foundSupplier][$arItem["ARTICLE"]])) {
			$keys = array_keys($arStock[$foundSupplier][$arItem["ARTICLE"]]);
			if (!empty($keys)) {
				unset($arStock[$foundSupplier][$arItem["ARTICLE"]][$keys[0]]);

				if (empty($arStock[$foundSupplier][$arItem["ARTICLE"]])) {
					unset($arStock[$foundSupplier][$arItem["ARTICLE"]]);
				}
			}
		}
	}
	unset($arItem);


	//prent($arResult["ITEMS"]);
	//prent($arStock);
	//prent($arResult["ITEMS"]);die;
	$arResult["SHIPMENT"] = array();
	$displayItems = $arResult['ITEMS'];
	foreach($arResult["ITEMS"] as $key => $arItem){
		$arResult["SHIPMENT"][$arItem['DATE_CREATE']][$arItem["SITE_ID"]][$arItem["ARTICLE"]][] = $arItem;
	}
	krsort($arResult["SHIPMENT"]);
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
	 foreach($arResult["SHIPMENT"] as $date => $arSite){
		foreach($arSite as $site_id => $arPrice){
			foreach($arPrice as $key => $arItem){
				$col_num = 0;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["ARTICLE"]);$col_num++;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["ID"]);$col_num++;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["DELIVERY_ID"]);$col_num++;
				$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["COMMENTS"]);$col_num++;
				$i++;
			}
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
	usort($displayItems, function($a, $b){
		return strtotime($a['DATE_CREATE']) - strtotime($b['DATE_CREATE']);
	});
  // print_r($arResult["SHIPMENT"]);
	if(is_array($arResult["SHIPMENT"]) && count($arResult["SHIPMENT"]) > 0):?>
		<div class="col-sm-12">
			<h4>Готовы к отгрузке</h4>
			<table class="table">
				<thead>
					<tr>
						<th style="width: 20%">Модель</th>
						<th style="width: 20%">Источник</th>
						<th style="width: 20%">Дата</th>
						<th style=""></th>
						<th style="width: 10%">Сайт</th>
					</tr>
				</thead>
				<tbody>
					<?foreach ($displayItems as $key => $item):?>
						<tr>
							<td><?=$item["ARTICLE"]?></td>
							<td><?=$item["SOURCE"]?></td>
							<td><?=$item["DATE_CREATE"]?></td>
							<td><a href="/bitrix/admin/sale_order_view.php?amp%3Bfilter=Y&%3Bset_filter=Y&lang=ru&ID=<?=$item["ID"]?>"><?=$item["ORDER_NUMBER_ID"]?></a></td>
							<td><?=$item['SITE_ID']?></td>
						</tr>
					<?endforeach;?>
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
