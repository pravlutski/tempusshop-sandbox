<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main">
<?
global $DB;
$strSql = "SELECT * FROM ci_log WHERE event IN ('WB') AND text = 'Обмен с WB ошибка' ORDER BY id desc LIMIT 0,1000";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["ITEMS"][] = $row;
}

?>
<table class="table" style="font-size: 11px;">
	<thead>
		<tr>
			<th>Код</th>
			<th>Ошибка</th>
			<th>Дата</th>
		</tr>
	</thead>
	<tbody>
		<?foreach($arResult["ITEMS"] as $key => $arItem):?>
		<?
		$error = "";
		if($arItem["file_id"]){
			$detail = file_get_contents("/home/bitrix/logs/detail/{$arItem["file_id"]}.txt");
			
			$ch = unserialize($detail);
			$error = encrypt_decrypt($ch["res"], true);
			$item = encrypt_decrypt($ch["item_info"], true);
			$item = unserialize($item);
			//prent($error);
			//$code = $item["item"]["card"]["nomenclatures"][0]["vendorCode"];
			$code = $item["item"]["VendorCode"];
		}
		if(!$error) continue;

		//prent($item);die;
		//
		?>
		<tr>
			<td><?=$code?></td>
			<td><?=$error?></td>
			<td><?=$arItem["timestamp"]?></td>
		</tr>
		<?endforeach?>
	</tbody>
</table>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>