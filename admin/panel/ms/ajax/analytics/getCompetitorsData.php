<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::includeModule('panel.manager');
$dbPanel = new DBPanel;
global $DB;

// ВНИМАНИЕ
// ЭТО УЖАСНО СЛОЖНАЯ И КОСТЫЛЬНАЯ КОНСТРУКЦИЯ. ПРИ ПРОМСОТРЕ МОЖЕТ БЫТЬ ОЧЕНЬ БОЛЬНО. ИЗМЕНЯЙТЕ НА СВОЙ СТРАХ И РИСК

$dateFrom = $_POST['dateFrom'];
$dateTo = $_POST['dateTo'];

$whereSql = '';
if ( !empty($_POST['data']) ){
  $arPairs = explode('&', $_POST['data']);
  foreach ($arPairs as $pair) {
    $seller[] = urldecode( explode('=',$pair)[0] );
  }
  $res = array_map(function($item){
    return "'".$item."'";
  }, $seller);
  $whereSql = ' AND seller IN ('.implode(',', $res).')';
}else{
  echo "<span class='notif'>Необходимо выбрать хотя бы одного продавца</span>";
  die;
}
$tableName = "ozon_competitors";

if ( !empty($dateFrom) && !empty($dateTo) ){
  $strSql = "SELECT * FROM {$tableName} WHERE date >= '{$dateFrom}' AND date <= '{$dateTo}'";
}
elseif ( !empty($dateFrom) && empty($dateTo) ){
  $strSql = "SELECT * FROM {$tableName} WHERE date >= '{$dateFrom}'";
}
elseif ( empty($dateFrom) && !empty($dateTo) ){
  $strSql = "SELECT * FROM {$tableName} WHERE date <= '{$dateTo}'";
}
elseif( empty($dateFrom) && empty($dateTo) ){
  $dateFrom = date('Y-m-d', strtotime('- 5 day'));
  $dateTo = date('Y-m-d');
  $strSql = "SELECT * FROM {$tableName} WHERE date >= '{$dateFrom}' AND date <= '{$dateTo}'";
}
$strSql .= $whereSql;
$strSql .= " ORDER BY date ASC";

// $strSql = "SELECT * FROM ozon_top_analytics";
$result = $dbPanel->query( $strSql );
$rows = $dbPanel->fetchAll( $result );
foreach ( $rows as $row ){
  unset( $row['id'] );
  unset( $row['median'] );
  // $row['date'] = date( 'Y.m.d', strtotime($row['date']) );
  $arCalc[$row['seller']][$row['model']][$row['date']] = $row;
  $arOurPrice[$row['date']][$row['model']] = $row['our'];
}
$dateFrom = $rows[0]['date'];
$dateTo = end($rows)['date'];
$arOurPrice = $arOurPrice[$dateTo];

$arPeriod = [];
$ds = $dateFrom;
while ( $ds <= $dateTo ){
  $arPeriod[] = $ds;
  $ds = date( 'Y-m-d', strtotime($ds . '+ 1 day') );
}
foreach ( $arCalc as $seller => $arSel ){
  foreach ( $arSel as $model => $arModel ){
    foreach ( $arPeriod as $cd ){
      if ( empty( $arModel[$cd]) ){
        $arCalc[$seller][$model][$cd] = [
          'model' => $model,
          'b_price' => 0,
          'g_price' => 0,
          'date' => $cd,
        ];
      }
    }
  }
}

$tableData = [];
$tableHead = [];

$header = [
  'model' => ' ',
  'b_price' => 'Черная цена',
  'g_price' => 'Зеленая цена'
];

$arDiff = ['b_price', 'g_price']; //Массив с полями, по которым надо выводить колонку с изменениями
$days = ( strtotime($dateTo) - strtotime($dateFrom) ) / 3600 / 24;
$columnsCount = ($days + 1) * 2 + ( $days * count($arDiff) );
if ( $dateTo == date('Y-m-d') ){
  $columnsCount = $columnsCount - 1;
}
  // echo '<pre>';
  // var_dump($arCalc);
  // echo '</pre>';
  // die;
