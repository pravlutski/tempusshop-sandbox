<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule('iblock') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;
$objPricelist = new CPanelPricelist;
global $DB;
$strSql = "SELECT * FROM ci_options";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["OPTIONS"][$row["code"]] = $row;
}

if($arResult["OPTIONS"]["ONLINER_LAST_EXCH"]["value"]){

	$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"] = json_decode($arResult["OPTIONS"]["ONLINER_LAST_EXCH"]["value"], true);

	//$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["statusText"] = htmlentities($arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["statusText"]);
	//prent($arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["statusText"]);
	switch($arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["statusCode"]){
		case "STATUS_WAITING": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "warning"; break;
		case "STATUS_IMPORT_ERROR": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "danger"; break;
		case "STATUS_PROCESS_ERROR": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "danger"; break;
		case "STATUS_OK": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "success"; break;
		case "STATUS_PROCESSING": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "warning"; break;
		case "STATUS_PARSE_ERROR": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "danger"; break;
	}
}elseif(isset($arResult["OPTIONS"]["UPDATE_ONLINER"]["value"]) && $arResult["OPTIONS"]["UPDATE_ONLINER"]["value"] != "START"){
	require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/include/classes/api_onliner.php");
	$obj = new Onliner_API;
	$res = $obj->status_pricelist($arResult["OPTIONS"]["UPDATE_ONLINER"]["value"]);

	$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"] = json_decode($res, true);

	if(!in_array($arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["statusCode"], array("STATUS_WAITING", "STATUS_PROCESSING"))){
		CProSet::setOption("ONLINER_LAST_EXCH", $res);
	}
	switch($arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["statusCode"]){
		case "STATUS_WAITING": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "warning"; break;
		case "STATUS_IMPORT_ERROR": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "danger"; break;
		case "STATUS_PROCESS_ERROR": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "danger"; break;
		case "STATUS_OK": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "success"; break;
		case "STATUS_PROCESSING": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "warning"; break;
		case "STATUS_PARSE_ERROR": $arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "danger"; break;
	}
}elseif($arResult["OPTIONS"]["UPDATE_ONLINER"]["value"] == "START"){
	$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["statusText"] = "Выгрузка запущена. Подождите окончания операции";
	$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["date"] = $arResult["OPTIONS"]["UPDATE_ONLINER"]["timestamp"];
	$arResult["OPTIONS"]["UPDATE_ONLINER_INFO"]["className"] = "danger";
}

$strSql = "SELECT text FROM ci_log WHERE event = 'C' ORDER BY id desc LIMIT 0,1";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	$arResult["OPTIONS"]["PARSE_CATALOG_ONLINER"]["tooltip"] = $row["text"];
}
/* количество товаров Парсер цен с онлайнера */
$strSql = "SELECT COUNT(id) as count FROM ci_catalog_onliner";

		$strSql2 = "SELECT COUNT(p.id) as count
		FROM
			ci_catalog_onliner p
		LEFT JOIN
			ci_onliner_articles	a ON p.id=a.id";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	$arResult["OPTIONS"]["PARSE_CATALOG_ONLINER"]["count"] = $row["count"];
}
//$price = $objPricelist->getOnlinerPriceByFilter(array());
//prent($price);
//prent($arResult["OPTIONS"]["PARSE_CATALOG_ONLINER"]);
$strSql = "SELECT text FROM ci_log WHERE event = 'U' ORDER BY id desc LIMIT 0,1";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	$arResult["OPTIONS"]["UPDATE_CATALOG"]["tooltip"] = $row["text"];
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


/* количество товаров во временной таблице */
$strSql = "SELECT COUNT(id) as count FROM ci_ceneo_price";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	$arResult["OPTIONS"]["PARSE_CENEO"]["count"] = $row["count"];
	$arResult["OPTIONS"]["PARSE_CENEO_URI"]["count"] = $row["count"];
}

//prent($arResult["OPTIONS"]["PARSE_CENEO_PER"]["value"]);

