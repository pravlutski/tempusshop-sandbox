<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;

global $db;
$strSql = "SELECT * FROM wdhs_ozon_attribute_category";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arCurrent[$row['description_category_id']] = $row['name'];
}

$strSql = "SELECT * FROM wdhs_ozon_main_settings";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arSetting[$row['name']] = $row['value'];
}

$conn['api_url'] = $arSetting['api_url'];
$conn['client_id'] = $arSetting['client_id'];
$conn['token'] = $arSetting['key'];

$ch = curl_init( $conn['api_url'] . '/v1/description-category/tree');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
  'Api-Key:' . $conn['token'],
  'Client-Id:' . $conn['client_id'],
  'Content-Type:application/json'
));
$data = array("language" => "DEFAULT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
$res = curl_exec($ch);
curl_close($ch);

$res = json_decode($res, true);
//print_r($res);
if (!empty($res['result'])) {?>
  <h5>Список категорий OZON</h5>
  <form id="update_att_category" action="/admin/modules/ozon/ajax/update_att_category.php" method="post">
    <div class="scrollable-container">
      <ul class="list-group">
      <?foreach ($res['result'] as $key => $value):?>
      <li class="list-group-item">
        <label style="font-size:18px;">
          <?if (isset($value['category_name'])) { print_r($value['category_name']); }else{ print_r($value['type_name']); }?>
        </label>
        <?if (!empty($value['children'])) {?>
        <ul>
          <?foreach ($value['children'] as $k => $v) {?>
          <li style="list-style: disclosure-open;">
            <label>
              <?if (isset($v['category_name'])) { print_r($v['category_name']); }else{ print_r($v['type_name']); }?>
            </label>
            <?if (!empty($v['children'])) {?>
            <ul>
              <?foreach ($v['children'] as $k2 => $v2) {?>
              <li>
                <input name="<?print_r($v['description_category_id']);?>@<?print_r($v2['type_id']);?>"
                 value="<?if (isset($v2['category_name'])) { print_r($v2['category_name']); }else{ print_r($v2['type_name']); }?>"
                 type="checkbox"
                 <?if (isset($v2['description_category_id']) & isset($arCurrent[$v2['description_category_id']])) {
                   print_r('checked');
                 } ?>

                 <?if (isset($v2['type_id']) & isset($arCurrent[$v2['type_id']])) {
                   print_r('checked');
                 } ?>>
                <label>
                  <?if (isset($v2['category_name'])) { print_r($v2['category_name']); }else{ print_r($v2['type_name']); }?>
                </label>
              </li>
              <?}?>
            </ul>
            <?}?>
          </li>
          <?}?>
        </ul>
        <?}?>
      </li>
      <?endforeach;?>
      </ul>
    </div>
    <button id="update_category_button" type="submit" style="width: 100%;" type="button" class="btn btn-secondary">Обновить категории</button>
  </form>
<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