function calculateDifference( $calc, $seller, $model, $date, $field, &$arDate = false ){
  $f_now = $calc[$seller][$model][$date][$field];
  $datePast = date( 'Y-m-d', strtotime($date . ' - 1 day') );
  $f_past = $calc[$seller][$model][$datePast][$field];

  if ( $f_past === NULL ) return;
  if ( $f_past == 0 ){
    if($arDate != false) $arDate[$date] = true;
    if ($f_now == 0){
      return [
        'date' => $date,
        'value' => round(floatval(0), 2),
        'className' => ''
      ];
    }
    return [
      'date' => $date,
      'value' => round(floatval(100), 2),
      'className' => ''
    ];
  }

  $result = ($f_now - $f_past) / $f_past * 100;
  if ( $result === NULL ) return;
  if($arDate != false) $arDate[$date] = true;
  return [
    'date' => $date,
    'value' => round(floatval($result), 2),
    'className' => colorCell( round(floatval($result), 2), $field )
  ];
}

// function colorCell($percent, $column){
//   if( $percent == 0 ) return '';
//   if( in_array($column, ['b_price', 'g_price']) ){
//     $percent = $percent / 1;
//   }
//   if ( $percent > 15 ){
//     $className = 'green-15';
//   }elseif( $percent > 10 ){
//     $className = 'green-10';
//   }elseif( $percent > 5 ){
//     $className = 'green-5';
//   }elseif( $percent > 1 ){
//     $className = 'green-0';
//   }elseif( $percent < -15 ){
//     $className = 'red-15';
//   }elseif( $percent < -10 ){
//     $className = 'red-10';
//   }elseif( $percent < -5 ){
//     $className = 'red-5';
//   }elseif( $percent < -1 ){
//     $className = 'red-0';
//   }
//   return $className;
// }

function colorCell($percent, $column){
  if( $percent == 0 || round($percent) == 0 ) return '';
  if( in_array($column, ['sell', 'our', 'g_price']) ){
    $percent = $percent / -1;
  }
  $className = '';
  $steps = [15, 10, 5, 1, 0];
  foreach ($steps as $value) {
    if ( intval(round($percent)) >= floatval($value) ){
      $classTag = $value == 0 ? 1 : $value;
      $className = 'green-' . $classTag;
      return $className;
    }
  }
  foreach ( $steps as $value ){
    if ( intval(round($percent)) <= ($value / -1) ){
      $classTag = $value == 0 ? 1 : $value;
      $className = 'red-' . $classTag;
      return $className;
    }
  }
  return $className;
}

function getAltArt( string $model ):string
{
  global $DB;
  $strSql = "SELECT artnumber FROM ci_catalog_artnumbers WHERE artnumber = '{$model}' OR alternative = '{$model}'";

  $res = $DB->Query( $strSql, false, $err_mess.__LINE__ );
  if ( $res->SelectedRowsCount() > 0 ){
    $result = $res->Fetch();
    return $result['artnumber'];
  }
  return $model;
}

$keys = array_keys( $rows[0] );
$arDateCheck = [1];
foreach ( $rows as $key => $row ){
  foreach ( $keys as $k){
    calculateDifference($arCalc, $seller, $row['model'], $row['date'], 'b_price', $arDateCheck);
    $tableDataTmp[$row['seller']][$row['model']][$k][$row['date']] = [
      'model' => $row['model'],
      'b_price' => $row['b_price'],
      'g_price' => $row['g_price'],
      'link' => $row['link'],
      'date' => $row['date'],
    ];
    $headerData[$k][$row['date']] = $row;
    // unset($tableData[$row['model']][$k][$row['date']]['sell_diff']);
  }
}

// Сортируем по количеству товаров у продавца
$arSort = [];
foreach ( $tableDataTmp as $seller => $ar ){
  $arSort[$seller] = count($ar);
}
asort($arSort);
foreach ( $arSort as $seller => $count ){
  $tmp[$seller] = $tableDataTmp[$seller];
}
$tableDataTmp = $tmp;
unset($arSort);

foreach ( $tableDataTmp as $seller => $arModel){
  foreach ( $arModel as $model => $arColumns ){
    foreach ( $arColumns as $colName => $daRow ){
      foreach ( $arPeriod as $cd ){
        if ( !isset($daRow[$cd]) ){
          $tableDataTmp1[$seller][$model][$colName][$cd] = [
            'model' => $model,
            'b_price' => 0,
            'g_price' => 0,
            'link' => '',
            'date' => $cd,
          ];
        }else{
          $tableDataTmp1[$seller][$model][$colName][$cd] = $tableDataTmp[$seller][$model][$colName][$cd];
        }
      }
    }
  }
}
$tableData = $tableDataTmp1;
$last_date = array_key_last( $headerData['model'] );
 ?>
