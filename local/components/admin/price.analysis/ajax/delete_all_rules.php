<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;
global $USER;

$source = $_POST['source'];
$strSql = "DELETE FROM individual_markups WHERE source = '{$source}'";
$DB->Query($strSql, false, $err_mess.__LINE__);

CModule::IncludeModule('panel.manager');
$triggers = new TsTriggers();
$message = "Очищены все настройки индивидуальных наценок для {$source}\n";
$message .= "Очистил пользователь: " . $USER->GetLogin() . " " . date('Y.m.d G:i:s');

$triggers->SetError([$message]);
$triggers->SendTriggerErrors();

$strSql = "SELECT * FROM individual_markups WHERE source = '{$source}'";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$addedRules = [];
while( $row = $resultDB->GetNext() ){
  $addedRules[] = [
    'id' => $row['id'],
    'model' => $row['model'],
    'markup' => $row['markup'],
    'source' => $row['source'],
    'user_id' => $row['user_id'],
    'date' => $row['date']
  ];
}
$fboGoods = [];
if ( $source == 'os' ){
  $strSql = "SELECT * FROM wdhs_ozon_fbo_price";
  $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
  while ( $row = $resultDB->Fetch() ){
    $fboGoods[$row['article']] = 1;
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

foreach ( $addedRules as $rule ):
?>
<label id="ruleim_container_<? echo $rule['id'];?>" class="rule-container" for="">
  <span class="model-label"><? echo $fboGoods[$rule['model']] ? $rule['model'] . ' (FBO)': $rule['model'];?></span>
  <input id="rule_im_<? echo $rule['id'];?>_<? echo $rule['source'];?>" type="text" name="rule_<? echo $rule['id'];?>_<? echo $rule['source'];?>" class="rule-input form-control" value="<? echo $rule['markup'];?>">
  <button id="delete_im_<? echo $rule['id'];?>_<? echo $rule['source'];?>" class="btn btn-danger del-btn-im">Удалить</button>
</label>
<?endforeach;?>
