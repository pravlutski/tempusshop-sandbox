<?php
class UIProcessor
{
  private const MODULE = 'ui';
  private static DBPanel $panel;
  private static \Bitrix\Main\DB\MysqliConnection $main;

  public static function init()
  {
    self::initConfig();
    self::initConnection();
  }

  private static function initConnection():void
  {
    CModule::IncludeModule('panel.manager');
    self::$panel = new DBPanel;
    self::$main = \Bitrix\Main\Application::getConnection();
  }

  private static function initConfig():void
  {
    $base = new ConfigProviderBase;
    $loader = new ConfigLoader( $base );
    $config = $loader->loadModuleConfig( self::MODULE );
    Config::init( $config );
  }

  public static function api():ApiManager
  {
    return new ApiManager(
      auth: self::data()->settings()->getAuthData("WR")
    );
  }

  public static function data():DataProvider
  {
    $loader = new RepositoryLoader( self::$main, self::$panel );
    return new DataProvider(
      items: $loader->loadRepository('items'),
      settings: $loader->loadRepository('settings'),
    );
  }

  public static function updater( bool $isPanel = true ):Updater
  {
    return new Updater(
      main: self::$main,
      panel: self::$panel,
      isPanel: $isPanel
    );
  }
}

?>
