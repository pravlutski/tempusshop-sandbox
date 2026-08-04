<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$curdate = date('Y-m-d');?>
<?$APPLICATION->SetTitle('Главная - WB модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Статистика по выгрузке за сегодня ($curdate)");?>
<?
opcache_reset();
global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

CModule::IncludeModule("iblock");

$strSql = "SELECT * FROM wdhs_wb_upload_status";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arLast[$row['cabinet']][$row['agent']] = $row;
}

?>
<div class="row">
  <div class="col-sm-6">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">WR</h5>
      </div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item resize" style="justify-content: space-between;"><span><b style="margin-right:10px;">Цены: </b>Выгрузка завершена в <?=$arLast['WR']['price']['time']?></span><a href="https://tempusshop.ru/admin/modules/wb/logs/price.php?cabinet=WR" class="card-link">Лог выгрузки цен</a></li>
        <li class="list-group-item resize" style="justify-content: space-between;"><span><b style="margin-right:10px;">Остатки: </b>Выгрузка завершена в <?=$arLast['WR']['stock']['time']?></span><a href="https://tempusshop.ru/admin/modules/wb/logs/stock.php?cabinet=WR" class="card-link">Лог выгрузки остатков</a></li>
        <li class="list-group-item resize"><b style="margin-right:10px;">Товары: </b><span style="color:red">Статус не установлен.</span></li>
      </ul>
    </div>
  </div>
  <div class="col-sm-6">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">TL</h5>
      </div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item resize" style="justify-content: space-between;"><span><b style="margin-right:10px;">Цены: </b>Выгрузка завершена в <?=$arLast['TL']['price']['time']?></span><a href="https://tempusshop.ru/admin/modules/wb/logs/price.php?cabinet=TL" class="card-link">Лог выгрузки цен</a></li>
        <li class="list-group-item resize" style="justify-content: space-between;"><span><b style="margin-right:10px;">Остатки: </b>Выгрузка завершена в <?=$arLast['TL']['stock']['time']?></span><a href="https://tempusshop.ru/admin/modules/wb/logs/stock.php?cabinet=TL" class="card-link">Лог выгрузки остатков</a></li>
        <li class="list-group-item resize"><b style="margin-right:10px;">Товары: </b><span style="color:red">Статус не установлен.</span></li>
      </ul>
    </div>
  </div>
</div>

<hr>
<style>
.custom-bar {
  margin: 5px!important;
  height: 100%!important;
  width: 100%!important;
}
.resize {
  display: flex!important;
  align-items: center!important;
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
