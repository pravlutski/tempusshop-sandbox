<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Sale;
use Bitrix\Main\UserTable;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

// Проверяем инициализацию ядра Bitrix
if (!Loader::includeModule('sale') || !Loader::includeModule('main')) {
    die('Не удалось загрузить необходимые модули');
}

class StatusSender
{
    private $orderId;

    public function __construct($orderId,$status)
    {
        $this->orderId = $orderId;
        $this->status = $status;
    }

    public function sendStatus()
    {
      $orderData = [
          'id' => $this->orderId,
          'status' => $this->status
      ];

      file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/sendStatus.txt', print_r($orderData,true). PHP_EOL);

      $ch = curl_init('https://tempus.ru/local/rest/getstatus.php');
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['order_data' => json_encode($orderData)]));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $response = curl_exec($ch);

      curl_close($ch);

      if ($response) {
          $responseObj = json_decode($response, true);
          return $responseObj['new_order_id'] ?? false;
      }

    }
}
