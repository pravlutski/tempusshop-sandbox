<?php
class StepCalculator
{
  public function __construct()
  {

  }

  public function calculateStep():int
  {
    $step = "formula";

    if ( !$checkProfitConditions ){

      while ( $profit < $minProfit ){
        $step += $defaultStep;
        if ( $checkProfitConditions ) break
      }

    }

    return $step;
  }



  private static function someAction():void
  {

  }
}

 ?>
