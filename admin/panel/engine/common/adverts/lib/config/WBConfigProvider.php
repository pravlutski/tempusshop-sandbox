<?php
class WBConfigProvider extends ConfigProviderBase implements ConfigProviderInterface
{
  protected array $defaultValues = [
    'minCost' => 1,
    'maxCost' => 999999,
    'stockDays' => 0,
    'bid' => 120,
  ];

  protected array $apiMethods = [
    'list' => 'https://advert-api.wildberries.ru/adv/v1/promotion/count',
    'products' => 'https://advert-api.wildberries.ru/api/advert/v2/adverts',
    'available' => 'https://advert-api.wildberries.ru/adv/v2/supplier/nms',
    'edit' => 'https://advert-api.wildberries.ru/adv/v0/auction/nms',
    'bids' => 'https://advert-api.wildberries.ru/api/advert/v1/bids',
    'balance' => 'https://advert-api.wildberries.ru/adv/v1/balance',
    'budget' => 'https://advert-api.wildberries.ru/adv/v1/budget',
    'deposit' => 'https://advert-api.wildberries.ru/adv/v1/budget/deposit',
    'create' => 'https://advert-api.wildberries.ru/adv/v2/seacat/save-ad',
    'disable' => 'https://advert-api.wildberries.ru/adv/v0/stop',
    'enable' => 'https://advert-api.wildberries.ru/adv/v0/start',
  ];

  protected array $budgetSettings = [
    'minBudget' => 300,
    'refill' => 1000,
    'type' => 1,
  ];

  protected array $limits = [
    'advertItems' => 50,
    'addItems' => 20,
    'updateBids' => 50,
  ];

  protected array $activeStatuses = [
    4 => true,
    9 => true
  ];

  protected string $dataProviderKey = 'nmid';
  protected int $subjectId = 60;
  protected string $platform = 'wb';

  public function getSubjectId():int
  {
    return $this->subjectId;
  }

  public function getBudgetSettings( string $key ):int
  {
    return $this->budgetSettings[ $key ];
  }

  public function getActiveStatuses():array
  {
    return $this->activeStatuses;
  }
}
 ?>
