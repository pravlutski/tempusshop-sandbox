<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$log_id = intval($_POST["id"]);
if(CModule::IncludeModule("panel.manager") && $log_id > 0){
	global $DB;
	$strSql = "SELECT * FROM ci_log WHERE id = '{$log_id}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);?>
	<?if ($row = $results->Fetch()):?>
		<?
		$detail = "";
		if($row["file_id"]){
			$detail = file_get_contents("/var/www/bitrix_logs/details_log/{$row["file_id"]}.txt");
			$ch = unserialize($detail);
			if($ch !== false) {
				$pr = true;
				$detail = $ch;
			}
		}

		prent($detail);
		?>
		<div class="modal-header" style="max-height: 300px;overflow: scroll;">
			<?=$row["text"]?>
		</div>
		<?if(is_string($detail) && strlen($detail) > 0):?>
		<div class="modal-header" style="max-height: 300px;overflow: scroll;">
			<?//=nl2br($row["detail"])?>
			<?=($pr === true ? "<pre>" . print_r($detail,true) . "</pre>" : nl2br($detail));?>
			<?if(isset($detail["item_info"]) > 0):?>
			<?=(encrypt_decrypt($detail["item_info"], true))?>
			<?endif?>
			<?//=($pr === true ? "sdfdsf" : "sdsss");?>
		</div>
		<?endif?>
	<?else:?>
	<div class="modal-header">
		Не найдены детали лога
	</div>
	<?endif?>

	<?

}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');