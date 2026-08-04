<?php
class SimpleGoalCorrector
{
  private static ?DBPanel $panel = null;
  private static array $data = [];
  private static array $raw = [];

  private static array $settings = [];

  public static function init( DBPanel $panel ):void
  {
    self::$panel = $panel;

    self::getData();
    self::getSettings();
  }

  public static function correctGlobalGoal( string $model, int $goal ):int
  {
    $result = $goal + (self::$data[$model] ?? 0);

    if ( $result < 1 ) return 1;

    return $result;
  }

  public static function calculateCorrection( string $model, int $step ):void
  {
    if ( $step == 0 ){
      self::$data[$model] = 0;
      return;
    }

    $correctiveValue = ($step < 0) ? self::$settings['decrease_step'] : self::$settings['increase_step'];
    $goalModificator = ( ($step < 0) ? self::$settings['decrease_value'] : self::$settings['increase_value'] ) ?? 0;

    self::$data[$model] = intdiv( $step, $correctiveValue ) * $goalModificator;
  }

  public static function update( $model ):void
  {
    $table = ConfigProvider::getGoalCorrectionsTable();
    if ( empty(self::$raw[$model]) ){
      $insert = ['model' => $model, 'correction' => self::$data[$model], 'date' => date('Y-m-d G:i:s') ];

      self::$panel->insert( $table, [$insert] );

      return;
    }

    $where = [ 'column' => 'model', 'operator' => '=', 'value' => $model ];
    $update = [ 'correction' => (self::$data[$model] ?? 0), 'date' => date('Y-m-d G:i:s') ];

    self::$panel->update( $table, $update, [$where] );
  }

  public static function getModelCorrection( string $model ):int
  {
    return self::$data[$model] ?? 0;
  }

  private static function getData():void
  {
    $table = ConfigProvider::getGoalCorrectionsTable();
    $rows = self::$panel->select(['*'], $table)->make();

    self::$raw = array_column($rows, 'correction', 'model');
    self::$data = self::$raw;
  }

  private static function getSettings():void
  {
    $table = ConfigProvider::getDefaultSettingsTable();
    $rows = self::$panel->select(['increase_step', 'decrease_step', 'decrease_value', 'increase_value'], $table)->make();

    self::$settings = $rows[0];
  }
}
 ?>
