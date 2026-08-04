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
		//prent($row);
		?>
		<form method="POST" action="#" id="form-edit-product">
			<input type="hidden" name="id" value="<?=$id?>">
			<p style="margin: 0 0 2px 0;">RU - <input name="active_ru" type="checkbox" value="Y" <?if($row["active_ru"] == "Y"):?>checked<?endif?>></p>
			<p style="margin: 0 0 2px 0;">BY - <input name="active_by" type="checkbox" value="Y" <?if($row["active_by"] == "Y"):?>checked<?endif?>></p>
			<p style="margin: 0 0 2px 0;">PL - <input name="active_pl" type="checkbox" value="Y" <?if($row["active_pl"] == "Y"):?>checked<?endif?>></p>
			<p style="margin: 0 0 2px 0;">YA - <input name="active_ya" type="checkbox" value="Y" <?if($row["active_ya"] == "Y"):?>checked<?endif?>></p>
			<p style="margin: 0 0 2px 0;">OS - <input name="active_os" type="checkbox" value="Y" <?if($row["active_os"] == "Y"):?>checked<?endif?>></p>
			<p style="margin: 0 0 2px 0;">WB - <input name="active_wb" type="checkbox" value="Y" <?if($row["active_wb"] == "Y"):?>checked<?endif?>></p>
			<input type="submit" class="btn" value="Сохранить">
		</form>
	<?else:?>
		<p style="color:red;">Не найден товар с ID - <?=$id?></p>
	<?endif?>

<?else:?>
	<p style="color:red;">Не корректный запрос</p>
<?endif?>