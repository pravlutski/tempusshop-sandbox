<?php

class OrchestraCore
{
  protected array $defaults;

  public function __construct( string $marketplace, string $cabinet )
  {
    ValidationService::validateConstruct( $marketplace, $cabinet );
    ConfigProvider::init( $marketplace, $cabinet );
    $this->init();
  }

  private function init():void
  {
    CModule::IncludeModule('panel.manager');

    $dbPanel = new DBPanel;
    $dbMain = \Bitrix\Main\Application::getConnection();

    $this->dataProvider = new DataProvider(
      items: new ItemsRepository( $dbMain, $dbPanel ),
      prices: new PricesRepository( $dbMain, $dbPanel ),
      settings: new SettingsRepository( $dbMain, $dbPanel )
    );

    $this->orderProvider = Loader::getOrderProvider(
      panel: $dbPanel,
      main: $dbMain
    );

    $this->communicationService = new CommunicationService(
      db: $dbPanel
    );

    $coefficients = $this->dataProvider->getCoeffientsSettings();
    $defaultSettings = $this->dataProvider->getDefaultSettings();

    $this->processManager = new ProcessManager(
      defaults: $defaultSettings,
      coefficients: $coefficients
    );

    $this->defaults = $defaultSettings;

    SimpleGoalCorrector::init( $dbPanel );
  }
}

 ?>
