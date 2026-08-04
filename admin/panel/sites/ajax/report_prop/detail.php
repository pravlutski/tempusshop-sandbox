<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule('panel.manager'))return;
$prop_code = trim($_REQUEST["property_code"]);
if(!$prop_code) die;

if(isset($_REQUEST["only_empty"]) && $_REQUEST["only_empty"] == "Y"){
	$only_empty = true;
}else{
	$only_empty = false;
}

$properties = CIBlockProperty::GetList(["sort"=>"asc", "name"=>"asc"], Array(
	"ACTIVE" => "Y", 
	"CODE" => $prop_code,
	"IBLOCK_ID" => 16
));
if ($prop_fields = $properties->GetNext())
{
	$arResult["PROPERTY"] = [
		"ID" => $prop_fields["ID"],
		"NAME" => $prop_fields["NAME"],
	];
}else{
	die('хз');
}

$dbPanel = new DBPanel();
$arResult = [];
$objPricelist = new CPanelPricelist;
/*$filter = [
	"website" => "os",
	"!bitrix_id" => "0",
];
$arModel = $objPricelist->getPriceByFilterNew($filter, "bitrix_id", ["bitrix_id", "model"], false, $priceType);
*/
$arSelect = ["ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_{$prop_code}"];
$arFilter = [
	"PROPERTY_OZON_ACTIVE" => 1943,
];
$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
$arResult["ITEMS"] = [];
while($ar = $rs->GetNext()){
	//prent($ar);
	$col_prop = "PROPERTY_{$prop_code}_VALUE";
	$arResult["ITEMS"][$ar["PROPERTY_CML2_ARTICLE_VALUE"]]["SELL_QUANTITY"] = 0;
	if(is_array($ar[$col_prop])){
		if(count($ar[$col_prop]) > 0){
			foreach($ar[$col_prop] as $v)
				$arResult["ITEMS"][$ar["PROPERTY_CML2_ARTICLE_VALUE"]]["VALUES"][] = $v;
		}else{
			$arResult["ITEMS"][$ar["PROPERTY_CML2_ARTICLE_VALUE"]]["VALUES"] = '';
		}
	}else{
		if(strlen($ar[$col_prop]) > 0){
			$arResult["ITEMS"][$ar["PROPERTY_CML2_ARTICLE_VALUE"]]["VALUES"][] = $ar[$col_prop];
		}else{
			$arResult["ITEMS"][$ar["PROPERTY_CML2_ARTICLE_VALUE"]]["VALUES"] = "";
		}
	}
	
}

// ищем продажи за 12 месяцев
$arArticle = array_keys($arResult["ITEMS"]);
if(count($arArticle) > 0){
	$result = $dbPanel->query("SELECT * FROM ms_profit_ru_12 WHERE model IN ('".implode("','", $arArticle)."')");
	$rows = $dbPanel->fetchAll($result);
	foreach ($rows as $row) {
		$arResult["ITEMS"][$row["model"]]["SELL_QUANTITY"] = $row["sellQuantity"];
	}
}
$arResult["ITEMS"] = sort_nested_arrays($arResult["ITEMS"], ['SELL_QUANTITY' => 'desc'], true);

?>
<table class="table table-striped">
	<thead>
		<tr>
			<th scope="col">Модель</th>
			<th scope="col"><?=$arResult["PROPERTY"]["NAME"]?></th>
			<th scope="col">Продаж за период</th>
		</tr>
	</thead>
	<tbody>
		<?foreach($arResult["ITEMS"] as $article => $arItem):?>
			<?if($only_empty && $arItem["VALUES"]) continue;?>
			<tr>
				<th scope="row"><?=$article?></th>
				<th scope="row"><?if(is_array($arItem["VALUES"])):?><?=implode(", ", $arItem["VALUES"])?><?endif?></th>
				<th scope="row"><?=$arItem["SELL_QUANTITY"]?></th>
			</tr>
		<?endforeach?>
	</tbody>
</table>
