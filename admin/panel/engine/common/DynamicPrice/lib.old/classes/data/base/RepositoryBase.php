<?php
class RepositoryBase
{
  protected DBPanel $panel;
  protected Bitrix\Main\DB\MysqliConnection $main;

  public function __construct( Bitrix\Main\DB\MysqliConnection $main, DBPanel $panel )
  {
    $this->panel = $panel;
    $this->main = $main;
  }
}
 ?>
