<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$log_id = intval($_POST["id"]);
if(CModule::IncludeModule("panel.manager") && $log_id > 0){
	$content = new CPanelContent;
	$propBX = $content->getProps();
	global $DB;
	$strSql = "SELECT * FROM ci_application WHERE id = '{$log_id}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);?>
	<?if ($row = $results->Fetch()):?>
		<?
		//$props = [];
		//foreach(json_decode($row["props"], true) as $code => $value ) $props[$code] = $value;
		//foreach(json_decode($row["fields"], true) as $code => $value ) $props[$code] = $value;
		prent($row);
		$props = json_decode($row["props"], true);
		foreach($props as $code => $val){
			if(!$propBX[$code] || $propBX[$code]["property_type"] != "L") continue;
			if(is_array($val)){
				$tmp = $val;
				foreach($val as $k => $v){
					if(
						$propBX[$code]["values"][$v]
					){
						$tmp[$k] = $propBX[$code]["values"][$v];
					}
				}
				$props[$code] = $tmp;
			}else{
				if(
					$propBX[$code]["values"][$val]
				){
					$props[$code] = $propBX[$code]["values"][$val];
					
				}
			}
		}
		
		?>
		<pre><?=print_r(json_decode($row["fields"], true), true)?></pre>
		<pre><?=print_r($props, true)?></pre>
		<p><?=$row["detail_text"]?></p>
		<div class="modal-header" style="max-height: 300px;overflow: scroll;">
			<?=$row["text"]?>
		</div>
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