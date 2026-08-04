<?php
class Calculator
{
  public static function calculateMargin( float $price, int $com, float $cost ):float
  {
    // Маржинальность, %
    $margin = ( $price * (1 - $com / 100) - $cost ) / $cost;
    return round( $margin * 100, 2 );
  }

  public static function calculateProfit( float $price, int $com, float $cost  ):float
  {
    // Маржа, руб.
    $profit = $price * (1 - $com / 100) - $cost;
    return round( $profit, 2 );
  }

  public static function calculateDiscount( float $maxActionPrice, float $itemPrice ):float
  {
    // Скидка, которую мы даем, чтобы достичь максимальной цены вхождения
    $discount = ( 1 - $maxActionPrice / $itemPrice ) * 100;
    return round( $discount, 2 );
  }

  public static function checkProfitConditions(
    array $settings,
    float $cost,
    float $price1,
    float|bool $price2 = false,
    int|false $maxDiscount = false
    ):array
  {
    $com = $settings['commission'];
    $status = false;

    $margin = self::calculateMargin( $price1, $com, $cost );
    $approvedMargin = ($margin >= $settings['min_margin']);

    $profit = self::calculateProfit( $price1, $com, $cost );
    $approvedProfit = ($profit >= $settings['min_profit']);

    if ( $maxDiscount !== false ){
      $discount = self::calculateDiscount( $price1, $price2 );
      $approvedDiscount = ($discount <= $maxDiscount);
    }else{
      $approvedDiscount = true;
    }

    if ( $approvedMargin && $approvedProfit && $approvedDiscount ){
      $status = true;
    }

    return [
      'margin' => $margin,
      'profit' => $profit,
      'discount' => $discount,
      'status' => $status,
    ];
  }
}
 ?>
