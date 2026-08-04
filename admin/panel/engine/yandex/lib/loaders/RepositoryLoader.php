<?php
class RepositoryLoader
{
  public function __construct(
    private ?Bitrix\Main\DB\MysqliConnection $main = null,
    private ?DBPanel $panel = null
  ){}

  public function loadRepository( string $name ):RepositoryInterface
  {
    $className = Config::instance()->getRepositoryClass($name);
    
    if ( !$className ) throw new UnregistredRepositoryException("Unknown Repository Name");
    if ( !class_exists($className) ) throw new UnregistredRepositoryException("Class file does not exist");

    $parent = Config::instance()->getParentClassName('repository');
    $interface = Config::instance()->getRequiredInterfaceName('repository');

    if ( !is_subclass_of($className, $parent) ) throw new OrphanClassException("{$className} must extend {$parent}");
    if ( !is_subclass_of($className, $interface) ) throw new OrphanClassException("{$className} must implement {$interface}");

    return new $className(
      panel: $this->panel,
      main: $this->main,
    );
  }
}
 ?>
