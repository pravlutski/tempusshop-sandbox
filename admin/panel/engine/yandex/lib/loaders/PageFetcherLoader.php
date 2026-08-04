<?php
class PageFetcherLoader
{
  public function __construct(
    private ?ApiManager $api,
    private ?ConfigProviderInterface $config,
  ){}

  public function loadRepository( string $name ):PageFetcherInterface
  {
    $class = Config::instance()->getPageFetcherClass($name);
    if ( !$class ) throw new UnregistredPageFetcherException("Unknown Page Fetcher Name");

    $path = __DIR__ . "/../fetchers/{$class}.php";
    if ( !file_exists($path) ) throw new UnregistredPageFetcherException("No such config file");

    require_once( $path );

    $parent = Config::instance()->getParentClassName('fetcher');
    $interface = Config::instance()->getRequiredInterfaceName('fetcher');

    if ( !is_subclass_of($class, $parent) ) throw new OrphanClassException("{$class} must extend {$parent}");
    if ( !is_subclass_of($class, $interface) ) throw new OrphanClassException("{$class} must implement {$interface}");

    return new $class(
      api: $this->api,
      config: Config::instance(),
    );
  }
}
?>
