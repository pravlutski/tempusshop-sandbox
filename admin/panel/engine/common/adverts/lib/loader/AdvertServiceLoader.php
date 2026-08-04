<?php
class AdvertServiceLoader
{
  public static function load( DataProvider $data ):AdvertServiceInterface
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
}
 ?>
