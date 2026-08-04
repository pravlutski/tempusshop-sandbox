<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class AnalyticsTableDriver
{
  private DBPanel $panel;

  private array $fields = [
    'our_price' => false,
    'sell_price' => false,
    'spp' => false,
  ];

  private array $names = [
    'our_price' => 'Цена продавца',
    'sell_price' => 'Цена для покупателя',
    'spp' => 'Соинвест'
  ];

  private array $calculatableFields = [
    'our_price' => true,
    'sell_price' => true,
  ];

  private array $colors = [
    'lighter' => ['min' => 0, 'max' => 3],
    'light' => ['min' => 3, 'max' => 8],
    'medium' => ['min' => 8, 'max' => 13],
    'hard' => ['min' => 13, 'max' => 100],
  ];

  private array $oppositeColoring = [
    'our_price' => true,
    'sell_price' => true,
  ];

  public function __construct(
    private string $dateFrom,
    private string $dateTo
  )
  {
    $this->panel = new DBPanel;
  }

  public function fun():void
  {
    $items = $this->getItems();

    $this->renderTable( $items );
  }

  private function getItems():array
  {
    $rows = $this->panel->select(['*'], 'yandex_top_analytics')->where('date', $this->dateFrom, '>=')->where('date', $this->dateTo, '<=')->asc('date')->make();
    $result = [];

    foreach( $rows as $row ){
      $result[ $row['model'] ][ $row['date'] ] = $this->enrichWithCalculatedFields( $row );
    }

    $result = $this->fillGaps( $result );

    return $result;
  }

  private function enrichWithCalculatedFields( array $row ):array
  {
    $row['spp'] = $this->calculateSpp( $row['our_price'], $row['sell_price'] );

    return $this->filterFields( $row );
  }

  private function calculateSpp( float $our, float $sell ):int|bool
  {
    if ( $our == 0 || $sell == 0 ) return false;
    return ($our - $sell) / $our * 100;
  }

  private function filterFields( array $row ):array
  {
    unset( $row['model'], $row['date'], $row['id'], $row['offerId'] );

    return $row;
  }

  private function fillGaps( array $rows ):array
  {
    $diff = strtotime($this->dateTo) - strtotime($this->dateFrom);
    $diff = $diff / 3600 / 24;
    $result = [];

    foreach( $rows as $model => $dg ){
      for( $i = 0; $i <= $diff; $i++ ){
        $date = date('Y-m-d', strtotime( $this->dateFrom . "+ {$i} days" ));
        $data = $rows[$model][$date];
        $result[$model][$date] = $data ?: $this->fields;
      }
    }

    return $result;
  }

  private function renderTable( array $items ):void
  {
    echo sprintf(
      "<table><thead>%s</thead><tbody>%s</tbody></table>",
      $this->renderHeader( $items ),
      $this->renderBody( $items ),
    );
  }

  private function renderHeader( array $items ):string
  {
    $dates = array_keys( reset($items) );
    $headers = [
      "<th class=\"header-cell\"></th>"
    ];
    foreach ( array_keys($this->fields) as $field ){
      foreach ( $dates as $date ){
        $headers[] = "<th class=\"header-cell\">{$this->names[$field]} {$date}</th>";
      }
      $headers[] = "<th class=\"group-divider header-cell\"></th>";
    }

    return implode('', $headers);
  }

  private function renderBody( array $items ):string
  {
    $tr = "<tr class=\"%s\">%s</tr>";
    $td = "<td class=\"%s\">%s</td>";
    $noData = "<span class=\"no-data-cell\">Нет данных</span>";
    $rows = [];

    foreach ( $items as $model => $dateGroup ){
      $cells = [];
      $cells[] = sprintf( $td, 'model-sticky', $model );

      foreach ( array_keys( $this->fields ) as $field ){

        foreach ( $dateGroup as $date => $fields ){

          $calculated = $this->calculateDifference(
            dates: $dateGroup,
            date: $date,
            field: $field,
          );
          $color = $this->colorCell( $calculated['diff'], $field );
          $value = $calculated['value'] == 0 ? $noData : $calculated['value'];

          $cells[] = sprintf( $td, $color, $value );
        }
        $cells[] = sprintf( $td, 'group-divider', '' );

      }

      $rows[] = sprintf($tr, '', implode('', $cells));
    }

    return implode( '', $rows );
  }

  private function calculateDifference( array $dates, string $date, string $field ):array
  {
    $current = $dates[$date][$field];

    if ( !$this->calculatableFields[$field] ) {
      return ['diff' => false, 'value' => $current];
    }

    $this->array_seek( $dates, $date );
    $previousDate = prev( $dates );

    if ( $previousDate === false ) {
      return ['diff' => false, 'value' => $current];
    }
    $previous = $previousDate[$field];

    if ( $previous == 0 || $current == 0 ) {
      return ['diff' => false, 'value' => $current];
    }

    $diff = round(($current - $previous) / $previous * 100);
    if ( $diff == 0 ) return ['diff' => false, 'value' => $current];
    $decoration = $diff > 0 ? "(+{$diff}%)" : "({$diff}%)";

    return [
      'diff' => $diff,
      'value' => "{$current} {$decoration}",
    ];
  }

  public function colorCell( bool|int $diff, string $field ):string
  {
    if ( $diff == 0 ) return '';
    $intensivity = '';
    $opposite = $this->oppositeColoring;

    foreach ( $this->colors as $color => $borders ){
      if ( $boders['min'] < abs($diff) && abs($diff) <= $borders['max'] ){
        $intensivity = $color;
        break;
      }
    }
    if ( empty($intensivity) ) return '';

    if ( $diff < 0 ){
      $base = $opposite[ $field ] ? 'green' : 'red';
    }else{
      $base = $opposite[ $field ] ? 'red' : 'green';
    }

    return "{$base}-{$intensivity}";
  }

  private function array_seek( array &$array, string $key ):bool
  {
    if ( !isset($array[$key]) ) return false;
    reset( $array );

    while( key($array) !== null && key($array) !== $key ){
      next( $array );
    }

    return key($array) === $key;
  }
}
$analytics = new AnalyticsTableDriver(
  dateFrom: $_POST['dateFrom'] ? $_POST['dateFrom'] : date('Y-m-d', strtotime('- 4 days')),
  dateTo: $_POST['dateTo'] ? $_POST['dateTo'] : date('Y-m-d'),
);
$analytics->fun();
 ?>
