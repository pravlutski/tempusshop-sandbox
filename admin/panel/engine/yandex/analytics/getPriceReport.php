<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("panel_engine_yandex_analytics_getPriceReport_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
require( __DIR__.'/../lib/bootstrap.php' );

class GetPriceReport
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
    $response = $this->api->generateGoodsPricesReport(
      data: ['businessId' => $this->data->settings()->getAuthData( $this->cabinet )['businessId']],
      query: ['format' => 'JSON'],
    );
    $reportId = $response->getData()->decode()['result']['reportId'];
    $counter = 0;

    while ( true ) {
      if ( $counter >= 25 ) throw new AnalyticsReportException("Limit of attempts reached");

      $response = $this->api->getReportInfo( $reportId );
      $data = $response->getData()->decode();

      if ( $data['result']['status'] == 'FAILED' ) throw new AnalyticsReportException("Report generation failed");

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
    file_put_contents( Config::instance()->getReportPath('priceReport'), $file );
  }

  private function parseFile():array
  {
    $zip = new ZipArchive();
    if ( $zip->open(Config::instance()->getReportPath('priceReport')) !== true ) throw new AnalyticsReportException("Cannot open zip file");

    $json = $zip->getFromName( "business_prices.json" );

    $data  = json_decode( $json, true );

    // return array_slice( $data['rows'], 0, 100 );
    return $data['rows'];
  }

  private function buildArray( array $rows ):array
  {
    $ms = $this->getTopModels();
    $rows = array_filter($rows, function($item) use ($ms){
      $model = end( explode(' ', $item['offerName']) );
      return isset($ms[$model]);
    });

    return array_map( function($row){
      return [
        'offerId' => (int) $row['offerId'],
        'model' => end( explode(' ', $row['offerName']) ),
        'our_price' => (float) $row['basicPrice'],
        'sell_price' => (float) $row['onDisplay'],
        'date' => date('Y-m-d'),
      ];
    }, array_column( $rows, null, 'offerId' ) );
  }

  private function getTopModels():array
  {
    $rows = $this->updater->panel()->select(['*'], 'yandex_top_models')->make();

    return array_column( $rows, null, 'model' );
  }

  private function saveInDatabase( array $items ):void
  {
    $this->updater->insertSome(into: 'yandex_top_analytics', values: array_values($items));
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

(new GetPriceReport('WR'))->run();
 ?>
