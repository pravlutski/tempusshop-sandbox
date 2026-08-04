<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/ZennolabParser.php');
CModule::IncludeModule('panel.manager');

$dbPanel = new DBPanel;
$zenno = new ZennolabParser(false);

$date = empty($_POST['control-date']) ? date('Y-m-d') : $_POST['control-date'];

$rows = $dbPanel->select(['*'], 'ozon_parser_analytics_data')->where('date', $date)->make();
if ( count($rows) > 0 ){

  $message = "
  <div class='status-block'>
    <h5>Данные парсера Zennolab</h5>
    <div class='si-block'>
      <div class='s-row'>
        Статус: <b style='color: green'>Импортировано</b>
      </div>
      <div class='s-row'>
        Дата: <b>{$date}</b>
      </div>
    </div>
  </div>";
  echo $message;
  // die;
}else{
  $message = "
  <div class='status-block'>
    <h4>Данные парсера Zennolab</h4>
    <div class='si-block'>
      <div class='s-row'>
        Статус: <b style='color:red'>Данные не найдены</b>
      </div>
      <div class='s-row'>
        Дата: <b>{$date}</b>
      </div>
    </div>
  </div>";
  echo $message;
}

$files = $zenno->getFileList( $date );
?>

<div class="status-block">
  <form class="" action="index.html" method="post">
    <select class="form-control" id="file-selector">
      <option value="0">-- Выберите файл --</option>
      <? foreach( $files as $file ): ?>
        <option value="<?=$file?>"><?=basename($file)?></option>
      <? endforeach; ?>
    </select>
  </form>
</div>
