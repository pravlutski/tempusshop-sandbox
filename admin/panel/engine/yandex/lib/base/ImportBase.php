<?php
class ImportBase
{
  protected ?DataProvider $data;
  protected ?ApiManager $api;
  protected ?ConfigProviderInterface $config;
  protected ?PageFetcherInterface $fetcher;
  protected ?Updater $updater;

  protected ?BusinessServiceInterface $business;
  protected ?RequestServiceInterface $request;
  protected ?LogServiceInterface $log;

  protected ?string $cabinet = null;
  protected ?string $module = null;

  public function __construct( string $cabinet, string $module )
  {
    // some validation
    $this->cabinet = $cabinet;
    $this->module = $module;

    $this->initConfig( $module );
    $this->initConnection( $cabinet );
    $this->initFetcher( $module );
    $this->initAgentMonitoring( $module );
    $this->initServices( $module );
  }

  private function initConnection( string $cabinet ):void
  {
    $panel = new DBPanel;
    $main = \Bitrix\Main\Application::getConnection();
    CModule::includeModule("panel.manager");
    $repositoryLoader = new RepositoryLoader( $main, $panel );

    $data = new DataProvider(
      items: $repositoryLoader->loadRepository('items'),
      settings: $repositoryLoader->loadRepository('settings'),
      prices: $repositoryLoader->loadRepository('prices'),
    );
    $api = new ApiManager(
      auth: $data->settings()->getAuthData( $cabinet )
    );
    $updater = new Updater(
      main: $main,
      panel: $panel,
      isPanel: true
    );

    $this->updater = $updater;
    $this->data = $data;
    $this->api = $api;
  }

  private function initFetcher( string $module ):void
  {
    $pageFetcherLoader = new PageFetcherLoader(
      api: $this->api,
      config: $this->config
    );
    $this->fetcher = $pageFetcherLoader->loadRepository( $module );
  }

  private function initConfig( string $module ):void
  {
    $base = new ConfigProviderBase;
    $loader = new ConfigLoader( $base );
    $config = $loader->loadModuleConfig( $module );
    Config::init( $config );

    $this->config = Config::instance();
  }

  private function initAgentMonitoring( string $module ):void
  {
    CommunicationService::initConnection(
      panel: $this->updater->panel(),
      module: $module
    );
  }

  private function initServices( string $module ):void
  {
    $str = __DIR__. "/../services/{$module}/*Service.php";
    CommunicationService::log( '-------------------------------------' );
    $services = glob( $str );

    if ( empty($services) ) {
      CommunicationService::log('No additional services found');
      return;
    }

    foreach ( $services as $row ){
      require_once( $row );
      CommunicationService::log("'{$row}' -- Loaded");
    }
  }
}
 ?>
