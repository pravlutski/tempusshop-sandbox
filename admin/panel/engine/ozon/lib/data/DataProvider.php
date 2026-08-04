<?php
class DataProvider
{
  public function __construct(
    private ?ItemsRepository $items = null,
    private ?SettingsRepository $settings = null,
    private ?PricesRepository $prices = null
  ){}

  public function getItems( array $select = [], array $filter = [], bool $useAdditionalDefaults = true ):array
  {
    if ( empty($this->items) ) throw new NoRepositoryException("Items repository was not set");

    if ( $useAdditionalDefaults ){
      $select = array_merge(
        $select,
        ConfigProvider::getDefaultSelectProperties(),
        ConfigProvider::getCabinetSpecifiedProperties()
      );

      $filter += ConfigProvider::getDefaultPropertyFilter();
    }


    $items = $this->items->getItems( $select, $filter );
    $result = [];

    return $items;
  }

  private function setBrandName( array &$items ):void
  {
    $brandDictionary = $this->items->getBrandsDictionary();
    foreach ( $items as &$item ){
      $item['BRAND_NAME'] = $brandDictionary[ $item['PROPERTY_BRAND_VALUE'] ] ?? '';
    }
  }

  private function setSectionName( array &$items ):void
  {
    $sectionDictionary = $this->items->getSectionDictionary();
    foreach ( $items as &$item ){
      $item['SECTION_NAME'] = $sectionDictionary[ $item['PROPERTY_BRAND_VALUE'] ] ?? '';
    }
  }


}
 ?>
