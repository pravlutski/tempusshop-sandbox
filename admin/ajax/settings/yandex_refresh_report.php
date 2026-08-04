<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager')|| $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || 
	!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) return;

$api = new MParserAPI;
$arCompany = $api->getCompanyList();
$arResult["MPARSER_COMPANY_LIST"] = $arCompany["response"]["campaigns"];
//prent($arResult["MPARSER_COMPANY_LIST"]);
$arActiveReports = array();
foreach($arCompany["response"]["campaigns"] as $key => $company){
	$tmp = $api->getReportListCompany($company["id"]);
	//prent($tmp);
	if(isset($tmp["response"]["reports"][0])){
		$arActiveReports[$company["id"]] = $tmp["response"]["reports"][0];
		$arActiveReports[$company["id"]]["name"] = $company["name"];
	}
/*
	foreach($tmp["response"]["reports"] as $report){
		if($report["status"] == "OK" && $report["isSuccessfullyFinished"] == true){
			$arActiveReports[$company["id"]] = $report;
			$arActiveReports[$company["id"]]["name"] = $company["name"];
		}
	}*/
}

$arResult["MPARSER_REPORT_LIST"] = $arActiveReports;
?>
<select multiple="" class="form-control multiple_select" name="yandex-report[]" style="height: 140px; width: 100%; overflow: auto;font-size: 11px;">
	<?foreach($arResult["MPARSER_REPORT_LIST"] as $key => $arItem):?>
		<option value="<?=$arItem["id"]?>" <?if($arItem["status"] != "OK"):?>disabled<?endif?>><?=$arItem["name"]?> <?if($arItem["status"] == "OK"):?>(<?=$arItem["countOkProducts"]?>) - <?=date("d.m.Y H:i", strtotime($arItem["finishedAt"]));?><?else:?>В обработке<?endif?>)</option>
	<?endforeach?>
</select>