$strSql = "SELECT type_price, COUNT(*) as cnt FROM ci_yandex_price GROUP BY type_price";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["OPTIONS"]["YANDEX_CNT_{$row["type_price"]}"] = $row["cnt"];
}

$strSql = "SELECT * FROM whds_control_panel LIMIT 1";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["CONTROL_WB"] = $row;
}

exec("df -h",$output,$code);
foreach($output as $key => $val){
	if(strripos($val, "md0p1")) $k = $key;
}
$tmp = explode(" ", $output[$k]);
$tmp = array_diff($tmp, array('', '/'));
$tmp = array_values($tmp);
//prent($output);
//$arResult["OPTIONS"]["FREE_SPACE"] = $tmp;
$arResult["OPTIONS"]["FREE_SPACE"] = [];
$arSkip = [
	"Mounted on",
	"/run",
	"/dev/shm",
	"/run/lock",
	"/boot/efi",
	"/run/user/0"
];
foreach ($output as $line) {
    // Разбиваем строку по пробелам, удаляя пустые элементы
    $parts = preg_split('/\s+/', trim($line));

    if (count($parts) >= 6) {
        $mounted_on = implode(' ', array_slice($parts, 5));
        if(in_array($mounted_on, $arSkip)) continue;
        $arResult["OPTIONS"]["FREE_SPACE"][] = [
            "FULL_SIZE" => $parts[1],  // Size
            "AVAIL"     => $parts[2],
            "FREE"     => $parts[3],
            "PER"       => str_replace("%", "", $parts[4]),  // Use%
            "MOUNTED"   => $mounted_on // Добавил точку монтирования для идентификации
        ];
    }
}
//prent($arResult["OPTIONS"]["FREE_SPACE"]);
$arStocksInfo = [
	"UPDATE_STOCK_BY" => ["name" => "Минск", "key" => "MOYSKLAD_LAST_EXCH_BY"],
	"UPDATE_STOCK_RU" => ["name" => "Москва", "key" => "MOYSKLAD_LAST_EXCH_RU"],
	"UPDATE_STOCK_RU_2" => ["name" => "Москва 2", "key" => "MOYSKLAD_LAST_EXCH_RU_2"],
	"UPDATE_STOCK_RU_IMPORT" => ["name" => "Импорт", "key" => "MOYSKLAD_LAST_EXCH_RU_IMPORT"],
	"UPDATE_STOCK_MSK" => ["name" => "Опт 1", "key" => "MOYSKLAD_LAST_EXCH_ORDER_MSK"],
	"UPDATE_STOCK_MSK2" => ["name" => "Опт 2", "key" => "MOYSKLAD_LAST_EXCH_ORDER_MSK2"],
	"UPDATE_STOCK_PL" => ["name" => "Польша", "key" => "MOYSKLAD_LAST_EXCH_PL"],
];
$arPriceInfo = [
	["name" => "RU", "key" => "UPDATE_PRICE_RU"],
	["name" => "BY", "key" => "UPDATE_PRICE_BY"],
	["name" => "PL", "key" => "UPDATE_PRICE_PL"],
	["name" => "YA", "key" => "UPDATE_PRICE_YA"],
	["name" => "O/S", "key" => "UPDATE_PRICE_OS"],
	["name" => "OZTI", "key" => "UPDATE_PRICE_OZTI"],
	["name" => "WB", "key" => "UPDATE_PRICE_WB"],
	["name" => "WBTL", "key" => "UPDATE_PRICE_WBTL"],
	["name" => "WBBY", "key" => "UPDATE_PRICE_WBBY"],
	["name" => "SB", "key" => "UPDATE_PRICE_SB"],
	["name" => "AV", "key" => "UPDATE_PRICE_AV"],
];

	$arResult["OPTIONS"]['MOYSKLAD_PROFIT_BY_6']['value'] = explode( ' ', $arResult["OPTIONS"]['MOYSKLAD_PROFIT_BY_6']['value'])[1];
	$arResult["OPTIONS"]['MOYSKLAD_PROFIT_BY_12']['value'] = explode( ' ', $arResult["OPTIONS"]['MOYSKLAD_PROFIT_BY_12']['value'])[1];
	$arResult["OPTIONS"]['MOYSKLAD_PROFIT_RU_6']['value'] = explode( ' ', $arResult["OPTIONS"]['MOYSKLAD_PROFIT_RU_6']['value'])[1];
	$arResult["OPTIONS"]['MOYSKLAD_PROFIT_RU_12']['value'] = explode( ' ', $arResult["OPTIONS"]['MOYSKLAD_PROFIT_RU_12']['value'])[1];


