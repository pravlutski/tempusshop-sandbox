<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $USER;
global $DB;
$source = $_POST['source'];
$fboGoods = [];
if ( $source == 'os' ){
  $strSql = "SELECT * FROM wdhs_ozon_fbo_price";
  $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
  while ( $row = $resultDB->Fetch() ){
    $fboGoods[$row['article']] = 1;
  }
}
$strSql = "SELECT * FROM individual_markups WHERE source = '{$source}'";
// var_dump($strSql);
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
foreach ( $addedRules as $rule ):
?>
<label id="ruleim_container_<? echo $rule['id'];?>" class="rule-container" for="">
  <span class="model-label"><? echo $fboGoods[$rule['model']] ? $rule['model'] . ' (FBO)': $rule['model'];?></span>
  <input id="rule_im_<? echo $rule['id'];?>_<? echo $rule['source'];?>" type="text" name="rule_<? echo $rule['id'];?>_<? echo $rule['source'];?>" class="rule-input form-control" value="<? echo $rule['markup'];?>">
  <button id="delete_im_<? echo $rule['id'];?>_<? echo $rule['source'];?>" class="btn btn-danger del-btn-im">Удалить</button>
</label>
<?endforeach;?>
