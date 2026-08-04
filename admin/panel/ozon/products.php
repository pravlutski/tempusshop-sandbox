<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройки товаров - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки товаров");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/products.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<style>
.ui-front {
  z-index: 1000000000!important;
}
</style>
<?


global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();
CModule::IncludeModule("iblock");
use Bitrix\Iblock\PropertyEnumerationTable;

$rsProperties = CIBlockProperty::GetList(
    array("name" => "asc"),
    array("IBLOCK_ID" => CProSet::IB_CATALOG)
);
$arProperty = array();

while ($property = $rsProperties->Fetch()) {
  $arProperty[$property["ID"]] = [
      "NAME" => $property["NAME"],
      "ID" => $property["ID"],
      "TYPE" => $property["PROPERTY_TYPE"]
    ];
}
//print_r($arProperty);
$strSql = "SELECT * FROM wdhs_ozon_attribute_category_new";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arResult['ACTIVE_CATEGORY'][] = $row['name'];
}


$strSql = "SELECT * FROM wdhs_ozon_attribute_new";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arResult['ACTIVE_ATT'][] = $row;
}

$strSql = "SELECT * FROM wdhs_ozon_attribute_bitrix_new";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  if ($row['default_value'] != ''){
    $curAtt[$row['attribute_id']][$row['property_id']] = $row['default_value'];
  } else {
    $curAtt[$row['attribute_id']] = $row['property_id'];
  }
}

$strSql = "SELECT * FROM wdhs_ozon_attribute_matches_new WHERE attribute_value_id != ''";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $actMatches[$row['attribute_id']][$row['property_id']][$row['property_value_id']] = ['attribute_value_id'=>$row['attribute_value_id'],'attribute_name'=> $row['attribute_name']];
}

$CurDB = new DBPanel;
$result = $CurDB->query("SELECT * FROM ozon_top_models");
$rows = $CurDB->fetchAll($result);
$tops = [];
foreach ($rows as $row) {
  $tops[] = $row['model'];
}
$tops = implode("\n", $tops);
unset($result);
unset($rows);
?>

<?
?>
<div id="topModelsModal_TI" class="modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ТОП ОЗОНА</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <textarea id="topModelsText_<?=$cabinet?>" class="form-control" rows="20" placeholder="Введите данные..."><?=$tops?></textarea>
        <input type="hidden" id="topModelsID_<?=$cabinet?>" value="<?=$cabinet?>"/>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отменить</button>
        <button type="button" style="display:none;" id="saveTopModels_<?=$cabinet?>" class="btn btn-primary">Сохранить</button>
      </div>
    </div>
  </div>
</div>

<div class="tabs-block">
  <button class="tab-btn is-selected" value="main-settings-container">Основные настройки</button>
  <button class="tab-btn" value="collection-settings-container">Склейки</button>
</div>

