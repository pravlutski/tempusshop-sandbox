<?php
class DataProvider
{
  public function __construct(
    private ItemsRepository $items,
    private PricesRepository $prices,
    private SettingsRepository $settings
  )
  {}

  public function getItems( string|false $model = false ):array
  {
    $items = $this->items->getItems( $model );
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

  public function getFboPrices():array
  {
    return $this->prices->getFboPrices();
  }

}

 ?>
