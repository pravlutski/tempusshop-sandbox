<?php
class ConfigLoader
{
  public function __construct( private ?ConfigProviderBase $config ){}

  public function loadModuleConfig( string $name ):ConfigProviderInterface
  {
    $class = $this->config->getConfigClass( $name );
    if ( !$class ) throw new UnregistredConfigException("Unknown Config Name");

    $path = __DIR__ . "/../configs/{$class}.php";
    if ( !file_exists($path) ) throw new UnregistredConfigException("No such config file");

    require_once( $path );
    return new $class;
  }
}
 ?>
