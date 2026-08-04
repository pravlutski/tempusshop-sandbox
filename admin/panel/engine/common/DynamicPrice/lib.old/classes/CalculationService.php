<?php
class CalculationService
{
  private static int $day = 24;
  private static int $hour = 3600;

  public static function calculateTimeGap( int $goal ):int
  {
    if  ( $goal >= self::$day ) return 1;
    return intval( round( 1 / ($goal / self::$day) ) );
  }

  public static function calculateGoal( array $item, array $coefficients, bool $isPeriod ):int
  {
    if ( $isPeriod ){
      $minInterval = strtotime( $item['intervals']['lastRunDate'] );
    }else{
      $minInterval = strtotime( date('Y-m-d 00:00:00') ); // Полночь
    }

    return self::calculateGoalForPeriod( $item['goal'], $minInterval, $coefficients );
  }

  private static function calculateGoalForPeriod( int $goal, int $minInterval, array $coefficients ):int
  {
    $now = time();
    $secondsDiff = $now - $minInterval;
    $hoursDiff = round( $secondsDiff / self::$hour );

    $result = 0;
    for ( $i = 0; $i <= $hoursDiff; $i++ ){
      $hour = date( 'G', $minInterval + $i * self::$hour );
      $result += $goal * $coefficients[ $hour ];
    }

    return round($result);
  }

  public static function calculateStepDirection( int $orders, int $goal ):int // return 1 | -1
  {
    if ( $orders - $goal == 0 ) return 0;
    return ($orders - $goal) / abs($orders - $goal);
  }

  public static function calculateProfit( float $price, float $cost, int $com ):float
  {
    return $price * (1 - $com / 100) - $cost;
  }

  public static function calculateMargin( float $price, float $cost, int $com ):float
  {
    return ($price * (1 - $com / 100) - $cost) / $cost;
  }

  public static function calculatePriceDiffernce( float $installed, float $actual ):float
  {
    $diff = $installed / $actual;
    return round( ($diff > 1) ? ($diff - 1) :  1 - $diff, 2);
  }
}
 ?>