$service = PanelManager::getPriceManager();
$arPrices = $service->getTypePrices(); 
?>

<tr>
	<td>Обновление складов</td>
	<td></td>
	<td>
		<? foreach ( $arStocksInfo as $key => $value){
			$dateUpd = explode( ' ', $arResult['OPTIONS'][ $value['key'] ]['timestamp'] )[0];
			$style = ( $dateUpd != date('Y-m-d') ? '#f2dede' : 'white');
			echo '<p style="background-color:'.$style.';font-size: 11px; margin:0; padding: 2px 0; display: block">';
			echo "{$value['name']} ({$arResult['OPTIONS'][ $key ]['value']}) - {$arResult['OPTIONS'][ $value['key'] ]['timestamp']}";
			echo '</p>';
		}?>
	</td>
</tr>

<tr>
	<td>Обновление цен</td>
	<td>
		<?foreach ($arPrices as $price):?>
			<?
			$option = $price['option_update'];
			?>
			<?if(is_numeric($arResult["OPTIONS"][$option]["value"])):?>
				<?if($arResult["OPTIONS"][$option]["value"] != 100):?>
				<span style="float: left;margin: 5px 0 0 0;"><?=$price['id']?></span>
				<div class="progress" style="margin:6px 0 0 0;">
					<div class="progress-bar progress-bar-striped active" role="progressbar"
						aria-valuenow="<?=$arResult["OPTIONS"][$option]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"][$option]["value"]?>%">
						<?=round($arResult["OPTIONS"][$option]["value"], 2)?>%
					</div>
				</div>
				<?endif?>
			<?elseif(strlen($arResult["OPTIONS"][$option]["value"]) > 0):?>
				RU - <?=$arResult["OPTIONS"][$option]["value"]?><br>
			<?endif?>
			
		<?endforeach?>

	<td>
		<?
		foreach ($arPrices as $price) {
			$name = $price['name'];
			$option = $price['option_update'];
			
			$dateUpd = explode( ' ', $arResult['OPTIONS'][$option]['timestamp'] )[0];
			$class = ( $dateUpd != date('Y-m-d') ? 'danger' : '');
			echo '<p style="background-color:'.$style.';font-size: 11px; margin:0; padding: 2px 0; display: block">';
			echo "{$name} - {$arResult['OPTIONS'][$option]['timestamp']}";
			echo '</p>';
		}
		?>
	</td>
		<?/*if(is_numeric($arResult["OPTIONS"]["UPDATE_PRICE_RU"]["value"])):?>
			<?if($arResult["OPTIONS"]["UPDATE_PRICE_RU"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">RU</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_RU"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_RU"]["value"]?>%">
					<?=round($arResult["OPTIONS"]["UPDATE_PRICE_RU"]["value"], 2)?>%
				</div>
			</div>
			<?endif?>
		<?elseif(strlen($arResult["OPTIONS"]["UPDATE_PRICE_RU"]["value"]) > 0):?>
			RU - <?=$arResult["OPTIONS"]["UPDATE_PRICE_RU"]["value"]?><br>
		<?endif?>
		<?if(is_numeric($arResult["OPTIONS"]["UPDATE_PRICE_BY"]["value"])):?>
			<?if($arResult["OPTIONS"]["UPDATE_PRICE_BY"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">BY</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_BY"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_BY"]["value"]?>%">
					<?=$arResult["OPTIONS"]["UPDATE_PRICE_BY"]["value"]?>%
				</div>
			</div>
			<?endif?>
		<?elseif(strlen($arResult["OPTIONS"]["UPDATE_PRICE_BY"]["value"]) > 0):?>
			BY - <?=$arResult["OPTIONS"]["UPDATE_PRICE_BY"]["value"]?><br>
		<?endif?>
		<?if(is_numeric($arResult["OPTIONS"]["UPDATE_PRICE_PL"]["value"])):?>
			<?if($arResult["OPTIONS"]["UPDATE_PRICE_PL"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">PL</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_PL"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_PL"]["value"]?>%">
					<?=$arResult["OPTIONS"]["UPDATE_PRICE_PL"]["value"]?>%
				</div>
			</div>
			<?endif?>
		<?elseif(strlen($arResult["OPTIONS"]["UPDATE_PRICE_PL"]["value"]) > 0):?>
			PL - <?=$arResult["OPTIONS"]["UPDATE_PRICE_PL"]["value"]?><br>
		<?endif?>
		<?if(is_numeric($arResult["OPTIONS"]["UPDATE_PRICE_YA"]["value"])):?>
			<?if($arResult["OPTIONS"]["UPDATE_PRICE_YA"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">YA</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_YA"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_YA"]["value"]?>%">
					<?=$arResult["OPTIONS"]["UPDATE_PRICE_YA"]["value"]?>%
				</div>
			</div>
			<?endif?>
		<?elseif(strlen($arResult["OPTIONS"]["UPDATE_PRICE_YA"]["value"]) > 0):?>
			YA - <?=$arResult["OPTIONS"]["UPDATE_PRICE_YA"]["value"]?><br>
		<?endif?>
		<?if(is_numeric($arResult["OPTIONS"]["UPDATE_PRICE_OS"]["value"])):?>
			<?if($arResult["OPTIONS"]["UPDATE_PRICE_OS"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">O/S</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_OS"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_OS"]["value"]?>%">
					<?=$arResult["OPTIONS"]["UPDATE_PRICE_OS"]["value"]?>%
				</div>
			</div>
			<?endif?>
		<?elseif(strlen($arResult["OPTIONS"]["UPDATE_PRICE_OS"]["value"]) > 0):?>
			O/S - <?=$arResult["OPTIONS"]["UPDATE_PRICE_OS"]["value"]?><br>
		<?endif?>
		<?if($arResult["OPTIONS"]["UPDATE_PRICE_WB"]["value"] > 0 && $arResult["OPTIONS"]["UPDATE_PRICE_WB"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">WB</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_WB"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_WB"]["value"]?>%">
					<?=$arResult["OPTIONS"]["UPDATE_PRICE_WB"]["value"]?>%
				</div>
			</div>
		<?endif?>
		<?if($arResult["OPTIONS"]["UPDATE_PRICE_WBTL"]["value"] > 0 && $arResult["OPTIONS"]["UPDATE_PRICE_WBTL"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">WBTL</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_WBTL"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_WBTL"]["value"]?>%">
					<?=$arResult["OPTIONS"]["UPDATE_PRICE_WBTL"]["value"]?>%
				</div>
			</div>
		<?endif?>
		<?if($arResult["OPTIONS"]["UPDATE_PRICE_WBBY"]["value"] > 0 && $arResult["OPTIONS"]["UPDATE_PRICE_WBBY"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">WBBY</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_WBBY"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_WBBY"]["value"]?>%">
					<?=$arResult["OPTIONS"]["UPDATE_PRICE_WBBY"]["value"]?>%
				</div>
			</div>
		<?endif?>
		<?if($arResult["OPTIONS"]["UPDATE_PRICE_OZTI"]["value"] > 0 && $arResult["OPTIONS"]["UPDATE_PRICE_OZTI"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">OZ TI</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_OZTI"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_OZTI"]["value"]?>%">
					<?=$arResult["OPTIONS"]["UPDATE_PRICE_OZTI"]["value"]?>%
				</div>
			</div>
		<?endif?>
		<?if($arResult["OPTIONS"]["UPDATE_PRICE_SB"]["value"] > 0 && $arResult["OPTIONS"]["UPDATE_PRICE_SB"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">SB</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_SB"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_SB"]["value"]?>%">
					<?=$arResult["OPTIONS"]["UPDATE_PRICE_SB"]["value"]?>%
				</div>
			</div>
		<?endif?>
		<?if($arResult["OPTIONS"]["UPDATE_PRICE_AV"]["value"] > 0 && $arResult["OPTIONS"]["UPDATE_PRICE_AV"]["value"] != 100):?>
			<span style="float: left;margin: 5px 0 0 0;">AV</span>
			<div class="progress" style="margin:6px 0 0 0;">
				<div class="progress-bar progress-bar-striped active" role="progressbar"
					aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_PRICE_AV"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_PRICE_AV"]["value"]?>%">
					<?=$arResult["OPTIONS"]["UPDATE_PRICE_AV"]["value"]?>%
				</div>
			</div>
		<?endif
	</td>
	<td>
		<? foreach ( $arPriceInfo as $key => $value){
			$dateUpd = explode( ' ', $arResult['OPTIONS'][ $value['key'] ]['timestamp'] )[0];
			$class = ( $dateUpd != date('Y-m-d') ? 'danger' : '');
			echo '<p style="background-color:'.$style.';font-size: 11px; margin:0; padding: 2px 0; display: block">';
			echo "{$value['name']} - {$arResult['OPTIONS'][ $value['key'] ]['timestamp']}";
			echo '</p>';
		}?>
	</td>*/?>
