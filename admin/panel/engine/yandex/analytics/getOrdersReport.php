<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}
require( __DIR__.'/../lib/bootstrap.php' );

class OrdersReport
{
  private ApiManager $api;
  private DataProvider $data;
  private Updater $updater;
  private ConfigProviderInterface $config;

  private string $cabinet;

  public function __construct( string $cabinet )
  {
    $this->cabinet = $cabinet;
    $this->init();
  }

  public function run():void
  {
    // $this->getReportData();
    $rows = $this->parseFile();

    $items = $this->buildArray( $rows );
    $this->saveInDatabase( $items );
  }

  private function getReportData():void
  {
    $data = [
      'businessId' => $this->data->settings()->getAuthData( $this->cabinet )['businessId'],
      'dateFrom' => date('Y-m-d', strtotime('- 90 days')),
      'dateTo' => date('Y-m-d')
    ];
    $response = $this->api->generateUnitedOrdersReport(
      data: $data,
      query: ['format' => 'JSON'],
    );
    if ( $response->getHttpCode() != 200 ){
      var_dump( $response->getData()->decode() );
      throw new AnalyticsReportException("Cannot put request on report generation");
    }
    $reportId = $response->getData()->decode()['result']['reportId'];
    $counter = 0;

    while ( true ) {
      if ( $counter >= 25 ) throw new AnalyticsReportException("Limit of attempts reached. Report {$reportId}");

      $response = $this->api->getReportInfo( $reportId );
      $data = $response->getData()->decode();

      if ( $data['result']['status'] == 'FAILED' ){
        var_dump( $data );
        throw new AnalyticsReportException("Report generation failed");
      }

      if ( $data['result']['status'] == 'DONE' ){
        $file = $data['result']['file'];
        break;
      }

      sleep( 3 );
      $counter++;
    }

    if ( empty($file) ) throw new AnalyticsReportException("Cannot get report due to undefined reason");

    $this->saveReport( $file );
  }

  private function saveReport( string $url ):void
  {
    $file = file_get_contents( $url );
    file_put_contents( Config::instance()->getReportPath('ordersReport'), $file );
  }

  private function parseFile():array
  {
    $zip = new ZipArchive();
    if ( $zip->open(Config::instance()->getReportPath('ordersReport')) !== true ) throw new AnalyticsReportException("Cannot open zip file");

    $json = $zip->getFromName( "orders_and_offers_transactions.json" );

    $data  = json_decode( $json, true );

    return $data['rows'];
  }

  private function buildArray( array $rows ):array
  {
    $ms = $this->getCostFromMS();
    $result = [];

    foreach ( $rows as $row ){
      $model = end( explode(" ", $row['offerName']) );
      $group = $this->mapStatus( $row['offerStatus'] );

      if ( !$group ) continue;

      if ( isset($result[ $row['shopSku'] ]) ){
        $result[ $row['shopSku'] ][ $group ] += $row['transferredForDelivery'];
        continue;
      }

      $result[ $row['shopSku'] ] = [
        'model' => $model,
        'stock' => 0,
        'to_client' => 0,
        'from_client' => 0,
        'utilization' => 0,
        'cost' => $ms[$model] ?? 0,
        'date' => date('Y-m-d'),
      ];

      $result[ $row['shopSku'] ][ $group ] += $row['transferredForDelivery'];
    }

    return array_values( $result );
  }

  private function saveInDatabase( array $items ):void
  {
    $this->updater->insertSome(into: 'yandex_stock_analytics', values: $items);
  }

  private function getCostFromMS():array
  {
    $main = \Bitrix\Main\Application::getConnection();
    $rows = $main->query("SELECT * FROM current_cost_ms");
    $result = [];

    while( $row = $rows->fetch() ){
      $result[ $row['model'] ] = $row['cost'];
    }

    return $result;
  }

  private function mapStatus( string $status ):?string
  {
    $statusGroups = [
      "Отгружен" => 'to_client',
      "Возврат готов к передаче вам" => "from_client",
      "Возврат отправлен" => "from_client",
      "Возврат принят" => "from_client",
      "Невыкуп готов к передаче вам" => "from_client",
      "Невыкуп отправлен" => "from_client",
      "Невыкуп принят" => "from_client",
      "Невыкуп на реализации" => "utilization",
      "Возврат на реализации" => "utilization",
      "Невыкуп подготовлен к утилизации" => "utilization",
    ];

    return $statusGroups[ $status ];
  }

  private function init():void
  {
    $base = new ConfigProviderBase;
    $loader = new ConfigLoader( $base );
    $config = $loader->loadModuleConfig( 'analytics' );
    Config::init( $config );
    $this->config = Config::instance();

    $repositoryLoader = new RepositoryLoader(
      panel: new DBPanel,
      main: \Bitrix\Main\Application::getConnection(),
    );
    $this->data = new DataProvider(
      settings: $repositoryLoader->loadRepository('settings'),
    );
    $this->api = new ApiManager(
      auth: $this->data->settings()->getAuthData( $this->cabinet )
    );
    $this->updater = new Updater(
      panel: new DBPanel,
      main: \Bitrix\Main\Application::getConnection(),
      isPanel: true,
    );
  }
}

(new OrdersReport('WR'))->run();
 ?>
