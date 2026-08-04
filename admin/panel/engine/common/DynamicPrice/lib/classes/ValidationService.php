<?php
class ValidationService
{
  public static function validateConstruct( string $mp, string $cab ):bool
  {
    $checkPlatform = in_array( $mp, ConfigProvider::getAllowedPlatforms() );
    if ( !$checkPlatform ) throw new InvalidArgumentException("Unknown platform {$mp}");

    $checkCabinet = in_array( $cab, ConfigProvider::getAllowedCabinets($mp) );
    if ( !$checkCabinet ) throw new InvalidArgumentException("Unknown cabinet {$cab} for platform {$mp}");

    return true;
  }
}
 ?>
