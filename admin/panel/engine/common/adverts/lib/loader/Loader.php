<?php
class Loader
{
  public static function loadService( DataProvider $data ):AdvertServiceInterface
  {
    ['api' => $apiClass, 'service' => $serviceClass] = Config::instance()->getServiceRequirements();

    if ( !$apiClass || !$serviceClass ) {
      throw new Exception("Cannot load unexisted service requirements or platform is not implemented yet");
    }
    if ( !class_exists($apiClass) || !class_exists($serviceClass) ){
      throw new Exception("One of required classes is missing");
    }

    return new $serviceClass( api: new $apiClass(), data: $data );
  }

  public static function loadConfig( string $platform ):ConfigProviderInterface
  {
    $base = new ConfigProviderBase;

    if ( !$base->getAllowedPlatforms()[$platform] ){
      throw new Exception("Undefined platform");
    }

    $className = $base->getConfigClass( $platform );

    return new $className;
  }

  public static function loadApiManager():ApiManagerInterface
  {
    ['api' => $apiClass] = Config::instance()->getServiceRequirements();

    if ( !$apiClass ) {
      throw new Exception("Cannot load unexisted service requirements or platform is not implemented yet");
    }
    if ( !class_exists($apiClass) ){
      throw new Exception("One of required classes is missing");
    }

    return new $apiClass();
  }
}
?>
