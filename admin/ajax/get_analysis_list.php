<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2")))
	$psFilter["website"] = $_POST["website"];
if(isset($_POST["brand"]) && $_POST["brand"] != "null")
	$psFilter["brand_id"] = explode(",", $_POST["brand"]);
if(isset($_POST["supplier"]) && $_POST["supplier"] != "null")
	$psFilter["supplier_id"] = explode(",", $_POST["supplier"]);
//prent($psFilter);
//prent($_POST);die;
?>
<?if(!in_array($psFilter["website"], array("s1", "s2"))):?>
	<tr><td colspan="7">
		<p>Выберите сайт</p>
	</tr></td>
	<?die;?>
<?endif?>
<?if(!$psFilter["brand_id"]):?>
	<tr><td colspan="7">
		<p>Выберите бренд</p>
	</tr></td>
	<?die;?>
<?endif?>
<?if(!$psFilter["supplier_id"]):?>
	<tr><td colspan="7">
		<p>Выберите поставщика</p>
	</tr></td>
	<?die;?>
<?endif?>
<?
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$page_size = 10;
	$pricelist = new CPanelPricelist;
	$supplier = new CPanelSupplier;
	$tmp = $supplier->getList();
	$arSupName = array();
	foreach($tmp as $arItem){
		$arSupName[$arItem["id"]] = $arItem["name"];
	}
/*	$psFilter = array();//фильтр для таблиц прайслистов
	if(isset($_POST["brand"])){
		$psFilter["brand_id"] = explode(",", $_POST["brand"]);
	}
	if(isset($_POST["supplier"])){
		$psFilter["supplier_id"] = explode(",", $_POST["supplier"]);
	}*/
	$type_price = ($psFilter["website"] == "s1" ? 1 : 2);
	$price = $pricelist->getPriceByFilter($psFilter);
//	prent($psFilter);
	$arArticle = array();//массив со всеми артикулами
	$tmpPrice = $arPrice = array();//
	foreach($price as $key => $arItem){
		if($arItem["model"]){
			$arArticle[$arItem["model"]] = $arItem["model"];
			if(isset($tmpPrice[$arItem["model"]])){
				if($arItem["price"] < $tmpPrice[$arItem["model"]]["price"])
					$tmpPrice[$arItem["model"]] = $arItem;
			}else
				$tmpPrice[$arItem["model"]] = $arItem;
		}
	}
	if(!$arArticle) return;
	$arFilter = array(
		"IBLOCK_ID" => CProSet::IB_CATALOG,
		"PROPERTY_CML2_ARTICLE" => $arArticle
	);
	//prent($tmpPrice);
