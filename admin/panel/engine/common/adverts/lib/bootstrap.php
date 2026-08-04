<?php
require("config/ConfigProvider.php");

require("base/ApiManagerBase.php");
require("base/AdvertServiceBase.php");
require("base/ConfigProviderBase.php");

require("data/DataProvider.php");

require("interface/ApiManagerInterface.php");
require("interface/AdvertServiceInterface.php");
require("interface/ConfigProviderInterface.php");

require("loader/Loader.php");

require("config/OzonConfigProvider.php");
require("config/WBConfigProvider.php");

require("facade/Config.php");

require("service/DistributeService.php");
require("service/CommunicationService.php");
require("service/TechAnalyticsService.php");
require("service/MSStockService.php");

require("service/ozon/OzonAdvertService.php");
require("service/ozon/OzonProductsProvider.php");

require("service/wb/WBAdvertService.php");
require("service/wb/WBProductsProvider.php");
require("service/wb/WBFinanceService.php");

require("api/OzonApiManager.php");
require("api/WBApiManager.php");
 ?>
