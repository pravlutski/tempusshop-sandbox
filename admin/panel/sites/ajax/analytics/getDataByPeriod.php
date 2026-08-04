<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::includeModule('panel.manager');
$dbPanel = new DBPanel;
global $DB;

// ВНИМАНИЕ
// ЭТО УЖАСНО СЛОЖНАЯ И КОСТЫЛЬНАЯ КОНСТРУКЦИЯ. ПРИ ПРОМСОТРЕ МОЖЕТ БЫТЬ ОЧЕНЬ БОЛЬНО. ИЗМЕНЯЙТЕ НА СВОЙ СТРАХ И РИСК
/*
В ролях:
ozon_top_analytics - таблица, которая хранит основную массу выводимых данных
ozon_competitors - таблица, в которой хранится спаршенная зеленая цена
ozon_stock_fbo_stat_ti - таблица, которая хранит остаток фбо (обновляется ежедневно в 10:30)
ozon_sales_pi_TI - список топовых моделей, относительно которого сортируются данные
*/

// Получаем данные из формы
$dateFrom = $_POST['dateFrom'];
$dateTo = $_POST['dateTo'];
// Фильтр по полям приходит в сериализованном виде, разбираем
$data = explode( '&', $_POST['data'] );
if ( !empty($data) && $_POST['data'] != '' ){
  $allowedFields = ['model'];
  foreach ( $data as $pair ){
    $allowedFields[] = explode('=', $pair)[0];
  }
}else{
  die('Не указано ни одно поле для отображения');
}

// Делаем формируем запрос для выборки из основной таблицы
$tableName = "wb_top_analytics";
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
  $dateFrom = date('Y-m-d', strtotime('- 4 day'));
  $dateTo = date('Y-m-d');
  $strSql = "SELECT * FROM {$tableName} WHERE date >= '{$dateFrom}' AND date <= '{$dateTo}'";
}
$strSql .= " ORDER BY date ASC";

// Делаем выборку из основной таблицы, чтобы сформировать вспомогательный массив для расчетов
$result = $dbPanel->query( $strSql );
$rows = $dbPanel->fetchAll( $result );
foreach ( $rows as $row ){
  unset( $row['id'] );
  unset( $row['median'] );
  $arCalc[$row['model']][$row['date']] = [
    'model' => $model,
    'our_price' => $row['our_price'],
    'sell_price' => $row['sell_price'],
    'black_price' => $row['black_price'],
    // 'orders_count' => $row['orders_count'],
    'date' => $row['date'],
  ];
}
// Формируем массив с датами в пределах выбранного периода или периода по умолчанию
$dateFrom = $rows[0]['date'];
$dateTo = end($rows)['date'];
$arPeriod = [];
$ds = $dateFrom;
while ( $ds <= $dateTo ){
  $arPeriod[] = $ds;
  $ds = date( 'Y-m-d', strtotime($ds . '+ 1 day') );
}

// Заполняем вспомогательный массив недостающими датами, чтобы избежать ошибок в расчетах
foreach ( $arCalc as $model => $arModel ){
    foreach ( $arPeriod as $cd ){
      if ( empty( $arModel[$cd]) ){
        $arCalc[$model][$cd] = [
          'nmid' => '',
          'model' => $model,
          'our_price' => 0,
          'sell_price' => 0,
          'black_price' => 0,
          // 'orders_count' => $row['orders_count'],
          'date' => $cd,
        ];
      }
    }
}
// Извлекаем топы, для сортировки
$strSql = "SELECT * FROM wb_fbo_stat_WR";
$resultF = $dbPanel->Query( $strSql, false, $err_mess.__LINE__ );
$rowsF = $dbPanel->fetchAll($resultF);
$stockFbo = [];
foreach( $rowsF as $row ){
  $stockFbo[ $row['stock_date'] ][ $row['model'] ] = $row['stock'];
}

$tableData = [];

// Массив с именанми колонк для отображения
$header = [
  'model' => ' ',
  'is_fbo' => 'ФБО',
  'our_price' => 'Цена продавца',
  'black_price' => 'Цена (чёрная)',
  'sell_price' => 'Цена (кошелёк)',
  'spp' => 'Соинвест (стд)',
  'c_spp' => 'Соинвест (кошелёк)',
  // 'orders_count' => 'Количество продаж',
];

