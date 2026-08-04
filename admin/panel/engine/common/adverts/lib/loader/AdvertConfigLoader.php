<?php
class AdvertConfigLoader
{
  public static function load( string $platform ):ConfigProviderInterface
  {
    $base = new ConfigProviderBase;

    if ( !$base->getAllowedPlatforms()[$platform] ){
      throw new Exception("Undefined platform");
    }

    $className = $base->getConfigClass( $platform );

    return new $className;
  }
}
 ?>
