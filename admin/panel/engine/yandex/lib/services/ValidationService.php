<?php
class ValidationService
{
  private ?ConfigProviderInterface $config = null;

  public static function init():void
  {
    self::$config = $config;
  }

  public static function isCabinetValid( string $cabinet ):bool
  {
    $cabinets = self::$config->getCabinets();

    if ( !isset($cabinets[$cabinet]) ){
      throw new ValidationException("Cabinet {$cabinet} is invalid");
    }

    return true;
  }

  public static function isModuleValid( string $module ):bool
  {
    $modules = self::$config->getModules();

    if ( !isset($modules[$module]) ){
      throw new ValidationException("Module {$module} is invalid");
    }

    return true;
  }

  public static function isSupplierFilterValid( array $filter ):bool
  {
    foreach ( $filter as $row ){
      if ( !is_int($row) ) throw new ValidationException("Suppliers filter has invalid structure");
    }

    return true;
  }
}
 ?>