</tr>

<tr>
	<td>МойСклад</td>
	<td></td>
	<td>
		<p style="font-size: 11px; margin:0; padding: 2px 0">Топ s1 (<?=$arResult["OPTIONS"]["UPDATE_TOP_s1"]["value"]?>) - <?=$arResult["OPTIONS"]["UPDATE_TOP_s1"]["timestamp"]?></p>
		<p style="font-size: 11px; margin:0; padding: 2px 0">Топ s2 (<?=$arResult["OPTIONS"]["UPDATE_TOP_s2"]["value"]?>) - <?=$arResult["OPTIONS"]["UPDATE_TOP_s2"]["timestamp"]?></p>
		<p style="font-size: 11px; margin:0; padding: 2px 0">Топ WB (<?=$arResult["OPTIONS"]["UPDATE_TOP_wb"]["value"]?>) - <?=$arResult["OPTIONS"]["UPDATE_TOP_wb"]["timestamp"]?></p>
		<p style="font-size: 11px; margin:0; padding: 2px 0">Приемки - <?=$arResult["OPTIONS"]["MS_LAST_UPDATE_SUPPLY"]["timestamp"]?></p>
		<p style="font-size: 11px; margin:0; padding: 2px 0">Товары - <?=$arResult["OPTIONS"]["MS_LAST_UPDATE_PRODUCT"]["timestamp"]?></p>
		<p style="font-size: 11px; margin:0; padding: 2px 0">Штрихкоды (<?=$arResult["OPTIONS"]["SYNC_BARCODES"]["value"]?>) - <?=$arResult["OPTIONS"]["SYNC_BARCODES"]["timestamp"]?></p>
		<a href="/admin/profit_table.php?table=ms_profit_ru_6"><p style="font-size: 11px; margin:0; padding: 2px 0">RU 6 мес (<?=$arResult["OPTIONS"]["MOYSKLAD_PROFIT_RU_6"]["value"]?>) - <?=$arResult["OPTIONS"]["MOYSKLAD_PROFIT_RU_6"]["timestamp"]?></p></a>
		<a href="/admin/profit_table.php?table=ms_profit_by_6"><p style="font-size: 11px; margin:0; padding: 2px 0">BY 6 мес (<?=$arResult["OPTIONS"]["MOYSKLAD_PROFIT_BY_6"]["value"]?>) - <?=$arResult["OPTIONS"]["MOYSKLAD_PROFIT_BY_6"]["timestamp"]?></p></a>
		<a href="/admin/profit_table.php?table=ms_profit_ru_12"><p style="font-size: 11px; margin:0; padding: 2px 0">RU 12 мес (<?=$arResult["OPTIONS"]["MOYSKLAD_PROFIT_RU_12"]["value"]?>) - <?=$arResult["OPTIONS"]["MOYSKLAD_PROFIT_RU_12"]["timestamp"]?></p></a>
		<a href="/admin/profit_table.php?table=ms_profit_by_12"><p style="font-size: 11px; margin:0; padding: 2px 0">BY 12 мес (<?=$arResult["OPTIONS"]["MOYSKLAD_PROFIT_BY_12"]["value"]?>) - <?=$arResult["OPTIONS"]["MOYSKLAD_PROFIT_BY_12"]["timestamp"]?></p></a>

	</td>
