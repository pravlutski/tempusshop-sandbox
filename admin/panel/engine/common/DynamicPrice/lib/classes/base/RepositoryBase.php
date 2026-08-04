<?php
class RepositoryBase
{
  public function __construct(
    protected Bitrix\Main\DB\MysqliConnection $main,
    protected DBPanel $panel
  )
  {}
}
 ?>
