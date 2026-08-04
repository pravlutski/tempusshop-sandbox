<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/utilities/dpu/classes/CRUDManager.php' );

$settings = new CRUDManager(
  mp: $_GET['marketplace'],
  cab: $_GET['cabinet']
);

$items = $settings->getItems();

$result[] = ["Артикул", "Маржинальность %", "Наличие в прайсах"];

foreach ( $items as $model => $data ){
  $result[] = [
    'model' => $model,
    'margin' => $data['installed']['margin'],
    'available' => $data['isAvailable'] ? 'Да' : 'Нет'
  ];
}

$rows = array_map( fn($val) => implode(';', $val), $result );
$contents = implode(PHP_EOL, $rows);

$filename = "{$_SERVER['DOCUMENT_ROOT']}/admin/utilities/dpu/export/export_{$_GET['marketplace']}_{$_GET['cabinet']}.csv";

file_put_contents($filename, $contents);

// Убеждаемся, что до функции header() не было никакого вывода
header('Content-Description: File Transfer');
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filename));
// Убеждаемся, что нет пробелов перед readfile()
readfile($filename);
 ?>
