<?php
class AnalyticsConfigProvider extends ConfigProviderBase
{
  private array $paths = [
    'priceReport' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/yandex/files/priceReport.zip',
    'ordersReport' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/yandex/files/ordersReport.zip',
  ];

  public function getReportPath( string $key ):string
  {
    return $this->paths[ $key ];
  }
}
 ?>
