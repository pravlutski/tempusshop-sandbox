<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule('iblock') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

global $DB;
$strSql = "SELECT * FROM ci_options";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["OPTIONS"][$row["code"]] = $row;
}
if(isset($arResult["OPTIONS"]["UPDATE_ONLINER"]["value"])){
	require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/include/classes/api_onliner.php");
	$obj = new Onliner_API;
	$res = $obj->status_pricelist($arResult["OPTIONS"]["UPDATE_ONLINER"]["value"]);
	$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"] = json_decode($res, true);
	switch($arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["statusCode"]){
		case "STATUS_WAITING": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "warning"; break;
		case "STATUS_IMPORT_ERROR": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "danger"; break;
		case "STATUS_PROCESS_ERROR": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "danger"; break;
		case "STATUS_OK": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "success"; break;
		case "STATUS_PROCESSING": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "warning"; break;
		case "STATUS_PARSE_ERROR": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "danger"; break;
	}
}

$strSql = "SELECT text FROM ci_log WHERE event = 'U' ORDER BY id desc LIMIT 0,1";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	$arResult["OPTIONS"]["UPDATE_CATALOG"]["tooltip"] = $row["text"];
}

$strSql = "SELECT text FROM ci_log WHERE event = 'C' ORDER BY id desc LIMIT 0,1";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	$arResult["OPTIONS"]["PARSE_CATALOG_ONLINER"]["tooltip"] = $row["text"];
}
/* количество товаров во временной таблице */
$strSql = "SELECT COUNT(id) as count FROM ci_price_catalog";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	$arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["count"] = $row["count"];
}
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_REVIEWS, 
	"ACTIVE"	=> "Y",
);
$rsAll = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter);
$arResult["OPTIONS"]["CNT_REVIEWS"] = $rsAll->SelectedRowsCount();
?>

<tr class="<?if($arResult["OPTIONS"]["UPDATE_CATALOG"]["value"] == "IN_PROCESS"):?>warning<?elseif($arResult["OPTIONS"]["UPDATE_CATALOG"]["value"] == "Y"):?>danger<?endif?>">
	<td>
	<?if($arResult["OPTIONS"]["UPDATE_CATALOG"]["value"] == "IN_PROCESS"):?>Каталог обновляется...
	<?elseif($arResult["OPTIONS"]["UPDATE_CATALOG"]["value"] == "Y"):?>Каталог ждет обновления
	<?else:?><span class="s-tooltip" data-toggle="tooltip" data-placement="top" title="<?=$arResult["OPTIONS"]["UPDATE_CATALOG"]["tooltip"]?>">Каталог обновлен</span><?endif?>
	</td>
	<td>
	<?if($arResult["OPTIONS"]["UPDATE_CATALOG"]["value"] == "IN_PROCESS"):?>
	<?//=$arResult["OPTIONS"]["UPDATE_CATALOG_PER"]["value"]?>
		<div class="progress" style="margin:6px 0 0 0;">
			<div class="progress-bar progress-bar-striped active" role="progressbar"
				aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_CATALOG_PER"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_CATALOG_PER"]["value"]?>%">
				<?=$arResult["OPTIONS"]["UPDATE_CATALOG_PER"]["value"]?>%
			</div>
		</div>
	<?endif?>
	</td>
	<td><?=$arResult["OPTIONS"]["UPDATE_CATALOG"]["timestamp"]?></td>
</tr>
<tr class="<?=$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"]?>">
	<td><a href="/admin/settings/onliner_report.php">Выгрузка на онлайнер</a></td>
	<td>
		<p style="line-height: 10px;font-size: 10px;margin: 0 0 0 0;"><?=$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["statusText"]?></p>
		<p style="line-height: 10px;font-size: 10px;margin: 0 0 0 0;">Выгружено - <?=$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["processedCount"]?></p>
		<p style="line-height: 10px;font-size: 10px;margin: 0 0 0 0;">Ошибок - <?=$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["errorsCount"]?></p>
	</td>
	<td><?=$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["date"]?></td>
</tr>
<tr>
	<td>Обновление минского склада</td>
	<td><a href="/admin/ajax/settings/get_store_models.php?store_id=1" style="cursor:pointer; color: #337ab7;"><?=$arResult["OPTIONS"]["UPDATE_STOCK_BY"]["value"]?></a></td>
	<td><?=$arResult["OPTIONS"]["GOOGLE_LAST_HASH"]["timestamp"]?></td>
</tr>
<tr>
	<td>Обновление московского склада</td>
	<td><a href="/admin/ajax/settings/get_store_models.php?store_id=2" style="cursor:pointer; color: #337ab7;"><?=$arResult["OPTIONS"]["UPDATE_STOCK_RU"]["value"]?></a></td>
	<td><?=$arResult["OPTIONS"]["GOOGLE_LAST_HASH_RU"]["timestamp"]?></td>
</tr>
<tr>
	<td><span class="s-tooltip" data-toggle="tooltip" data-placement="top" title="<?=strip_tags($arResult["OPTIONS"]["PARSE_CATALOG_ONLINER"]["tooltip"])?>">Парсер цен с онлайнера</span></td>
	<td></td>
	<td><?=$arResult["OPTIONS"]["PARSE_CATALOG_ONLINER"]["timestamp"]?></td>
</tr>
<tr>
	<td>Парсер цен яндекса</td>
	<td><?=$arResult["OPTIONS"]["PARSE_CATALOG_YANDEX"]["value"]?></td>
	<td><?=$arResult["OPTIONS"]["PARSE_CATALOG_YANDEX"]["timestamp"]?></td>
</tr>
<tr>
	<td>Обновление цен во временной таблице</td>
	<td><?=$arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["count"]?></td>
	<td><?=$arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["timestamp"]?></td>
</tr>
<tr>
	<td>Количество отзывов</td>
	<td><?=$arResult["OPTIONS"]["CNT_REVIEWS"]?></td>
	<td></td>
</tr>
<?
/*
$str = "";
$strSql = "SELECT * FROM ci_price WHERE store_id IN ('1','2')";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["MODELS"][$row["store_id"]][] = $row["model"];
//	$str .= $row["model"] . "\r\n";
}

<tr style="">
	<td colspan="3">
		<textarea id="textarea-store-items-1" style="position: fixed;left: -9999px;"><?=implode("\r\n", $arResult["MODELS"][1]);?></textarea>
		<textarea id="textarea-store-items-2" style="position: fixed;left: -9999px;"><?=implode("\r\n", $arResult["MODELS"][2]);?></textarea>
	</td>
</tr>
<script>
	new Clipboard('.btn-clipboard'); // Не забываем инициализировать библиотеку на нашей кнопке
</script>
*/
?>
<script>
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
});
</script>