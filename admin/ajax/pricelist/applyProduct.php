<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;
$id = intval($_POST["id"]);
$objPricelist = new CPanelPricelist;
$objProduct = new CPanelProduct;
$objExchange = new CExchange;
global $DB;
?>
<?if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0):?>
	<?
	$strSql = "SELECT * FROM ci_price WHERE id = '{$id}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	?>
	<?if ($row = $results->Fetch()):?>
		<?
		if($_POST["active_ru"] == "Y") $active_ru = "Y"; else $active_ru = "N";
		if($_POST["active_by"] == "Y") $active_by = "Y"; else $active_by = "N";
		if($_POST["active_pl"] == "Y") $active_pl = "Y"; else $active_pl = "N";
		if($_POST["active_ya"] == "Y") $active_ya = "Y"; else $active_ya = "N";
		if($_POST["active_os"] == "Y") $active_os = "Y"; else $active_os = "N";
		if($_POST["active_wb"] == "Y") $active_wb = "Y"; else $active_wb = "N";

		$in = array(
			"active_ru" => "'" . $active_ru . "'",
			"active_by" => "'" . $active_by . "'",
			"active_pl" => "'" . $active_pl . "'",
			"active_ya" => "'" . $active_ya . "'",
			"active_os" => "'" . $active_os . "'",
			"active_wb" => "'" . $active_wb . "'",
		);
		$ID = $DB->Update("ci_price", $in, "WHERE id='{$id}'", $err_mess.__LINE__);
		//prent($ID);
		//prent($err_mess);
		$model = $row["model"];
		$b_id = CPanelProduct::findArticle($model);
		if($b_id > 0){
			$ex = $objExchange->updateProduct($b_id);
			if($ex === true){
				//$cache_manager = Bitrix\Main\Application::getInstance()->getTaggedCache();
				//$cache_manager->ClearByTag("iblock_id_".CProSet::IB_CATALOG);
			}
		}

		//обновляем данные об активности в отдельной таблице где храним неиспользуемые товары
		$ar = array(
			"active_ru" => $active_ru,
			"active_by" => $active_by,
			"active_pl" => $active_pl,
			"active_ya" => $active_ya,
			"active_os" => $active_os,
			"active_wb" => $active_wb,
			"model" => $row["model"],
			"brand_id" => $row["brand_id"],
			"supplier_id" => $row["supplier_id"],
		);
		$objPricelist->updatePriceUnused($ar);
		?>
	<?else:?>
		<p style="color:red;">Не найден товар с ID - <?=$id?></p>
	<?endif?>

<?else:?>
	<p style="color:red;">Не корректный запрос</p>
<?endif?>
