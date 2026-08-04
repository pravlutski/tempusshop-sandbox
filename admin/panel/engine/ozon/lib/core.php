<?php
// Интерфейсы

// Исключения
require('exceptions/InvalidJsonException.php');
// Конфиг
require('config/ConfigProvider.php');
// ДТО
require('dto/Response.php');
require('dto/ResponseData.php');
// Базовые классы
require('base/ApiBase.php');
// Репозитории

// Менеджеры
require('api/ApiManager.php');
// Провайдеры
// Сервисы
require('services/CommunicationService.php');
//// stocks
  require('services/stocks/StocksApiManager.php');
  require('services/stocks/StocksDataProvider.php');
  require('services/stocks/StocksDataProvider2.php');
 ?>