<div class="main-settings-container">
  <form id="product_settings" action="/admin/modules/ozon2/ajax/save_products.php" method="post">
    <div class="card">
      <div class="card-body">
        <div class="p-head" style="display:flex; gap: 10px">
          <p class="card-text" style="margin-right:auto"><b>Выбранные категории: </b>
            <?if (!empty($arResult['ACTIVE_CATEGORY'])):
              print_r(implode(", ", $arResult['ACTIVE_CATEGORY']));
            else:?>
            Не выбрано
            <?endif;?>
          </p>
          <div style="">
            <a id="topModels_TI" class="btn btn-primary"/>Топ Озона</a>
          </div>
          <div style="display:none">
            <a class="btn btn-primary" href="https://tempusshop.ru/admin/panel/engine/ozon/reportTopSales.php?cabinet=TI">Отчет по ТОП моделям</a>
          </div>
          <button id="save_products" type="submit" style="width: 290px;" type="button" class="btn btn-warning">Сохранить настройки</button>
        </div>
        <div>
          <?
          use Bitrix\Iblock\Component\ElementList;

          $filterDataValues['iblockId'] = 16;
          $arComponentParameters['PARAMETERS']['CUSTOM_FILTER'] = array(
            'PARENT' => 'DATA_SOURCE',
            'NAME' => GetMessage('CP_BCS_TPL_CUSTOM_FILTER'),
            'TYPE' => 'CUSTOM',
            'JS_FILE' => "/bitrix/tools/maxyss.ozon/filter_conditions/script.js",
            'JS_EVENT' => 'initFilterConditionsControl',
            'JS_MESSAGES' => json_encode(array(
              'invalid' => GetMessage('CP_BCS_TPL_SETTINGS_INVALID_CONDITION')
            )),
            'JS_DATA' => json_encode($filterDataValues),
            'DEFAULT' => ''
          );
          ?>
        </div>
      </div>
      <ul class="list-group" style="padding: 20px ;gap: 20px;">
        <?foreach ($arResult['ACTIVE_ATT'] as $key => $value) {?>

          <li class="list-group-item att_li">
            <div style="width: 300px;cursor:inherit!important;" class="btn btn-secondary" data-bs-toggle="tooltip" data-bs-custom-class="full_width" data-bs-customClass="full_width" data-bs-placement="left" title="" data-bs-original-title="<?=$value['description']?>" ><?=$value['name']?><?if ($value['is_required'] == 1) { echo '<sup><span style="color:red">  *</span></sup>';}?></div>
            <div style="width: 300px;">
              <select data-ids="<?=$value['attribute_id']?>" name="data[<?=$value['attribute_id']?>]" class="form-select selecter" aria-label=".form-select-lg">
                <option value="NULL" <? if ($curAtt[$value['attribute_id']] == 'NULL') echo 'selected'; ?>>Выберите св-во</option>
                <option value="default-value" <? if (is_array($curAtt[$value['attribute_id']])) echo 'selected'; ?>>Общее св-во</option>
                <?php foreach ($arProperty as $k => $v): ?>
                  <option value="<?=$v['ID']?>" <? if ($curAtt[$value['attribute_id']] == $v['ID']) echo 'selected'; ?> >[<?=$v['ID']?>] <?=$v['NAME']?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?
            if (!is_array($curAtt[$value['attribute_id']])) {
              $res = CIBlockProperty::GetByID($curAtt[$value['attribute_id']]);
              if($ar_res = $res->GetNext())
              $propertyType = $ar_res['PROPERTY_TYPE'];
            }
            ?>
            <? if (is_array($curAtt[$value['attribute_id']])) {?>
              <div style="width:300px;"><input name="default-value[<?=$value['attribute_id']?>]" value="<?=$curAtt[$value['attribute_id']]['default-value']?>"/></div>
              <?}?>
              <?if (!is_array($curAtt[$value['attribute_id']])) {?>
                <?if ($value['dictionary_id'] != 0 and $curAtt[$value['attribute_id']] != 'NULL') {?>
                  <div style="width:300px;">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pop<?=$value['attribute_id']?>">
                      Соответствие свойств
                    </button>
                  </div>
                  <?}?>
                  <?}?>
                </li>
                <?}?>
              </ul>
            </div>
          </form>

</div>
<?require('include/collection.php');?>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="coplete_toast" class="toast hide align-items-center bg-warning" data-bs-delay="4000" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <img style="width:20px;height:20px;" src="<?=SITE_TEMPLATE_PATH?>/img/logo.png" class="rounded me-2" alt="...">
      <strong class="me-auto">Tempus Ozon Module</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Закрыть"></button>
    </div>
    <div class="toast-body">
      Операция выполнена успешно
    </div>
  </div>
</div>

