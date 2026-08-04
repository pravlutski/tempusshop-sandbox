<?php
class ItemsRepository extends RepositoryBase
{
  // Конструктор унаследован

  public function getItems( string|false $model ):array
  {
    $settingsTable = ConfigProvider::getSettingsTable();
    $cabinet = ConfigProvider::getCabinet();

    $query = $this->panel->select(['*'], $settingsTable )->where('cabinet', $cabinet);
    if ( $model !== false ) $query->where('model', $model);
    $rows = $query->make();

    $items = [];

    foreach ( $rows as $row ){
      // $row['goal'] = SimpleGoalCorrector::correctGlobalGoal( mb_strtoupper($row['model']), $row['goal'] );
      // $row['goal_correction'] = SimpleGoalCorrector::getModelCorrection( mb_strtoupper($row['model']) );
      $items[ mb_strtoupper($row['model']) ] = $row;
    }

    return $items;
  }

  public function setDefaultSettings( array &$items, array $settings ):void
  {
    foreach ( $items as $model => $data ){
      $items[$model]['min_profit_rub'] = $items[$model]['min_profit_rub'] ?? $settings['min_profit_rub'];
      $items[$model]['min_profit_perc'] = $items[$model]['min_profit_perc'] ?? $settings['min_profit_perc'];
      $items[$model]['step'] = $items[$model]['step'] ?? $settings['step'];
    }
  }

  public function setCurrentStatuses( array &$items ):void
  {
    $currentData = $this->getItemsDataDP();

    foreach ( $items as $model => $data ){
      $settedData = $currentData[$model] ?? false;
      if ( $settedData ){
        $perc = $currentData[$model]['perc'];

        $items[$model]['intervals']['lastRunDate'] = $currentData[$model]['date'];
        $items[$model]['installed']['step'] = ($currentData[$model]['action'] == 'down') ? ($perc * -1) : $perc;
        $items[$model]['installed']['price'] = $currentData[$model]['price'];
        $items[$model]['installed']['startPrice'] = $currentData[$model]['startPrice'];
        $items[$model]['installed']['action'] = $currentData[$model]['action'];
        continue;
      }
      $items[$model]['intervals']['lastRunDate'] = false;
    }
  }

  public function setCheckIntervals( array &$items ):void
  {

    foreach ( $items as $model => $item ){
      $lastRunDate = $item['intervals']['lastRunDate'] ?? false;
      $gap = CalculationService::calculateTimeGap( $item['goal'] );

      if ( !$lastRunDate ){
        $lastRunDate = date( 'Y-m-d H:00:00', strtotime("- {$gap} hour") );
        $nextRunDate = date( 'Y-m-d H:00:00' );
      }else{
        $nextRunDate = date( "Y-m-d H:00:00", strtotime("{$lastRunDate} + {$gap} hour") );
      }

      $items[$model]['intervals'] = [
        'lastRunDate' => $lastRunDate,
        'nextRunDate' => $nextRunDate,
      ];
    }
  }

  private function getItemsDataDP():array
  {
    $result = [];
    $rows = $this->panel->select(['*'], ConfigProvider::getFinalPriceTable() )->where('cabinet', ConfigProvider::getCabinet())->make();

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row;
    }

    return $result;
  }

}

 ?>
