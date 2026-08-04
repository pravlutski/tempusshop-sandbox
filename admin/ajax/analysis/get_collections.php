<?php
// Получение коллекций

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule("panel.manager");

$collection = new CPanelBrand;

$brandsId = $collection->searchBrands($_REQUEST["brand_id"]);
$collectionList = $collection->getCollections($brandsId);

?>
<option value="0">На все коллекции</option>
<?php foreach ($collectionList as $item):?>
	<?php if($item["id"] == $_REQUEST["collection_id"]):?>
		<option value="<?=$item["id"]?>" selected><?=$item["name"]?></option>
	<?php else:?>
		<option value="<?=$item["id"]?>"><?=$item["name"]?></option>
	<?php endif;?>
<?php endforeach;
