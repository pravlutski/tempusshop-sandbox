<?php
class Loader
{
  public static function getOrderProvider( DBPanel $panel, \Bitrix\Main\DB\MysqliConnection $main ):OrderProviderInterface
  {
    $marketplace = ConfigProvider::getMarketplace();

    $classPath = ConfigProvider::getOrderProviderPath();
    $className = ConfigProvider::getOrderProviderName();

    if ( empty( $classPath ) ) throw new Exception("Class path is not set for {$marketplace}");
    if ( !file_exists(  __DIR__ . $classPath ) ) throw new Exception("Class file does not exists");
    if ( empty( $className ) ) throw new Exception("Class name is not set for {$marketplace}");

    require_once __DIR__ . $classPath;
    return new $className( $main, $panel );
  }
}
 ?>
