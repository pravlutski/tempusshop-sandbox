<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Лог акций');?>

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
$arGroups = $USER->GetUserGroupArray();

$strSql = "SELECT * FROM wdhs_ozon_sales";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $salesActive[$row['sale_id']] = $row;
}

$action_id = $_GET['ID'];
$tmp = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/sales/log.txt');
$arLog = json_decode($tmp,true);
if (empty($action_id)) {
$APPLICATION->SetPageProperty("page_h1", "Логи акции");
} else {
$APPLICATION->SetPageProperty("page_h1", "Лог акции - ".$salesActive[$action_id]['name']."" );
}
if (empty($action_id)) {  ?>
  <?php foreach ($arLog as $key => $value): ?>

  <?if ($key != 'GET_ITEMS') {?>
  <div class="col-sm-12" style="background-color: rgba(77, 196, 60, 0.42);border-radius: 20px;margin-top: 15px;padding-top: 20px;
  padding-bottom: 20px;">
      <div class="header_table">
          <div class="title_header_table">

            <span><a style="color:#111;text-decoration:none;" href="?ID=<?=$key?>"><?=$salesActive[$key]['name']?></a></span>
          </div>
      </div>
  </div>
  <?}?>
  <?php endforeach; ?>


<?
} else {
  $log = $arLog[$action_id];
  unset($arLog);

  ?>

<?php foreach ($log as $key => $value): ?>
  <?
  switch ($key) {
    case 'STAY':
        $name = "Остальсь в акции без изменний";
        $bg = "background-color: rgba(212, 242, 64, 0.44);";
        break;
    case 'REPLACE':
        $name = "Пернесенны из другой акции";
        $bg = "background-color: rgba(40, 96, 144, 0.33);";
        break;
    case 'NOT_ADD':
        $name = "Не добавлены";
        $bg = "background-color: rgba(244, 39, 78, 0.29);";
        break;
    case 'DELETE':
        $name = "Удалены из акции";
        $bg = "background-color: rgba(244, 39, 78, 0.29);";
        break;
    case 'ADD':
        $name = "Добавлены";
        $bg = "background-color: rgba(77, 196, 60, 0.42);";
        break;
}
  ?>
  <div class="col-sm-12" style="<?=$bg?>border-radius: 20px;margin-top: 15px;">
      <div class="header_table">
          <div class="title_header_table">
            <span><?=$name?> <i style="font-size: 18px;
  font-weight: normal;">(<?=count($value)?>)</i></span>
          </div>
          <div class="arrow-4">
              <span class="arrow-4-left"></span>
              <span class="arrow-4-right"></span>
          </div>
      </div>
    <div class="tabled">
      <?php foreach ($value as $k => $v): ?>
      <div class="item" style="padding: 10px 10px 20px 10px;
  border-bottom: 1px solid black;
  margin: 10px;">
        <div class="row">
          <span style="font-size: 18px;
  font-weight: bold;"><?=$v['model']?>  <i style="font-size: 14px;
  font-weight: normal;;">(OZON ID: <?=$v['ozon_id']?>)</i></span>
        </div>
        <div class="row">
          <span><?=$v['reason']?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

<?php endforeach; ?>
<?}?>

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
    font-size:24px;
}
</style>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
