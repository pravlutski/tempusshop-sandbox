<?php
class DataProvider
{
  private ItemsRepository $items;
  private PricesRepository $prices;
  private SettingsRepository $settings;

  public function __construct( ItemsRepository $items, PricesRepository $prices, SettingsRepository $settings )
  {
    $this->items = $items;
    $this->prices = $prices;
    $this->settings = $settings;
  }

  public function getItems():array
  {
    $items = $this->items->getItems();
    $settings = $this->settings->getDefaults();

    $this->prices->getStartPrices( items: $items );
    $this->prices->getCosts( items: $items );

    $this->items->setCurrentStatuses( items: $items );
    $this->items->setDefaultSettings(
      items: $items,
      settings: $settings
    );

    $this->items->setCheckIntervals( items: $items );

    return $items;
  }

  public function getCoeffientsSettings():array
  {
    return $this->settings->getCoeffients();
  }

  public function getDefaultSettings():array
  {
    return $this->settings->getDefaults();
  }

}

 ?>
