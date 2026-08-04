<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::includeModule('panel_manager');
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
$tableName = "ozon_top_analytics";
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

// Делаем выборку из таблицы с конкурентами для получения зеленой цены
$sellerInfo = [];
$productLinks = [];
$rowsC = $dbPanel->select(['*'], "ozon_sku_dict_IP")->make();
foreach( $rowsC as $row ){
  $productLinks[ $row['model'] ] = "https://www.ozon.ru/product/" . $row['sku'];
  // $sellerInfo[ $row['date'] ][ $row['model'] ] = 0;
}

// Делаем выборку из основной таблицы, чтобы сформировать вспомогательный массив для расчетов
$result = $dbPanel->query( $strSql );
$rows = $dbPanel->fetchAll( $result );
if ( count( $rows ) <= 0 ) die("Нет данных в таблице для выбранного периода");
foreach ( $rows as $row ){
  unset( $row['id'] );
  unset( $row['median'] );
  // $row['date'] = date( 'Y.m.d', strtotime($row['date']) );
  $arCalc[$row['model']][$row['date']] = [
    'model' => $model,
    'our' => $row['our'],
    'sell' => $row['sell'],
    'g_price' => $sellerInfo[ $row['date'] ][ $row['model'] ] ?? 0,
    'spp' => $row['spp'],
    // 'g_spp' => $row['g_spp'], // Соинвест от зеленой цены
    'is_fbo' => $row['is_fbo'],
    'sale_name' => $row['sale_name'],
    'sale_price' => $row['sale_price'],
    'orders_count' => $row['orders_count'],
    'date' => $row['date'],
  ];
}
// Формируем массив с датами в пределах выбранного периода или периода по умолчанию
$dateFrom = isset($_POST['dateFrom']) ? $_POST['dateFrom'] : date('Y-m-d', strtotime("- 4 day"));
$dateTo = isset($_POST['dateTo']) ? $_POST['dateTo'] : end($rows)['date'];
if ( empty($dateTo) ) $dateTo = date('Y-m-d');
if ( empty($dateFrom) || $dateFrom == $dateTo ) $dateFrom = date('Y-m-d', strtotime(" - 4 day"));
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
          'model' => $model,
          'our' => 0,
          'sell' => 0,
          'g_price' => 0,
          'spp' => 0,
          'is_fbo' => 'N',
          'sale_name' => 'Не участвует',
          'sale_price' => 0,
          'orders_count' => 0,
          'date' => $cd,
        ];
      }
    }
}
// Извлекаем топы, для сортировки
$strSql = "SELECT * FROM ozon_stock_fbo_stat";
$result = $DB->Query( $strSql, false, $err_mess.__LINE__ );
$stockFbo = [];
while( $row = $result->Fetch() ){
  $stockFbo[ $row['date'] ][ $row['model'] ] = $row['stock'];
}

$tableData = [];

// Массив с именанми колонк для отображения
$header = [
  'model' => ' ',
  'is_fbo' => 'ФБО',
  'our' => 'Цена продавца',
  'g_price' => 'Зелёная цена',
  'sell' => 'Цена дял покупателя ',
  'sale_name' => 'В акции',
  'sale_price' => 'Цена для входа Х4',
  'spp' => 'Соинвест',
  'g_spp' => 'Соинвест зеленый',
  'median' => '',
  'orders_count' => 'Количество продаж',
];