<?foreach ($arResult['ACTIVE_ATT'] as $key => $value) {?>
  <?
  if (!is_array($curAtt[$value['attribute_id']])) {
    $res = CIBlockProperty::GetByID($curAtt[$value['attribute_id']]);
    if($ar_res = $res->GetNext())
      $propertyType = $ar_res['PROPERTY_TYPE'];
  }
  ?>
  <?if ($value['dictionary_id'] != '0' and (!is_array($curAtt[$value['attribute_id']]))) {?>
  <div class="modal fade show" id="pop<?=$value['attribute_id']?>" tabindex="-1" aria-labelledby="exampleModalCenteredScrollableTitle" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <form class="modal-form" data-pop-id="pop<?=$value['attribute_id']?>" method="post" action="/admin/modules/ozon2/ajax/save_directory_values.php">
          <input hidden name="attribute_id" value="<?=$value['attribute_id']?>" />
          <input hidden name="property_id" value="<?=$curAtt[$value['attribute_id']]?>" />
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalCenteredScrollableTitle">Соответствие свойств с атрибутами OZON для типа "список"</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
        </div>
        <div class="modal-body">
          <?
          //список


          ?>
          <?if ($arProperty[$curAtt[$value['attribute_id']]]['TYPE'] == 'E' or $arProperty[$curAtt[$value['attribute_id']]]['TYPE'] == 'S') {?>
            <?
              $res = CIBlockProperty::GetByID($curAtt[$value['attribute_id']]);
              if($ar_res = $res->GetNext())
                $iblock_id = $ar_res['LINK_IBLOCK_ID'];

              $rsResult = CIBlockElement::GetList(
                array(),
                array(
                    'IBLOCK_ID' => 16,
                    'ACTIVE' => 'Y',
                    '!PROPERTY_'.$curAtt[$value['attribute_id']] => false,
                ),
                array('PROPERTY_'.$curAtt[$value['attribute_id']])
                );

                while($arResult=$rsResult->Fetch()){
                  //print_r($arResult);
                  $valueId = $arResult['PROPERTY_'.$curAtt[$value['attribute_id']].'_VALUE'];
                  if ($arProperty[$curAtt[$value['attribute_id']]]['TYPE'] != 'S') {
                    $res = CIBlockElement::GetByID($valueId);
                    if($ar_res = $res->GetNext())
                         $name_v = $ar_res['NAME'];
                   } else {
                     $name_v = $valueId;
                   }
                  $arTmpRes[] = ['ID'=>$valueId, 'NAME'=> $name_v];
                  ?>
                  <div class="complete_att" style="justify-content: space-between;  display: flex;  border-bottom: 1px solid #dee2e6;  border-top-left-radius:calc(.3rem - 1px);  border-top-right-radius: calc(.3rem - 1px);  padding-left: 20px;  padding-right: 20px;  padding-bottom: 10px;  margin-bottom: 10px; align-items: center;">
                    <span style="font-size: 20px;font-weight: 400;" data-id="<?=$valueId?>"><?=$name_v?></span>
                    <input
                     <?if (isset($actMatches[$value['attribute_id']][$curAtt[$value['attribute_id']]][$valueId])) {?>name="data[<?=$valueId?>][<?=$actMatches[$value['attribute_id']][$curAtt[$value['attribute_id']]][$valueId]['attribute_value_id']?>]"<?
                     }else {?>name="data[<?=$valueId?>][]"<?}?>
                     <?if (isset($actMatches[$value['attribute_id']][$curAtt[$value['attribute_id']]][$valueId])) {?>value="<?=$actMatches[$value['attribute_id']][$curAtt[$value['attribute_id']]][$valueId]['attribute_name']?>"<?}?>
                     data-bt-id="<?=$valueId?>"
                     data-att-id="<?=$value['attribute_id']?>"
                     class="att_set_value" style="max-height:30px;"/>
                  </div>
                  <?
                }
            } else {
              $enumList = array(); // массив для хранения значений

              $rsEnum = CIBlockPropertyEnum::GetList(
                  array("SORT" => "ASC"),
                  array("PROPERTY_ID" => $curAtt[$value['attribute_id']])
              );
              while ($arEnum = $rsEnum->GetNext()) {
                  $enumList[$arEnum["ID"]] = $arEnum["VALUE"];
              }
              ?>
              <?php foreach ($enumList as $keyProp => $prop_value):
              $name_v = $prop_value;
              $valueId = $keyProp;?>

              <div class="complete_att" style="justify-content: space-between;  display: flex;  border-bottom: 1px solid #dee2e6;  border-top-left-radius:calc(.3rem - 1px);  border-top-right-radius: calc(.3rem - 1px);  padding-left: 20px;  padding-right: 20px;  padding-bottom: 10px;  margin-bottom: 10px; align-items: center;">
                <span style="font-size: 20px;font-weight: 400;" data-id="<?=$valueId?>"><?=$name_v?></span>
                <input
                 <?if (isset($actMatches[$value['attribute_id']][$curAtt[$value['attribute_id']]][$valueId])) {?>name="data[<?=$valueId?>][<?=$actMatches[$value['attribute_id']][$curAtt[$value['attribute_id']]][$valueId]['attribute_value_id']?>]"<?
                 }else {?>name="data[<?=$valueId?>][]"<?}?>
                 <?if (isset($actMatches[$value['attribute_id']][$curAtt[$value['attribute_id']]][$valueId])) {?>value="<?=$actMatches[$value['attribute_id']][$curAtt[$value['attribute_id']]][$valueId]['attribute_name']?>"<?}?>
                 data-bt-id="<?=$valueId?>"
                 data-att-id="<?=$value['attribute_id']?>"
                 class="att_set_value" style="max-height:30px;"/>
              </div>
              <?php endforeach; ?>
            <?}
            ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
          <button type="button submit" class="btn btn-primary">Сохранить изменения</button>
        </div>
      </form>
      </div>
    </div>
  </div>
  <?require('include/mobile.php');?>
  <script>
  $(document).ready(function() {
  var options = null;
    var pop<?=$value['attribute_id']?> = new bootstrap.Modal(document.getElementById('pop<?=$value['attribute_id']?>'), options)
  });
  </script>
  <?}?>
  <?}?>
