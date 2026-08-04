<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) || empty($_POST['idbx']) || empty($_POST['idwb']) ){
  echo '<div class="btn btn-danger">Системная ошибка! Не выбрано свойство</div>';
  die;
}
$res = CIBlockPropertyEnum::GetList( array(), ["IBLOCK_ID" => 16, "PROPERTY_ID" => $_POST['idbx']] );
if ( empty($res) ){
  echo '<div class="btn btn-danger" style="margin-bottom:20px">Данное свойство Bitrix не поддерживается</div>';
  die;
}

function formulateRequest( array $array, string $table ):string // Люблю велосипеды изобретать
{
  $columnsDB = "(";
  $valuesDB = "(";
  foreach ( $array as $column => $value ){
    if ( array_key_last($array) == $column ){
      $columnsDB .= $column . ")";
      $valuesDB .= "'{$value}')";
    }else{
      $columnsDB .= $column . ", ";
      $valuesDB .= "'{$value}', ";
    }
  }
  $strSql = "INSERT INTO {$table} {$columnsDB} VALUES {$valuesDB}";
  return $strSql;
}

$propsBX = [];
while ( $prop = $res->GetNext() ){
  $propsBX[] = [
    'property_id' => $prop['PROPERTY_ID'],
    'property_name' => $prop['PROPERTY_NAME'],
    'option_id' => $prop['ID'],
    'option_name' => $prop['VALUE'],
    'char_id' => $_POST['idwb']
  ];
}
if ( empty($propsBX) ){
  echo '<div class="btn btn-danger" style="margin-bottom:20px">Данное свойство Bitrix не поддерживается</div>';
  die;
}
foreach ( $propsBX as $opt ){
  $strSql = "SELECT 1 FROM wdhs_wb_product_props_dependencies WHERE property_id = '{$opt['property_id']}' AND option_id = '{$opt['option_id']}' AND char_id = '{$_POST['idwb']}'";
  $result = $DB->Query($strSql, false, $err_mess.__LINE__);
  if ( $result->SelectedRowsCount() > 0 ){
    echo '<div class="btn btn-danger" style="margin-bottom:20px">Зависимость для данного свойства уже создана</div>';
    break;
  }
  $strSql = formulateRequest( $opt, 'wdhs_wb_product_props_dependencies');
  $DB->Query($strSql, false, $err_mess.__LINE__);
}
unset($propBX);

$strSql = "SELECT * FROM wdhs_wb_product_props";
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$dictChar = [];
while ( $row = $result->Fetch() ){
  $dictChar[$row['char_id']] = $row['name'];
}

$strSql = "SELECT * FROM wdhs_wb_product_props_dependencies";
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$dependecies = [];
while ( $row = $result->Fetch() ){
  $key = $row['property_name'] . '_' . $row['property_id'] . '_' . $row['char_id'];
  $dependecies[$key][] = $row;
}
 ?>

 <? foreach ( $dependecies as $nid => $elem ):?>
   <?php
     $name = explode('_', $nid)[0];
     $pid = explode('_', $nid)[1];
     $cid = explode('_', $nid)[2];
   ?>
   <details id="<?=$pid?>_<?=$cid?>" class="dep-piece">
     <summary><?=$name?> --> <?=$dictChar[$cid]?></summary>
     <hr>
     <button id="del_<?=$pid?>_<?=$cid?>" class="btn btn-danger del-btn">Удалить зависимость</button>
     <? foreach ( $elem as $option ):
       $text = '';
       if ( !empty($option['value']) ){
         $value = json_decode($option['value']);
         if ( count($value) > 1) {
           $text = implode(';', $value);
         }else{
           $text = $value[0];
         }
       }
       ?>
     <div class="dep-row row">
       <span class="dep-name"><?=$option['option_name']?></span>
       <input type="text" name="<?=$option['option_id']?>+<?=$option['char_id']?>" class="form-input dep-value" value="<?=$text?>">
     </div>
     <? endforeach;?>
   </details>
 <? endforeach;?>
