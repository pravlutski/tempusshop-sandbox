<?php
class Config
{
  private static ConfigProviderInterface $config;

  public static function init( ConfigProviderInterface $config ):void
  {
    self::$config = $config;
  }

  public static function instance():ConfigProviderInterface
  {
    return self::$config;
  }

  public static function getLoadedConfig():string
  {
    return self::$config::class;
  }
}
 ?>