<audio id="successSound">
  <source src="<?=SITE_TEMPLATE_PATH?>/source/success.mp3" type="audio/mpeg">
</audio>
<style>
.full_width{
  max-width: 100%!important;
}
.modal-dialog-scrollable .modal-body {
  overflow-y: auto!important;
}
.modal-dialog-scrollable .modal-content {
  overflow: auto!important;
}
.tab-btn{
  width: 240px;
  padding: 10px;
  background-color: #6c757d;
  color: white;
  border:none;
}
.tab-btn:hover{
  font-weight: bolder;
}
.is-selected{
  background-color: #ffca2c !important;
  font-weight: bolder;
  color: black;
}
@media (max-width: 867px){
  .p-head{
    display: flex;
    flex-direction: column;
  }
  .p-head button{
    width: 100% !important;
  }
  .list-group{
    padding: 10px !important;
  }
  .att_li{
    display: flex;
    flex-direction: column;
    border-bottom: 1px solid rgba(0,0,0,0.25) !important;
    gap: 10px;
    margin-bottom: 10px;
    padding-bottom: 10px !important;
  }
  .att_li div{
    width: 100% !important;
  }
}
</style>
<script>
$('#topModels_TI').click(function(e) {
  e.preventDefault();
  $('#topModelsModal_TI').modal('show');
});
$(document).on('click', '.tab-btn', function(e){
  $('.tab-btn').removeClass('is-selected');
  $('.main-settings-container').hide();
  $('.collection-settings-container').hide();
  $('.' + $(this).val() ).show();
  $(this).addClass('is-selected');
});
$(document).ready(function() {
  $('.att_set_value').each(function() {
    var attId = $(this).attr("data-att-id");
    var btId = $(this).attr("data-bt-id");
    $(this).autocomplete({
      source: function(request, response) {
        $.ajax({
          method: 'GET',
          url: '/admin/modules/ozon2/ajax/get_directory_values.php',
          dataType: 'json',
          data: { input: request.term, id: attId },
          success: function(data) {
            response($.map(data, function(item) {
              return {
                label: item.value,
                value: item.value,
                attValueId: item.value_id
              };
            }));
          },
          error: function(xhr, status, error) {
            console.error(error);
            response([]);
          }
        });
      },
      minLength: 2,
      select: function(event, ui) {
        $(this).attr('name', 'data[' + btId + '][' + ui.item.attValueId + ']');
      }
    });
  });


});
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