</tr>

<tr class="<?if($arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["value"] != "Y"):?>warning<?endif?>">
	<td>Обновление цен во временной таблице</td>
	<td>
	<?if($arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["value"] != "Y"):?>
		<?//=$arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["value"]?>
		<div class="progress" style="margin:6px 0 0 0;">
			<div class="progress-bar progress-bar-striped active" role="progressbar"
				aria-valuenow="<?=$arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["value"]?>%">
				<?=$arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["value"]?>%
			</div>
		</div>
	<?else:?>
		<a href="/admin/ajax/settings/get_all_catalog.php" style="cursor:pointer; color: #337ab7;"><?=$arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["count"]?></a>
	<?endif?>
	</td>
	<td>
	<?=$arResult["OPTIONS"]["CATALOG_TEMP_TABLE"]["timestamp"]?>
	</td>
</tr>

<tr class="<?if($arResult["OPTIONS"]["UPDATE_CATALOG"]["value"] == "IN_PROCESS"):?>warning<?elseif($arResult["OPTIONS"]["UPDATE_CATALOG"]["value"] == "Y"):?>danger<?endif?>">
	<td>
	<?if($arResult["OPTIONS"]["UPDATE_CATALOG"]["value"] == "IN_PROCESS"):?>Каталог обновляется...
	<?elseif($arResult["OPTIONS"]["UPDATE_CATALOG"]["value"] == "Y"):?>Каталог ждет обновления
	<?else:?><span class="" data-toggle="tooltip" data-placement="top" title="<?=$arResult["OPTIONS"]["UPDATE_CATALOG"]["tooltip"]?>">Каталог обновлен</span><?endif?>
	</td>
	<td>
	<?if($arResult["OPTIONS"]["UPDATE_CATALOG"]["value"] == "IN_PROCESS"):?>
	<?//=$arResult["OPTIONS"]["UPDATE_CATALOG_PER"]["value"]?>
		<div class="progress" style="margin:6px 0 0 0;">
			<div class="progress-bar progress-bar-striped active" role="progressbar"
				aria-valuenow="<?=$arResult["OPTIONS"]["UPDATE_CATALOG_PER"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATE_CATALOG_PER"]["value"]?>%;color:black;">
				<?=$arResult["OPTIONS"]["UPDATE_CATALOG_PER"]["value"]?>%
			</div>
		</div>
	<?endif?>
	</td>
	<td><?=$arResult["OPTIONS"]["UPDATE_CATALOG"]["timestamp"]?></td>
