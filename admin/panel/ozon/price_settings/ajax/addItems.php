<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/panel/ozon/price_settings/classes/class.php' );
$time1 = time();
$data = $_POST['txt'];

if ( empty($data) ) throw new Exception('Empty Data');

$rows = explode( PHP_EOL, $data );
$items = [];

function validateModel( string $model ):bool
{
  $arFilter = [
    'IBLOCK_ID' => 16,
    'PROPERTY_CML2_ARTICLE' => $model
  ];
  $arSelect = ['IBLOCK_ID','ID','PROPERTY_CML2_ARTILCE'];
  $res = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

  return $res->SelectedRowsCount() > 0;
}

$errors = [];

foreach ( $rows as $key => $row ){

  $formatted = preg_replace('/\s+/', ';', trim($row));
  list( $model, $goal ) = explode( ';', $formatted );

  if ( empty($goal) ){
    $errors[] = "<p style='color: red;'>{$model} - Нет цены</p>";
    continue;
  }

  if ( empty($model) ){
    $errors[] = "<p style='color: red;'>Не указана модель в строке {$key}</p>";
    continue;
  }

  if ( !validateModel($model) ){
    $errors[] = "<p style='color: red;'>{$model} - неизвестный артикул</p>";
    continue;
  }

  if ( !empty($items[ $model ]) ){
    $errors[] = "<p style='color: red;'>{$model} - Повторное вхождение не учтено</p>";
    continue;
  }

  $items[ $model ] = [
    'model' => $model,
    'goal' => $goal,
    'cabinet' => 'IP',
  ];
}

$items = array_values($items);

$settings = new SettingsManager('IP');
$settings->addItems( $items );

foreach ( $errors as $error ){
  echo $error;
}
 ?>
