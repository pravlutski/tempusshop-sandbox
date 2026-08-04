<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$db = \Bitrix\Main\Application::getConnection();

$rows = $db->Query( "SELECT * FROM wdhs_wb_product_brand_aliases" );
$savedAliases = [];
while ( $row = $rows->Fetch() ){
  $savedAliases[ $row['id'] ] = [
    'brand_id' => $row['brand_id'],
    'brand_name' => $row['brand_name'],
  ];
}

$rows = CIBlockElement::getList(
  ["NAME" => "ASC"],
  [ "IBLOCK_ID" => 11 ],
  false,
  false,
  [ "ID", "NAME" ]
);
$brandsBX = [];
while ( $row = $rows->GetNext() ){
  $brandsBX[ $row['ID'] ] = $row['NAME'];
}

foreach ( $savedAliases as $id => $aData ):
?>
<div id="<?=$id?>_card" class="alias-card">
  <div class="card-col card-name">
    <span><?=$brandsBX[$aData['brand_id']]?></span>
  </div>
  <div class="card-col">
    <input type="text" id="<?=$id?>_name" class="form-control" value="<?=$aData['brand_name']?>" disabled>
  </div>
  <div class="card-col card-btns">
    <button  value="<?=$id?>" class="btn btn-danger delete-alias">Удалить</button>
    <!-- <button id="edit-alias" value="<?=$id?>" class="btn btn-warning">Сохранить</button> -->
  </div>
</div>
<? endforeach; ?>