</tr>

<tr class="">
	<td>
	<?if($arResult["OPTIONS"]["UPDATER_RUN"]["value"] != "N"):?>Обновление свойств товаров
	<?else:?><span class="" data-toggle="tooltip" data-placement="top" title="<?=$arResult["OPTIONS"]["UPDATER_RUN"]["tooltip"]?>">Свойства товров обновлены</span><?endif?>
	</td>
	<td>
	<?if($arResult["OPTIONS"]["UPDATER_RUN"]["value"] != "N"):?>
	<?//=$arResult["OPTIONS"]["UPDATE_CATALOG_PER"]["value"]?>
		<div class="progress" style="margin:6px 0 0 0;">
			<div class="progress-bar progress-bar-striped active" role="progressbar"
				aria-valuenow="<?=$arResult["OPTIONS"]["UPDATER_RUN_PER"]["value"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["UPDATER_RUN_PER"]["value"]?>%;color:black;">
				<?=$arResult["OPTIONS"]["UPDATER_RUN_PER"]["value"]?>%
			</div>
		</div>
		<?=$arResult["OPTIONS"]["UPDATER_RUN"]["value"]?>
	<?endif?>
	</td>
	<td><?=$arResult["OPTIONS"]["UPDATE_CATALOG"]["timestamp"]?></td>
