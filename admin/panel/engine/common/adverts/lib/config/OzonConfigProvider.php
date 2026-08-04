<?php
class OzonConfigProvider extends ConfigProviderBase implements ConfigProviderInterface
{
  protected array $defaultValues = [
    'minCost' => 1,
    'maxCost' => 999999,
    'stockDays' => 0,
    'bid' => 4,
  ];

  protected array $auth = [
    'client_id' => '92352313-1771405802481@advertising.performance.ozon.ru',
    'client_secret' => 'YAV_CeGiaHYrWBd04Guab0RUlPGI3CwRXthMRap74JJJhqVL1APmjg7zavmi1sDYU2gL6nxxjy7OxmE1nA',
    'grant_type' => 'client_credentials'
  ];

  protected array $apiMethods = [
    'auth' => 'https://api-performance.ozon.ru/api/client/token',
    'list' => 'https://api-performance.ozon.ru:443/api/client/campaign',
    'products' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s/objects',
    'add' =>  'https://api-performance.ozon.ru:443/api/client/campaign/%s/products',
    'delete' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s/products/delete',
    'parameters' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s',
    'disable' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s/deactivate',
    'enable' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s/activate',
    'create' => 'https://api-performance.ozon.ru:443/api/client/campaign/cpc/v2/product'
  ];

  protected string $dataProviderKey = 'sku';
  protected string $platform = 'ozon';
  protected string $authCachePath = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/adverts/cache/auth.json";
  protected int $authLifespan = 1500;

  protected array $limits = [
    'advertItems' => 500,
  ];

  public function getAuthData():array
  {
    return $this->auth;
  }

  public function getAuthCachePath():string
  {
    return $this->authCachePath;
  }

  public function getAuthLifespan():int
  {
    return $this->authLifespan;
  }
}
 ?>
