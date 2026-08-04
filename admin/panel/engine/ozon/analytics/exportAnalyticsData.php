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
    ota.median as median,
    ota.our as our,
    ota.sell as sell,
    ota.spp as spp,
    ota.is_fbo as fbo,
    ota.sale_name as sale_name,
    ota.sale_price as sale_price,
    ota.orders_count as orders_count,
    ota.date as date,
    IFNULL(otm.sellSum, 0) as sortSum
FROM ozon_top_analytics AS ota
LEFT JOIN ozon_top_models AS otm
    ON ota.model = otm.model";

$result = $panel->query( $strSql );
$rows = $panel->fetchAll( $result );

$strSql = "SELECT * FROM ozon_stock_fbo_stat";
$result = $DB->query( $strSql );
$stockFbo = [];

while( $row = $result->Fetch() ){
  $stockFbo[ $row['date'] ][ $row['model'] ] = $row['stock'];
}

$data = [];
foreach ( $rows as $row ){
  $item = $row;
  $item['fbo_stock'] = $stockFbo[ $row['date'] ][ $row['model'] ] ?? 0;
  $data[] = $item;
}

arrayToCsv($data, '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/export/ozon_top_sort_in.csv');

$rows = $panel->select(['*'], 'ozon_spp_analytics_by_hour')->make();
arrayToCsv($rows, '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/export/ozon_spp_analytics_by_hour.csv');

 ?>