// Массив с колонками, для которых будут считаться и выводиться изменения
$arDiff = ['sell', 'our', 'sale_price', 'g_price'];
// Исключаем из массива поля, которые не были получены из формы
foreach ( $arDiff as $key => $field ){
  if ( !in_array($field, $allowedFields) ){
    unset( $arDiff[$key] );
  }
}
// Считаем количество колонок для дополнительной таблицы с итогами ( я не понимаю, почему это работает )
$days = ( strtotime($dateTo) - strtotime($dateFrom) ) / 3600 / 24;
$columnsCount = ($days + 1) * (count($allowedFields) - 1) + 1;
// $columnsCount = ($days + 1) * (count($allowedFields) - 1) + ( $days * count($arDiff) );
if ( $dateTo == date('Y-m-d') ){
  $columnsCount = $columnsCount - 1;
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

function stripText( $text ){
  if ( $text == 'Не участвует'){
    return 'Нет';
  }
  if( preg_match( '/Бустинг х1,5/', $text) ){
    return 'Буст х1,5';
  }
  if ( strpos($text, '.') ){
    $retu = explode('.', $text)[0];
    if ( mb_strlen($retu) <= 10 ){
      return $retu;
    }
  }
  return mb_substr($text, 0, 6) . '...';
}

function getColorSppCell( string $model, string $date, array $arData ):string
{
  $result = '';
    if ( empty($arData[$model]) ) return $result;
    if ( empty($arData[$model][$date]) ) return $result;

    $modelData = $arData[$model][$date];
    $uniqueValues = array_unique( $modelData );
    if ( count($uniqueValues) > 1 ) $result = 'warning-cell ';

    return $result;
}

function getSppDetail( object $db ):array
{
  $rows = $db->select( ['*'], 'ozon_spp_analytics_by_hour' )->make();
  $result = [];

  foreach ( $rows as $row ){
    if ( empty($row['our_price']) || empty($row['black_price']) ){
      continue;
    }
    $spp = round( ($row['our_price'] - $row['black_price']) / $row['our_price'] * 100 );
    $result[ $row['model'] ][ $row['date'] ][] = $spp;
  }

  return $result;
}
$arSppData = getSppDetail( $dbPanel );

$keys_raw = array_keys( $rows[0] );
$keys = [];
foreach ( $keys_raw as $k ){
  switch ( $k ){
    case 'sell':
      $keys[] = 'g_price';
      break;
    case 'spp':
      $keys[] = 'g_spp';
      break;
  }
  $keys[] = $k;
}
$arDateCheck = [1];

try{
  foreach ( $rows as $key => $row ){
    unset($row['id']);
    unset( $row['median'] );
    foreach ( $keys as $k){
      calculateDifference($arCalc, $row['model'], $row['date'], 'sell', $arDateCheck);
      $g_price =  $sellerInfo[ $row['date'] ][ $row['model'] ] ?? 0;
      $spp = round( ($row['our'] - $row['sell']) / $row['our'] * 100 );
      $tableDataTmp[$row['model']][$k][$row['date']] = [
        'model' => $row['model'],
        // 'median' => $row['median'],
        'our' => $row['our'],
        'g_price' => $g_price,
        'sell' => $row['sell'],
        'spp' => ($spp == 100) ? "<span class='no-comps'>Нет данных</span>" : $spp,
        'g_spp' => !empty($g_price) ? round( ($row['our'] - $g_price) / $row['our'] * 100 ) : 0,
        'is_fbo' => $stockFbo[$row['date']][$row['model']] ?? 0,
        'sale_name' => $row['sale_name'],
        'sale_price' => $row['sale_price'],
        'orders_count' => $row['orders_count'],
        'date' => $row['date'],
      ];
      // $headerData[$k][$row['date']] = $row;
      // unset($tableData[$row['model']][$k][$row['date']]['sell_diff']);
    }
    unset($row);
  }
}catch ( Throwable $e ){
  var_dump( $row['model'] );
  var_dump( $row['date'] );
  var_dump( $row['our'] );
  var_dump( $row['sell'] );
}

foreach ( $tableDataTmp as $model => $arModel ){
  foreach ( $arModel as $colName => $daRow ){
    foreach ( $arPeriod as $cd ){
      if ( !isset($daRow[$cd]) ){
        $tableDataTmp1[$model][$colName][$cd] = [
          'model' => $model,
          // 'median' => $row['median'],
          'our' => 0,
          'g_price' => 0,
          'sell' => 0,
          'spp' => "<span class='no-comps'>Нет данных</span>",
          'g_spp' => '0',
          'is_fbo' => $stockFbo[$cd][$model] ?? 0,
          'sale_name' => 'Не участвует',
          'sale_price' => 0,
          'orders_count' => 0,
          'date' => $cd,
        ];
        $headerData[$colName][$cd] = ['date' => $cd];
      }else{
        $tableDataTmp1[$model][$colName][$cd] = $tableDataTmp[$model][$colName][$cd];
        $headerData[$colName][$cd] = ['date' => $cd];
      }
    }
  }
}
// echo '<pre>';
// var_dump( $headerData );
// echo '</pre>';
// die;
$result = $dbPanel->query("SELECT * FROM ozon_top_models");
$rows = $dbPanel->fetchAll($result);
foreach ($rows as $row) {
  $tops[] = $row['model'];
}
$tableData = [];
foreach ( $tops as $model ){
  if ( isset($tableDataTmp1[$model]) ){
    $tableData[$model] = $tableDataTmp1[$model];
  }
}
var_dump(count($tableData));
$summaryCols = ['our', 'sell' , 'g_price', 'is_fbo', 'sale_name', 'sale_price'];
$last_date = array_key_last( $headerData['model'] );

$arArticle = [];
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
              if ( $arDateCheck[$date] ){
                if ( $date == $last_date){
                  // echo '<th class="bordered">';
                }else{
                  // echo '<th class="">';
                }
                // echo 'Изменения ' . date('Y.m.d',strtotime($date));
                // echo '</th>';
              }
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
       'spp' => 'Нет ФБО:',
       'is_fbo' => 'Не в  акции:',
       'sale_name' => 'Уменьшилось:'
     ];
     $rowHeadersPositive = [
       'spp' => 'Есть ФБО:',
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
        $subClass = '';
        foreach ( $dbRow as $date => $data ){
            if ( $i != 0 ) unset($data['model']);
            unset($data['date']);
            if ( $data[$colName] === NULL ) continue;
            if ( $colName != 'model'  ){
              if ( $colName == 'orders_count' && $date == date('Y-m-d') ) continue;
              if ( in_array($colName, $arDiff) ){

                $diff = calculateDifference($arCalc, $model, $date, $colName);

                if ( $diff != NULL ){
                  if ( round($diff['value']) >= 1 ){
                    $total_inc[$i] += 1;
                  }elseif( round($diff['value']) <= -1 && $diff['value'] != 0 ){
                    $total_dec[$i] += 1;
                  }
                  $k++;

                  if ( $date == $last_date ){
                    $columnSpace[$i] = $colName;
                  }
                }
                if ( $data[$colName] == 0 ){
                  echo '<td style="max-width: 155px !important;min-width: 155px !important;" class="no-comps">';
                  echo 'Нет данных';
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
                      echo $data[$colName] == 0 && $colName == 'sale_price' ? '<span class ="no-comps">Не в акции</span>' : $data[$colName];
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
                if ( $colName == 'sale_name' ){
                  if (  $data[$colName] == 'Не участвует' ){
                    $total_dec[$i] += 1;
                  }else{
                    $total_inc[$i] += 1;
                  }

                  $title = ($colName == 'sale_name') ? 'title="'.$data[$colName].'"' : '';
                  $class = ($colName == 'sale_name' && $data[$colName] == 'Не участвует') ? 'no-data' : 'has-data';

                  echo ($date == $last_date) ? '<td class="bordered '.$class.'" '.$title.' style="max-width: 155px !important;min-width: 155px !important;">' : '<td '.$title.' class="'.$class.'" style="max-width: 155px !important;min-width: 155px !important;">';
                }else{
                  if ( $colName == 'is_fbo' && $data[$colName] == 0 ){
                    $class = 'no-data';
                    $total_dec[$i] += 1;
                  }elseif ( $colName == 'is_fbo' && $data[$colName] !== 0 ){
                    $class = '';
                    $total_inc[$i] += 1;
                  }
                  elseif ( $colName == 'g_spp' && $data[$colName] == 0 ){
                    $class = 'no-comps';
                  }
									elseif ( $colName == 'spp' ){
                    $class = 'spp-btn';
										$data_attrs = "data-model='{$model}' data-date='$date'";
										$subClass = getColorSppCell($model, $date, $arSppData);
										$class .= ' ' . $subClass;
                  }
                  else{
                    $class = '';
                  }
                  echo "<td class='bordered ".$class.$subClass."' {$data_attrs} style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'>";
                }
                if ( $colName == 'g_price' || $colName == 'g_spp' ){
                  echo $data[$colName] == 0 ? 'Н/Д' : $data[$colName];
                }
                elseif ( $colName == 'sale_name' ){
                  echo stripText($data[$colName]);
                }
                else{
                  echo $data[$colName];
                }
                echo '</td>';

                if ( $date == $last_date ){
                  $columnSpace[$i] = $colName;
                  echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
                }
              }
            }else{
              echo '<td class="bordered sticked-left" style="max-width: 165px !important;min-width: 165px !important;">';
              if ( isset($productLinks[$data[$colName]]) ){
                echo '<a href="'.$productLinks[$data[$colName]].'" target="_blank">' . $data[$colName] . '</a>';
              }else{
                echo $data[$colName];
              }
				$arArticle[$data[$colName]] = $data[$colName];
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
  // var_dump( count($total_inc) );
  // var_dump( count($total_dec) );
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
             echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'><span class='row-header'>{$rowHeadersPositive[$columnSpace[$key]]}</span></td>";
           }else{
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
           }
         }else{
           if ( $columnSpace[$key] && $key != array_key_last($total_inc) ){
             echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'>{$pos}</td>";
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'><span class='row-header'>{$rowHeadersPositive[$columnSpace[$key]]}</span></td>";
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
             echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'><span class='row-header'>{$rowHeadersNegative[$columnSpace[$key]]}</span></td>";
           }else{
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'></td>";
           }
         }else{
           if ( $columnSpace[$key] && $key != array_key_last($total_inc) ){
             echo "<td class='bordered' style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'>{$pos}</td>";
             echo "<td style='padding:0.5rem;max-width: 155px !important;min-width: 155px !important;'><span class='row-header'>{$rowHeadersNegative[$columnSpace[$key]]}</span></td>";
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
