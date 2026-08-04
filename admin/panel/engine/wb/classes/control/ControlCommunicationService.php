<?php
class ControlCommunicationService
{
  private static string $cabinet;
  private static DBPanel $panel;
  private static string $module;

  private static string $root = "/var/www/bitrix/data/www/tempusshop.ru";
  private static string $path = "/admin/panel/engine/wb/logs/reportStock/%s/";
  private static string $filename = "control_errors.json";


  public static function init( string $cabinet, DBPanel $panel, string $module ):void
  {
    self::$cabinet = $cabinet;
    self::$panel = $panel;
    self::$module = $module;
  }

  public static function updateStatus( $text, $perc, $status = false, $start = false, $end = false ):void
  {
    $tmp = [
      'status' => $status,
      'status_text' => $text,
      'percent' => $perc,
      'time_start' => $start,
      'time_end' => $end,
    ];

    $where[] = [
      'column' => 'code',
      'operator' => '=',
      'value' => self::$module,
    ];

    $add = array_filter(
      array: $tmp,
      callback: fn($val) => ($val !== false)
    );

    self::$panel->update('wb_agents', $add, $where );
  }

  public static function save( array $result ):void
  {
    $resultPath = self::$root . self::$path . self::$filename;
    file_put_contents(
      filename: sprintf( $resultPath, self::$cabinet ),
      data: json_encode( $result )
    );
  }
}
 ?>