//	$res = CIBlockElement::GetList(array(), $arFilter, array('IBLOCK_ID'), array("nPageSize"=>10));
//	$res = CIBlockElement::GetList(array(), $arFilter, false, array("nPageSize" => $page_size), array("ID", "PROPERTY_CML2_ARTICLE", "CATALOG_GROUP_{$type_price}"));
	$res = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "PROPERTY_CML2_ARTICLE", "CATALOG_GROUP_{$type_price}"));

	while ($el = $res->Fetch()){
		$tmp = $tmpPrice[$el["PROPERTY_CML2_ARTICLE_VALUE"]];
		$ar = array(
			"ID" => $el["CATALOG_PRICE_ID_{$type_price}"],
			"PRICE" => $el["CATALOG_PRICE_{$type_price}"],
			"CURRENCY" => $el["CATALOG_CURRENCY_{$type_price}"],
			"CATALOG_GROUP_ID" => $el["CATALOG_GROUP_ID_{$type_price}"],
		);
//$asd = CCatalogProduct::GetOptimalPrice($el["ID"], 1, array(), "N", $ar);//, "s1"
$asd = AHCatalog::OnGetOptimalPrice($el["ID"], 1, array(), "N", array(), "s1");
prent($asd);
		$revenue_p = (($el["CATALOG_PRICE_{$type_price}"] - $tmp["price"]) / $tmp["price"]) * 100;
		$revenue_p = round($revenue_p, 2);
		$arPrice[] = array(
			"article" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
			"b_price" => $el["CATALOG_PRICE_{$type_price}"],
			"b_price_cur" => $el["CATALOG_CURRENCY_{$type_price}"],
			"price" => $tmp["price"],
			"supplier_id" => $tmp["supplier_id"],
			"supplier_name" => $arSupName[$tmp["supplier_id"]],
			"revenue" => $el["CATALOG_PRICE_{$type_price}"] - $tmp["price"],
			"revenue_p" => $revenue_p,
		);
	}
	/******************** сортировка **********************/
	$arAvailSort = array(
		"article" => SORT_STRING,
		"b_price" => SORT_NUMERIC,
		"price" => SORT_NUMERIC,
		"revenue" => SORT_NUMERIC,
		"revenue_p" => SORT_NUMERIC,
		"supplier_name" => SORT_STRING,
	);
	$order = $sort = "";
	if($_POST["order"] != "undefined" && $_POST["sort"] != "undefined"){
		if(in_array($_POST["order"], array("asc", "desc")) && isset($arAvailSort[$_POST["sort"]])){
			$order = ($_POST["order"] == "asc" ? SORT_ASC : SORT_DESC);
			$sort = $_POST["sort"];
//$sort = "article";
			$data = array();
			foreach($arPrice as $key => $arr){
				$data[$key] = $arr[$sort];
			}
//			prent($data);
//			prent($arAvailSort[$sort]);
			//for($i = 0; $i < 100000; $i++){
			//	$data_tmp = $data;
			//$asd = array_multisort($data, $arAvailSort[$sort], $order, $arPrice);
			
			array_multisort($data, $arAvailSort[$sort], $order, $arPrice);
//			prent($arPrice);
			
//	prent($order);
//	prent($sort);
		}
	}

//	prent($all);
/* 
	foreach($price as $key => &$arItem){
		if($arItem["model"]){
			$res = CIBlockElement::getList(array(), array('ACTIVE' => 'Y', 'IBLOCK_ID' => CProSet::IB_CATALOG, "PROPERTY_CML2_ARTICLE" => $arItem["model"]),false, false,array("ID", "CATALOG_GROUP_{$type_price}"));
			if ($row = $res->getNext()) {
				$arItem["b_price"] = $row["CATALOG_PRICE_{$type_price}"];
				$arItem["b_price_cur"] = $row["CATALOG_CURRENCY_{$type_price}"];
			}else
				unset($price[$key]);
		}else
			unset($price[$key]);
	}
*/
	//[supplier_id] => 45
	foreach($arPrice as $key => $arItem):?>
		<tr>
			<td <?if($sort == "article"):?>class="active"<?endif?>><?=$arItem["article"]?></td>
			<td <?if($sort == "b_price"):?>class="active"<?endif?>><?=$arItem["b_price"]?></td>
			<td <?if($sort == "price"):?>class="active"<?endif?>><?=$arItem["price"]?></td>
			<td <?if($sort == "revenue"):?>class="active"<?endif?>><?=$arItem["revenue"]?></td>
			<td <?if($sort == "revenue_p"):?>class="active"<?endif?>><?=$arItem["revenue_p"]?></td>
			<td <?if($sort == "supplier_name"):?>class="active"<?endif?>><?=$arSupName[$arItem["supplier_id"]]?></td>
			<td><button type="button" class="btn btn-primary">РРЦ</button></td>
		</tr>
	<?endforeach?>
	
	<?
/*	if($allEl > $page_size):?>
		<tr><td colspan="7">
		<select class="form-control select_w" id="s-page">
		<?
		$page = round($allEl / $page_size);
		for($i = 1; $i < $page; $i++):?> 
		<option value="<?=$i?>"><?=$i?></option>
		<?endfor?>
		<select>
		<?//=$page?>
		</tr></td>
	<?endif*/?>
	<?
	//prent($price);

}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');