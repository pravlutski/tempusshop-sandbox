<?php

class ProcessManager
{
  private array $defaults;
  private array $coefficients;

  public function __construct( array $defaults, array $coefficients )
  {
    $this->defaults = $defaults;
    $this->coefficients = $coefficients;
  }

  public function checkItems( array $items ):array
  {
    $result = [];
    $priceChangedCounter = 0;
    foreach ( $items as $model => $item ) {
      CommunicationService::log("-----------------------------------------------");
      CommunicationService::log("Item: {$model}");
      if ( empty($item['cost']) ){
        CommunicationService::log("Item has zero cost value. Skipped");
        continue;
      }

      $updateFlag = true;
      $goal = CalculationService::calculateGoal( $item, $this->coefficients, $this->defaults['isPeriod'] );
      $checkData = $this->checkIfGoalAchieved( $item['ordersCount'], $goal );
      $direction = CalculationService::calculateStepDirection( $item['ordersCount'], $goal );


      if ( !$checkData['status']['abs'] || !$checkData['status']['deviation'] ){
        CommunicationService::log("Update requirements was not fulfilled. Item will not be updated");

        $step = $item['installed']['step'] ?? 0;
        $action = $item['installed']['action'] ?? 'none';
        $price = $item['installed']['price'] ?? $item['startPrice'];
      }else{
        $step = $item['installed']['step'] + $item['step'] * $direction;
        $action = ( $step < 0 ) ? 'down' : 'up';
        $price = $item['startPrice'] * ( 1 + $step / 100 );

        $priceChangedCounter++;
      }

      CommunicationService::log("Absolute: {$checkData['value']['abs']}. Min: {$this->defaults['tolerance']}");
      CommunicationService::log("Orders/Goal: {$item['ordersCount']}/{$goal}");
      CommunicationService::log("Deviation: {$checkData['value']['deviation']}. Min: {$this->defaults['threshold']}");

      $profit = $this->checkProfitConditions( $item, $price, $this->defaults['commission'] );

      if ( !$profit['status'] ){
        CommunicationService::log("Profit check was not passed. New status will not be installed");
        CommunicationService::log("Min. margin: {$item['min_profit_perc']}; Calc. margin: {$profit['margin']}");
        CommunicationService::log("Min. profit: {$item['min_profit_rub']}; Calc. profit: {$profit['profit']}");

        $price = $item['installed']['price'] ?? $item['startPrice'];
        $step = $item['installed']['step'] ?? 0;
        $action = $item['installed']['action'] ?? 'none';

        $profit = $this->checkProfitConditions( $item, $price, $this->defaults['commission'] );
      }

      $data = [
        'model' => $model,
        'action' => $action,
        'perc' => abs( $step ),
        'startPrice' => $item['startPrice'],
        'price' => round($price),
        'cost' => $item['cost'],
        'profit_rub' => $profit['profit'],
        'profit_perc' => $profit['margin'],
        'profit_cap' => 'N',
        'cabinet' => ConfigProvider::getCabinet(),
        'date' => date('Y-m-d G:i:s'),
      ];
      CommunicationService::log("Step will be changed from {$item['installed']['step']} to {$step}");
      CommunicationService::log("Step will be changed from {$item['installed']['price']} to {$price}");
      CommunicationService::log( $data );

      $result[] = $data;
    }
    $countItems = count( $items ?? [] );
    CommunicationService::log("Data will be updated for {$priceChangedCounter}/{$countItems} models");

    return $result;
  }

  private function checkIfGoalAchieved( int $orders, int $goal ):array
  {
    if ( $goal == 0 ) {
      $diff = ($orders > 0) ? 1 : 0;
    } else {
      $diff = $orders / $goal;
      $diff = ($diff >= 1) ? $diff - 1 : 1 - $diff;
    }

    $result = [
      'value' => [
        'abs' => abs($orders - $goal),
        'deviation' => $diff * 100
      ],
      'status' => [
        'abs' => ( abs($orders - $goal) != $this->defaults['tolerance'] ),
        'deviation' => ($diff * 100 > $this->defaults['threshold'])
      ],
    ];

    return $result;
  }

  private function checkProfitConditions( array $item, float $price, int $com ):array
  {
    $profit = CalculationService::calculateProfit( $price, $item['cost'], $com );
    $margin = CalculationService::calculateMargin( $price, $item['cost'], $com );

    $result = [
      'profit' => round( $profit, 2),
      'margin' => round( $margin  * 100, 2),
      'profit_requirement_passed' => $profit > $item['min_profit_rub'],
      'margin_requirement_passed' => $margin * 100 > $item['min_profit_perc'],
      'status' => false,
    ];

    $result['status'] = $result['profit_requirement_passed'] && $result['margin_requirement_passed'];

    return $result;
  }

  public function checkTimestamps( array $items ):array
  {
    $excluded = [];
    $rest = [];
    foreach( $items as $model => $item ){
      $nextRunDate = $item['intervals']['nextRunDate'];

      if ( strtotime( date('Y-m-d H:00:00') ) < strtotime($nextRunDate) ){
        $excluded[] = [
          'model' => $model,
          'nextRunDate' => $nextRunDate
        ];
        continue;
      }
      $rest[$model] = $item;
    }

    return [
      $excluded ?? [],
      $rest ?? [],
    ];
  }

}

?>