<? foreach ($tableData as $seller => $arData ):?>
<div class="table-main">
 <table class="table table-data">
   <thead class="sticked-top">
      <?
      foreach( $headerData as $colName => $dbRow ){
        $i = 0;
        foreach ( $dbRow as $date => $data ){
          if ( $i != 0 && $colName == 'model' ) continue;
          if ( $header[$colName] == NULL ) continue;
          if ( $colName == 'model' ){
            echo '<th class="bordered">';
            echo $seller . ' ' . '('.count($tableData[$seller]).')';
            echo '</th>';
            echo '<th></th>';
          }else{
            if ( in_array($colName, $arDiff) ){
              echo '<th>';
                echo ($header[$colName] != '') ? $header[$colName] . ' ' . date('Y.m.d',strtotime($data['date'])) : '';
              echo '</th>';
              if ( $arDateCheck[$date] ){
                // if ( $date == $last_date){
                //   echo '<th class="bordered">';
                // }else{
                //   echo '<th class="">';
                // }
                // // var_dump($date);
                // echo 'Изменения ' . $date;
                // echo '</th>';
              }
            }else{
              echo $date == $last_date ? '<th class="bordered">' : '<th>';
              echo ($header[$colName] != '') ? $header[$colName] . ' ' . $data['date'] : '';
            }
          }
          echo '</th>';
          if( $date == $last_date && $dateFrom != $dateTo ){
            echo '<th class="bordered"></th>';
          }
          $i++;

        }
      }
      ?>
   </thead>
   <tbody>
     <?
     $total_inc = array_fill(0, $columnsCount + 1, 0);
     $total_dec = array_fill(0, $columnsCount + 1, 0);
     foreach( $arData as $model => $modelRow){
      echo '<tr>';
      $i = 0;
      $k = 1;
      foreach( $modelRow as $colName => $dbRow ){
        $c = 1;
        $colspan = 0;
        $summCheck = 0;
        $last_sale = '';
        foreach ( $dbRow as $date => $data ){
            if ( $i != 0 && $colName == 'model' ) continue;
            if ( $colName == 'date' || $colName == 'link' ) continue;
            if ( $data[$colName] === NULL ) continue;
            if ( $colName != 'model'  ){
              if ( in_array($colName, $arDiff) ){
                $diff = calculateDifference($arCalc, $seller, $model, $date, $colName);
                if ( $diff != NULL && round($diff['value']) != 0 && $diff['value'] != 100 && $diff['value'] != -100 ){
                  $percent = ($diff['value'] > 0 && round($diff['value']) != 0 ) ? '+' . round($diff['value']): round($diff['value']);
                  $percent = ($percent == -0) ? abs($percent) : $percent;
                  $cellData = $data[$colName] . ' <span class="">('.$percent.'%)</span>';
                }else{
                  $cellData = $data[$colName];
                }

                if ( $data[$colName] == 0 ){
                  echo '<td class="no-data" style="max-width: 165px !important;min-width: 165px !important;" title="'.$colName.$date.'">';
                }else{
                  echo '<td class="'.$diff['className'].'" title="'.$colName.$date.'" style="max-width: 165px !important;min-width: 165px !important;">';
                }
                echo $data[$colName] == 0 ? 'Нет в наличии' : $cellData;
                echo '</td>';
                if ( $date == $last_date ){
                  echo "<td class='bordered' style='padding:0.5rem;max-width: 165px !important;min-width: 165px !important;'></td>";
                }

              }else{
                echo "<td class='bordered' style='padding:0.5rem;max-width: 165px !important;min-width: 165px !important;'>";
                echo $data[$colName];
                echo '</td>';

                if ( $date == $last_date ){
                  echo "<td class='bordered' style='padding:0.5rem;max-width: 165px !important;min-width: 165px !important;'></td>";
                }
              }
            }else{
              $model = getAltArt($data[$colName]);
              if ( isset($arOurPrice[$model]) ){
                $our_price = $arOurPrice[$model];
              }else{
                $our_price = '';
              }
              echo '<td class="bordered sticked-left" style="max-width: 210px !important;min-width: 210px !important;">';
              echo '<a href="'.$data['link'].'">' . $model . '</a>';
              echo '</td>';
              echo '<td class="bordered sticked-price" style="max-width: 70px !important;min-width: 70px !important;">';
              echo $our_price == 0 ? '<span class="no-data">Н/Д</span>' : $our_price;
              echo '</td>';
            }
            $i++;
            $c++;
          }
      }
      echo "</tr>";
     }
     ?>
   </tbody>
 </table>
</div>
<?endforeach;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
