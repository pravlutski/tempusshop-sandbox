<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $USER;
global $DB;
$user_id = $USER->GetID();
// echo 'get all' .date("G:i:s") . '<br>';
$source = $_POST['input-source'];
$strSql = "SELECT * FROM individual_markups WHERE source = '{$source}'";
$rulesDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$addedRules = [];
while( $row = $rulesDB->GetNext() ){
  $addedRules[ $row['model'] ] = [
    'id' => $row['id'],
    'model' => $row['model'],
    'markup' => $row['markup'],
    'source' => $row['source'],
    'user_id' => $row['user_id'],
    'date' => $row['date']
  ];
}
// echo 'end ' .date("G:i:s") . '<br>';
if ( !empty($_POST['input-rules']) ){
  $rulesTmp = explode("\n", $_POST['input-rules']);
  // $source = $_POST['input-source'];
  $rules = [];
  $models = [];
  // echo 'check input ' .date("G:i:s") . '<br>';
  foreach ($rulesTmp as $value) {
    $value = preg_replace('/\s+/', ';', $value);
    $arRule = explode(';', $value);
    $markup = floatval( str_replace(',', '.', $arRule[1]) );
    $models[] = $arRule[0];
    if ( empty($arRule[1]) ){
      $arLog['no_markup'][] = $arRule[0];
      continue;
    }

    $rules[] = [
      'model' => mb_strtoupper($arRule[0]),
      'bitrix_id' => $bitrix_id,
      'markup' => $markup,
      'source' => $source,
      'user_id' => $user_id,
      'timestamp' => date('Y-m-d G:i:s'),
    ];
  }
  $bxInfo = getItemsIds( $models );

  // echo 'end ' .date("G:i:s") . '<br>';
  // echo 'check\update ' .date("G:i:s") . '<br>';
  $toAdd = [];
  foreach ( $rules as $rule){
    $model = $rule['model'];
    $bitrix_id = $bxInfo[ $rule['model'] ] ?? false;
    if ( !$bitrix_id ){
      $arLog['no_id'][] = $rule['model'];
      continue;
    }
    $rule['bitrix_id'] = $bitrix_id;
    $markup = $rule['markup'];
    $source = $rule['source'];
    $user_id = $rule['user_id'];
    $timestamp = $rule['timestamp'];

    $log[] = $rule;

    if ( isset($addedRules[$rule['model']]) ){

      if ( $addedRules[ $rule['model'] ]['markup'] == $rule['markup'] ) continue;

      $strSql = "UPDATE individual_markups SET markup = '{$rule['markup']}', timestamp = '{$timestamp}' WHERE bitrix_id = '{$rule['bitrix_id']}' AND source = '{$rule['source']}'";

      $DB->Query($strSql, false, $err_mess.__LINE__);

      continue;
    }
    $toAdd[] = $rule;
  }
  // echo 'end ' .date("G:i:s") . '<br>';
  // echo 'insert ' .date("G:i:s") . '<br>';
  fuckYouBitrixORM( 'individual_markups', $toAdd );
  // echo 'end ' .date("G:i:s") . '<br>';
}

CModule::IncludeModule('panel.manager');

if ( count($log) > 144 ){
  $logChunks = array_chunk( $log, 144 );

  foreach ( $logChunks as $key => $chunk ){
    sendMessage($chunk, $addedRules, $source);
  }
}else{
  sendMessage($log, $addedRules, $source);
}

function sendMessage( $log, $addedRules, $source )
{
  global $USER;
  $triggers = new TsTriggers();
  $message = "Изменены настройки индивидуальных наценок для {$source}\n";

  foreach ( $log as $rule ){
    $oldVal = $addedRules[ $rule['model'] ] ?? false;

    $isChanged = false;
    if ( $oldVal ) $isChanged = ($oldVal['markup'] != $rule['markup']);

    $oldBlock = '';
    if ( $isChanged ) $oldBlock = " {$oldVal['markup']} -->";

    $message .= "{$rule['model']} ||{$oldBlock} {$rule['markup']}\n";
  }

  $message .= "Изменил пользователь: " . $USER->GetLogin() . " " . date('Y.m.d G:i:s');

  $triggers->SetError([$message]);
  $triggers->SendTriggerErrors();
}

