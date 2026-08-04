<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Цены выше чем на онлайнере");
?>
<h1 class="page-header">Цены выше чем на онлайнере</h1>
<div class="page_header_buttons clearfix">
	<a href="/admin/statistics/" class="btn btn-default">Назад</a>
</div>
<hr>
<?
global $DB;
$strSql = "SELECT * FROM ci_catalog_onliner WHERE shop_price > min_price ORDER BY brand";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	//prent($row);die;
	$arResult["ITEMS"][] = $row;
}
?>
<table class="table">
	<thead>
	<tr>
		<th style="">Бренд</th>
		<th style="">Модель</th>
		<th style="">URL</th>
		<th style="">Мин цена</th>
		<th style="">Цена сайта</th>
	</tr>
	</thead>
	<tbody>
	<?foreach($arResult["ITEMS"] as $key => $arItem):?>
	<tr>
		<td><?=$arItem["brand"]?></td>
		<td><?=$arItem["model"]?></td>
		<td><a href="<?=$arItem["url"]?>" target="_blank">перейти</a></td>
		<td><?=$arItem["min_price"]?></td>
		<td><?=$arItem["shop_price"]?></td>
	</tr>
	<?endforeach?>
	</tbody>
</table>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>