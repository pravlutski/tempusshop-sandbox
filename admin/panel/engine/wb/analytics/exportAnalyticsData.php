<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('panel.manager');

function arrayToCsv($data, $filename = "export.csv") {
    // Открываем файл для записи
    $file = fopen($filename, 'w');

    // Добавляем BOM для корректного отображения кириллицы в Excel
    fputs($file, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    // Записываем заголовки (если массив ассоциативный)
    if (!empty($data)) {
        $firstRow = $data[0];
        if (is_array($firstRow)) {
            fputcsv($file, array_keys($firstRow));
        }

        // Записываем данные
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
    }

    fclose($file);
}

global $DB;
$panel = new DBPanel;

$strSql = "SELECT
    ota.id as id,
    ota.model as model,
    ota.our_price as our,
    ota.sell_price as sell,
    ota.black_price as black,
    ota.spp as spp,
    ota.date as date,
    IFNULL(otm.sellSum, 0) as sortSum
FROM wb_top_analytics AS ota
LEFT JOIN wb_top_models AS otm
    ON ota.model = otm.model";

$result = $panel->query( $strSql );
$rows = $panel->fetchAll( $result );

$strSql = "SELECT * FROM wb_fbo_stat_WR";
$result = $panel->query( $strSql );
$rows1 = $panel->fetchAll($result);
$stockFbo = [];
foreach( $rows1 as $row ){
  $stockFbo[ $row['stock_date'] ][ $row['model'] ] = $row['stock'];
}

$data = [];
foreach ( $rows as $row ){
  $item = $row;
  $item['fbo_stock'] = $stockFbo[ $row['date'] ][ $row['model'] ] ?? 0;
  $data[] = $item;
}

arrayToCsv($data, '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/export/wb_top_sort_in.csv');

$rows = $panel->select(['*'], 'wb_spp_analytics_by_hour')->make();
arrayToCsv($rows, '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/export/wb_spp_analytics_by_hour.csv');
 ?>