function getItemsIds($models)
{
  $arFilter = [
    'IBLOCK_ID' => 16,
    'PROPERTY_CML2_ARTICLE' => $models
  ];
  $arSelect = ['IBLOCK_ID','ID','PROPERTY_CML2_ARTICLE'];
  $result = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
  $items = [];
  while ( $row = $result->GetNext() ){
    $items[$row['PROPERTY_CML2_ARTICLE_VALUE']] = $row['ID'];
  }

  return $items;
}

function fuckYouBitrixORM($tableName , $arrayData)
{
  global $DB;
  $cardSample = $arrayData[0];
  $fields = [];
  foreach ($cardSample as $key => $value) {
    $fields[] = $key;
  }
  if (empty($fields) || count($fields) < 2) return false;
  $strSql = "INSERT INTO {$tableName} " . '(';

  $i = 0;
  foreach ($fields as $fname) {
    $strSql .= (count($fields) - 1 != $i) ? "{$fname}," : $fname;
    $i++;
  }
  $strSql .= ') VALUES ';
  $c = 0;
  foreach ($arrayData as $card){
    $strSql .= '(';
    $k = 0;
    foreach ($card as $field) {
      $strSql .= (count($card) - 1 != $k) ? "'{$field}'," : "'{$field}'";
      $k++;
    }
    $strSql .= ( count($arrayData) - 1 != $c ) ? '),' : ')';
    $c++;
  }
  // var_dump($strSql);
  try{
    $DB->query( $strSql );
  }catch ( Throwable $e){
    print_r( $e );
    print_r( $strSql );
  }
}

// Запускаем обновление цен
$channelsDict = [
  'wb' => 'WB',
  'wbtl' => 'WBTL',
  'ru' => 'RU',
  'os' => 'OS',
  'ozti' => 'OZTI',
  'by' => 'BY',
  'wb' => 'WB',
  'sb' => 'SB',
  'av' => 'AV'
];
if ( isset($channelsDict[$source]) ){
  exec("php /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_price_analys.php PRICE_ID={$channelsDict[$source]} > /dev/null 2>&1 &");
  print_r('Запущено обновление цен для выбранного канала продаж<br>');
}

if ( !empty($arLog) ):
?>
<div class="error-im rule-container w-100" >
  <?foreach ( $arLog['no_id'] as $error ){
    echo "<span>" . $error . " - Ошибка: не найден id</span>";
  }
  foreach ( $arLog['no_markup'] as $error ){
    echo "<span>" . $error . " - Ошибка: не указана наценка или указан ноль</span>";
  }
  ?>
</div>
<?
endif;
$fboGoods = [];
if ( $source == 'os' ){
  $strSql = "SELECT * FROM wdhs_ozon_fbo_price";
  $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
  while ( $row = $resultDB->Fetch() ){
    $fboGoods[$row['article']] = 1;
  }
}
$strSql = "SELECT * FROM individual_markups WHERE source = '{$source}'";
$rulesDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$addedRules = [];
while( $row = $rulesDB->GetNext() ){
  $addedRules[] = [
    'id' => $row['id'],
    'model' => $row['model'],
    'markup' => $row['markup'],
    'source' => $row['source'],
    'user_id' => $row['user_id'],
    'date' => $row['date']
  ];
}
foreach ( $addedRules as $rule ):
?>
<label id="ruleim_container_<? echo $rule['id'];?>" class="rule-container" for="">
  <span class="model-label"><? echo $fboGoods[$rule['model']] ? $rule['model'] . ' (FBO)': $rule['model'];?></span>
  <input id="rule_im_<? echo $rule['id'];?>_<? echo $rule['source'];?>" type="text" name="rule_<? echo $rule['id'];?>_<? echo $rule['source'];?>" class="rule-input form-control" value="<? echo $rule['markup'];?>">
  <button id="delete_im_<? echo $rule['id'];?>_<? echo $rule['source'];?>" class="btn btn-danger del-btn-im">Удалить</button>
</label>
<?endforeach;?>
