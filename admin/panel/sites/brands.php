<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Кол-во товаров по брендам');?>

<link href="<?=SITE_TEMPLATE_PATH?>/css/sales.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/sales.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<style>
.ui-front {
  z-index: 1000000000!important;
}
</style>
<?
opcache_reset();


global $DB;
global $USER;
$CurDB = new DBPanel();
$arGroups = $USER->GetUserGroupArray();



$tmp = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/result.txt');
$arLog = json_decode($tmp,true);
$log = $arLog;
//print_r($log);
$APPLICATION->SetPageProperty("page_h1", "Кол-во товаров по брендам");
?>

<?
use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionTable;
CModule::IncludeModule('iblock');

$arSelect = array(
    "ID",
    "NAME"
);
$arFilter = array(
    "IBLOCK_ID" => 11,
    "ACTIVE"    => "Y"
);

$res = CIBlockElement::GetList(
    array("SORT" => "ASC"),
    $arFilter,
    false,
    false,
    $arSelect
);

while ($ob = $res->GetNextElement()) {
    $arFields = $ob->GetFields();
    $arBrnads[] = ['ID' => $arFields['ID'],'NAME' => $arFields['NAME']];
}
$arCut = array_chunk($arBrnads, 4);
  ?>
<?php foreach ($arCut as  $arBchk) {?>
<?//print_r($arBchk);?>
<div class="row" style="display: flex;
  gap:1%;">
<?php foreach ($arBchk as $key => $value): ?>
  <?//print_r($value);?>
  <?if ($value['ID'] == 206671) { continue;}?>
  <?
  if ($log[$value['ID']]['RU']) {
    $cRu = count($log[$value['ID']]['RU']);
  } else {
    $cRu = 0;
  }
  if ($log[$value['ID']]['BY']) {
    $cBy = count($log[$value['ID']]['BY']);
  } else {
    $cBy = 0;
  }
  $name = $value['NAME'];
  $bg = "background-color: rgba(65, 192, 213, 0.42);";
  ?>
  <div class="col-sm-2" style="<?=$bg?> margin-top: 15px; padding: 10px;margin-left: 0px;width: 24%;">
      <div class="header_table">
          <div class="title_header_table" style="display: flex;
  justify-content: center;
  align-items: center;gap: 30px;">
            <span><?=$name?></span> <div style="font-size:12px"><p style="margin:0;<?if ($cRu == 0) { echo 'color:red';}?>">RU - <?=$cRu?></p> <p style="margin:0;<?if ($cBy == 0) { echo 'color:red';}?>">BY - <?=$cBy?></p></div>

          </div>
          <div class="arrow-4"  style="display:none;">
              <span class="arrow-4-left"></span>
              <span class="arrow-4-right"></span>
          </div>
      </div>
    <div class="tabled">
      <?if (isset($log['key'])) {?>
        <h3>RU</h3>
      <div class="item" style="padding: 10px 10px 20px 10px;
  border-bottom: 1px solid black;
  margin: 10px;">
        <div class="row">
          <span style="font-size: 18px;
  font-weight: bold;">  <i style="font-size: 14px;
  font-weight: normal;;">(OZON ID: )</i></span>
        </div>
        <div class="row">
          <span></span>
        </div>
      </div>
      <?}?>
    </div>
  </div>

<?php endforeach; ?>
</div>

<?php } ?>
<script>
$(document).on('click', '.arrow-4', function() {
    $(this).toggleClass("open");
	var table = $(this).closest('.col-sm-12').find('.tabled');
    table.toggle();
});
</script>

<style>

.arrow-4 {
    position: relative;
    cursor: pointer;
    margin:20px;
    width: 66px;
    height: 30px;
}
.count-p {
  font-size: 12px;
  margin: 0px;
}
.arrow-4-left {
    position: absolute;
    background-color: transparent;
    top: 10px;
    left: 0;
    width: 20px;
    height: 5px;
    display: block;
    transform: rotate(35deg);
    float: right;
    border-radius: 2px;
}
.arrow-4-left:after {
    content: "";
    background-color: #337AB7;
    width: 20px;
    height: 5px;
    display: block;
    float: right;
    border-radius: 6px 10px 10px 6px;
    transition: all 0.5s cubic-bezier(0.25, 1.7, 0.35, 0.8);
    z-index: -1;
}

.arrow-4-right {
    position: absolute;
    background-color: transparent;
    top: 10px;
    left: 14px;
    width: 20px;
    height: 5px;
    display: block;
    transform: rotate(-35deg);
    float: right;
    border-radius: 2px;
}
.arrow-4-right:after {
    content: "";
    background-color: #337AB7;
    width: 20px;
    height: 5px;
    display: block;
    float: right;
    border-radius: 10px 6px 6px 10px;
    transition: all 0.5s cubic-bezier(0.25, 1.7, 0.35, 0.8);
    z-index: -1;
}
.open .arrow-4-left:after {
    transform-origin: center center;
    transform: rotate(-70deg);
}
.open .arrow-4-right:after {
    transform-origin: center center;
    transform: rotate(70deg);
}

.tabled {
    display:none;
}
.header_table {
    display: flex;
    flex-direction: row;
    align-items: center;
    padding-left: 20px;;
    font-size: 18px;;
}
.header_table span {
    font-weight: bold;
}
</style>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
