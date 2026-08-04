<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;
if(!Loader::includeModule('maxyss.wb'))return;

include_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/components/adm/order.assembly/wbtools.php");
if(empty($_SESSION["CABINET"])) {
	$_SESSION["CABINET"] = "WR";
}
$cabinet_init = $_SESSION["CABINET"];
$wb = new WBTools($cabinet_init);

$arResult["ASSEMBLY_LIST"] = $wb->getSuppliesActive();
if(is_array($arResult["ASSEMBLY_LIST"]) && count($arResult["ASSEMBLY_LIST"]) > 0):?>
<script>
	$("#move-to-supply").show();
</script>
	<table class="table">
		<thead>
			<tr>
				<th></th>
				<th>Имя</th>
				<th>ID</th>
				<th>Создан</th>
			</tr>
		</thead>
		<tbody>
			<?foreach($arResult["ASSEMBLY_LIST"] as $key => $arItem):?>
			<tr>
				<td><input type="radio" name="supply_id" class="select_supply" <?if($key == 0):?>checked<?endif?> value="<?=$arItem["id"]?>"></td>
				<td><?=$arItem["name"]?></td>
				<td><?=$arItem["id"]?></td>
				<td><?=$arItem["createdAt"]?></td>
			</tr>
			<?endforeach?>
		</tbody>
	</table>
<?else:?>
<script>
	$("#move-to-supply").hide();
</script>
<?endif?>
<?require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