</tr>

<tr>
	<td>
	Изменения обновлены
	</td>
	<td></td>
	<td><?=$arResult["OPTIONS"]["UPDATE_CATALOG_DIFF"]["timestamp"]?></td>
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
	<td><span class="s-tooltip" data-toggle="tooltip" data-placement="top" title="<?=strip_tags($arResult["OPTIONS"]["PARSE_CATALOG_ONLINER"]["tooltip"])?>">Парсер цен с онлайнера</span></td>
	<td><?=$arResult["OPTIONS"]["PARSE_CATALOG_ONLINER"]["count"]?></td>
	<td><?=$arResult["OPTIONS"]["PARSE_CATALOG_ONLINER"]["timestamp"]?></td>
</tr>

<?if($arResult["OPTIONS"]["FREE_SPACE"] > 0):?>
<tr class="<?if($arResult["OPTIONS"]["FREE_SPACE"]["FREE"] < 100):?>warning<?elseif($arResult["OPTIONS"]["FREE_SPACE"]["FREE"] < 20):?>danger<?endif?>">
	<td colspan="3">Место на серверsе</td>
</tr>
<?foreach($arResult["OPTIONS"]["FREE_SPACE"] as $k => $arItem):?>
<tr class="<?if($arItem["PER"] < 10):?>warning<?elseif($arItem["PER"] < 5):?>danger<?endif?>">
	<td colspan="3">
		<div class="progress" style="margin:6px 0 0 0;">
			<div class="progress-bar progress-bar-striped active" role="progressbar"
				aria-valuenow="<?=$arItem["PER"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arItem["PER"]?>%;color:black;">
				<?=$arItem["PER"]?>%
			</div>

		</div>
		<p style="font-size: 10px;"><?=$arItem["MOUNTED"]?>. свободно <?=$arItem["FREE"]?> из <?=$arItem["FULL_SIZE"]?></p>
	</td>
</tr>
<?endforeach?>
<?endif?>
<?/*<tr class="<?if($arResult["OPTIONS"]["FREE_SPACE"]["FREE"] < 100):?>warning<?elseif($arResult["OPTIONS"]["FREE_SPACE"]["FREE"] < 20):?>danger<?endif?>">
	<td>Место на сервере</td>
	<td>
		<div class="progress" style="margin:6px 0 0 0;">
			<div class="progress-bar progress-bar-striped active" role="progressbar"
				aria-valuenow="<?=$arResult["OPTIONS"]["FREE_SPACE"]["PER"]?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$arResult["OPTIONS"]["FREE_SPACE"]["PER"]?>%;color:black;">
				<?=$arResult["OPTIONS"]["FREE_SPACE"]["PER"]?>%
			</div>

		</div>
		<p style="font-size: 10px;">свободно <?=$arResult["OPTIONS"]["FREE_SPACE"]["FREE"]?>Gb из <?=$arResult["OPTIONS"]["FREE_SPACE"]["FULL_SIZE"]?>Gb</p>
	</td>
	<td></td>
</tr>*/?>
<?

?>
<script>
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
