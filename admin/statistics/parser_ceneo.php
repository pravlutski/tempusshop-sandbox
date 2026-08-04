<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Цены выше чем на онлайнере");
?>
<h1 class="page-header">Парсер цен ceneo.pl</h1>
<div class="page_header_buttons clearfix">
	<a href="/admin/statistics/" class="btn btn-default">Назад</a>
</div>
<hr>
<?
global $DB;
$strSql = "SELECT 
	ci.name as article,
	ci.ceneo_id as ceneo_id,
	ci.minPrice as price_ceneo,
	ci.timestamp as timestamp,
	b.price_discount_pl as price_bitrix 
FROM 
	ci_ceneo_price ci, ci_price_catalog b 
WHERE 
	ci.bitrix_id=b.product_id";//b.product_id
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	//prent($row);die;
	$arResult["ITEMS"][] = $row;
}
//prent($arResult["ITEMS"]);die;
?>
<table class="table">
	<thead>
	<tr>
		<th style="">Модель</th>
		<th style="">URL</th>
		<th style="">Цена ceneo</th>
		<th style="">Цена сайта</th>
		<th style="">Дата</th>
	</tr>
	</thead>
	<tbody>
	<?foreach($arResult["ITEMS"] as $key => $arItem):?>
	<tr>
		<td><?=$arItem["article"]?></td>
		<td><a href="https://www.ceneo.pl/<?=$arItem["ceneo_id"]?>" target="_blank">перейти</a></td>
		<td><?=$arItem["price_ceneo"]?></td>
		<td><?=$arItem["price_bitrix"]?></td>
		<td><?=$arItem["timestamp"]?></td>
	</tr>
	<?endforeach?>
	</tbody>
</table>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>