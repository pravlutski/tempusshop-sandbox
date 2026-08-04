<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/utilities/dpu/classes/CRUDManager.php' );
$time1 = time();
$data = $_POST['txt'];

if ( empty($data) ) throw new Exception('Empty Data');

if ( empty($_POST['marketplace']) || empty($_POST['cabinet']) ){
  throw new Exception("Empty marketplace or cabinet");
}

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
  list( $model, $goal, $margin, $profit, $step ) = explode( ';', $formatted );

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
    'goal' => empty($goal) ? null : (int) $goal,
    'min_profit_perc' => empty($margin) ? null : (int) $margin,
    'min_profit_rub' => empty($profit) ? null : (int) $profit,
    'step' => empty($step) ? null : (int) $step,
    'cabinet' => $_POST['cabinet'],
  ];
}

$items = array_values($items);

$settings = new CRUDManager(
  mp: $_POST['marketplace'],
  cab: $_POST['cabinet']
);
$settings->addItems( $items );

foreach ( $errors as $error ){
  echo $error;
}
 ?>