// Массив с колонками, для которых будут считаться и выводиться изменения
$arDiff = ['our_price', 'sell_price', 'black_price'];
// Исключаем из массива поля, которые не были получены из формы
foreach ( $arDiff as $key => $field ){
  if ( !in_array($field, $allowedFields) ){
    unset( $arDiff[$key] );
  }
}
// Считаем количество колонок для дополнительной таблицы с итогами ( я не понимаю, почему это работает )
$days = ( strtotime($dateTo) - strtotime($dateFrom) ) / 3600 / 24;
$columnsCount = ($days + 1) * (count($allowedFields) ) - $days ;
var_dump( $columnsCount );
// $columnsCount = ($days + 1) * (count($allowedFields) - 1) + ( $days * count($arDiff) );
if ( $dateTo == date('Y-m-d') ){
  // $columnsCount = $columnsCount - 1;
}
// Функция для подсчетов изменений. Также заполняет массив с датами, для которых надо выводить колонку
function calculateDifference( $calc, $model, $date, $field, &$arDate = false ){
  // Примечание: под текущим днем надо считать день переданный аргументом $date
  $f_now = $calc[$model][$date][$field];
  $datePast = date( 'Y-m-d', strtotime($date . ' - 1 day') );
  $f_past = $calc[$model][$datePast][$field];

  // Если нет данных за предыдущий день, значит, что этот день выходит за указанный период.
  if ( $f_past === NULL ) return;
  // Если за предыдущий день ноль, выходим из функции, чтобы избежать деления на ноль ( процент -100 )
  if ( $f_past == 0 ){
    if($arDate != false) $arDate[$date] = true;
    // Если за текущий, то аналогично выходим ( процент +100 )
    if ($f_now == 0){
      return [
        'date' => $date,
        'value' => round(floatval(0), 2),
        'className' => colorCell(floatval(0), 2)
      ];
    }
    return [
      'date' => $date,
      'value' => round(floatval(100), 2),
      'className' => colorCell(floatval(100), 2)
    ];
  }
  // Считаем разницу в процентах между текущим и предыдущим днем.
  $result = ($f_now - $f_past) / $f_past * 100;
  if ( $result === NULL ) return;
  if($arDate != false) $arDate[$date] = true;
  // Возвращаем массив с датой, разницей и именем класса для покраски ячейки
  return [
    'date' => $date,
    'value' => round(floatval($result), 2),
    'className' => colorCell( round(floatval($result), 2), $field )
  ];
}
// Примитивная функция для покраски ячеек
function colorCell($percent, $column){
  if( $percent == 0 || round($percent) == 0 || abs($percent) == 100) return '';
  if( in_array($column, ['sell_price', 'our_price', 'black_price']) ){
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
$keys = [
  'model',
  'our_price',
  'black_price',
  'sell_price',
  'spp',
  'c_spp',
  'is_fbo'
];

$arDateCheck = [1];
try{
  foreach ( $rows as $key => $row ){
    unset($row['id']);
    unset( $row['median'] );
    foreach ( $keys as $k){
      calculateDifference($arCalc, $row['model'], $row['date'], 'sell_price', $arDateCheck);
      $spp = round( ($row['our_price'] - $row['black_price']) / $row['our_price'] * 100 );
      $c_spp = round( ($row['our_price'] - $row['sell_price']) / $row['our_price'] * 100 );
      $tableDataTmp[$row['model']][$k][$row['date']] = [
        'model' => $row['model'],
        'nmid' => $row['nmid'],
        'our_price' => $row['our_price'],
        'black_price' => $row['black_price'],
        'sell_price' => $row['sell_price'],
        // 'spp' => 0,
        // 'c_spp' => 0,
        'spp' => $spp == 100 ? '<span class="no-comps">Нет в наличии</span>' : $spp,
        'c_spp' => $c_spp == 100 ? '<span class="no-comps">Нет в наличии</span>' : $c_spp,
        'is_fbo' => $stockFbo[$row['date']][$row['model']] ?? 0,
        'date' => $row['date'],
      ];
      $headerData[$k][$row['date']] = $row;
      // unset($tableData[$row['model']][$k][$row['date']]['sell_diff']);
    }
    unset($row);
  }
}catch ( Throwable $e ){
  var_dump($e);
  var_dump( $row['model'] );
  var_dump( $row['date'] );
  var_dump( $row['our_price'] );
  var_dump( $row['sell_price'] );
}

foreach ( $tableDataTmp as $model => $arModel ){
  foreach ( $arModel as $colName => $daRow ){
    foreach ( $arPeriod as $cd ){
      if ( !isset($daRow[$cd]) ){
        $tableDataTmp1[$model][$colName][$cd] = [
          'model' => $model,
          'nmid' => $row['nmid'],
          'our_price' => 0,
          'black_price' => 0,
          'sell_price' => 0,
          'spp' => 0,
          'c_spp' => '0',
          'is_fbo' => $stockFbo[$model][$cd] ?? 0,
          'date' => $cd,
        ];
      }else{
        $tableDataTmp1[$model][$colName][$cd] = $tableDataTmp[$model][$colName][$cd];
      }
    }
  }
}
$result = $dbPanel->query("SELECT * FROM wb_top_models");
$rows = $dbPanel->fetchAll($result);
foreach ($rows as $row) {
  $tops[] = $row['model'];
}
$tableData = [];
foreach ( $tops as $model ){
  if ( !empty($tableDataTmp1[$model]) ){
    $tableData[$model] = $tableDataTmp1[$model];
  }
}
var_dump( count($tableData) );
$summaryCols = ['our_price', 'sell_price' , 'black_price', 'is_fbo',];
$last_date = array_key_last( $headerData['model'] );


 ?>
<div class="table-main">
 <table class="table table-data">
   <thead class="sticked-top">
      <?
      foreach( $headerData as $colName => $dbRow ){
        if ( !in_array($colName, $allowedFields) ) continue;
        $i = 0;
        foreach ( $dbRow as $date => $data ){
          if ( $i != 0 && $colName == 'model' ) continue;
          if ( $header[$colName] == NULL ) continue;
          if ( $colName == 'model' ){
            echo '<th class="bordered">';
            echo '';
          }else{
            if ( $colName == 'orders_count' && $date == date('Y-m-d') ) continue;
            if ( in_array($colName, $arDiff) ){
              echo '<th>';
              echo ($header[$colName] != '') ? $header[$colName] . ' ' . date('Y.m.d',strtotime($data['date'])) : '';
              echo '</th>';
            }else{
              echo $date == $last_date ? '<th class="">' : '<th>';
              echo ($header[$colName] != '') ? $header[$colName] . ' ' . $data['date'] : '';
            }
          }
          echo '</th>';
          if( $date == $last_date ){
            echo '<th class="bordered"></th>';
          }
          $i++;

        }
      }
      ?>
   </thead>
   <tbody>
     <?
     $columnSpace = [];
     $rowHeadersNegative = [
       'с_spp' => 'Нет ФБО:',
       'is_fbo' => 'Не в  акции:',
       'sale_name' => 'Уменьшилось:'
     ];
     $rowHeadersPositive = [
       'с_spp' => 'Есть ФБО:',
       'is_fbo' => 'В акции:',
       'sale_name' => 'Увеличилось:'
     ];
     $total_inc = array_fill(0, $columnsCount , 0);
     $total_dec = array_fill(0, $columnsCount , 0);
     foreach( $tableData as $model => $modelRow){
      echo '<tr>';
      $i = 0;
      $k = 1;
      // $c = 0;
      // unset($colspan);
      foreach( $modelRow as $colName => $dbRow ){
        if ( !in_array($colName, $allowedFields) ) continue;
        $c = 1;
        $colspan = 0;
        $summCheck = 0;
        $last_sale = '';
        foreach ( $dbRow as $date => $data ){
            if ( $i != 0 ) unset($data['model']);
            unset($data['date']);
            if ( $data[$colName] === NULL ) continue;
            if ( $colName != 'model' && $colName != 'nmid'  ){
              if ( in_array($colName, $arDiff) ){

                $diff = calculateDifference($arCalc, $model, $date, $colName);

                if ( $diff != NULL ){
                  if ( $diff['value'] >= 1 ){
                    $total_inc[$i] += 1;
                  }elseif( $diff['value'] <= 1 && $diff['value'] != 0 ){
                    $total_dec[$i] += 1;
                  }
                  $k++;

                  if ( $date == $last_date ){
                    $columnSpace[$i] = $colName;
                  }
                }
                if ( $data[$colName] == 0 ){
                  echo '<td style="max-width: 155px !important;min-width: 155px !important;" class="no-comps">';
                  echo 'Нет в наличии';
                  echo '</td>';
                }else{
                  echo '<td class="'.$diff['className'].'" style="max-width: 155px !important;min-width: 155px !important;" class="bordered" title="'.$diff['value'].'">';
                  if ( $diff == 0 ){
                    echo $data[$colName];
                  }else{
                    $percent = ($diff['value'] > 0 && round($diff['value']) != 0 ) ? '+' . round($diff['value']): round($diff['value']);
                    $percent = ($percent == -0) ? abs($percent) : $percent;
                    if ( $percent != 0 && abs($percent) != 100 ){
                      echo $data[$colName] . ' <span class="">('.$percent.'%)</span>';
                    }else{
                      echo $data[$colName];
                    }
                  }
                  echo '</td>';
                }
                if ( $diff != NULL ){
                  if ( $date == $last_date ){
                    $columnSpace[$i] = $colName;
                    echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
                  }
                }
              }else{
                if ( $colName == 'is_fbo' && $data[$colName] == 0 ){
                  $class = 'no-data';
                  $total_dec[$i] += 1;
                }elseif ( $colName == 'is_fbo' && $data[$colName] !== 0 ){
                  $class = '';
                  $total_inc[$i] += 1;
                }
                elseif ( $colName == 'c_spp' && $data[$colName] == 0 ){
                  $class = 'no-comps';
                }
                else{
                  $class = '';
                }
                echo "<td class='bordered ".$class."' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'>";
                echo $data[$colName];
                echo '</td>';

                if ( $date == $last_date ){
                  $columnSpace[$i] = $colName;
                  echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
                }
              }
            }else{
              echo '<td class="bordered sticked-left" style="max-width: 165px !important;min-width: 165px !important;">';
              $link = 'https://www.wildberries.ru/catalog/'.$data['nmid'].'/detail.aspx';
              echo '<a href="'.$link.'">' . $data[$colName] . '</a>';
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

<div class="table-total">
  <?
  // echo '<pre>';
  // var_dump( $columnSpace );
  // echo '</pre>';
  ?>
 <table class="table table-striped table-summary" style="">
   <tbody>
     <tr>
       <td class="bordered" style="max-width: 165px !important;min-width: 165px !important;"><div style="width:145.5">Увеличилось</div></td>
       <?
       foreach ( $total_inc as $key => $pos ){
         if ($key == 0) continue;
         if ( $pos == 0 && $total_dec[$key] == 0){
           if ( $columnSpace[$key] && $key != array_key_last($total_inc) ){
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
             echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'><span class='row-header'></span></td>";
           }else{
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
           }
         }else{
           if ( $columnSpace[$key] && $key != array_key_last($total_inc) ){
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'>{$pos}</td>";
             echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'><span class='row-header'></span></td>";
           }else{
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'>{$pos}</td>";
           }
         }
       }
       ?>
     </tr>
     <tr>
       <td class="bordered" style="max-width: 165px !important;min-width: 165px !important;"><div style="width:145.5px;">Уменьшилось</div></td>
       <?
       foreach ( $total_dec as $key => $pos ){
         if ($key == 0) continue;
         if ( $pos == 0 && $total_dec[$key] == 0){
           if ( $columnSpace[$key] && $key != array_key_last($total_inc) ){
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
             echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'><span class='row-header'></span></td>";
           }else{
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
           }
         }else{
           if ( $columnSpace[$key] && $key != array_key_last($total_inc) ){
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'>{$pos}</td>";
             echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'><span class='row-header'></span></td>";
           }else{
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'>{$pos}</td>";
           }
         }
       }
       ?>
     </tr>
   </tbody>
 </table>

</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
