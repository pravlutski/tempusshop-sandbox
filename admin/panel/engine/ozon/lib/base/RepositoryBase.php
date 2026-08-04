<?php
class RepositoryBase
{
  public function __construct(
    private DBPanel $panel,
    private Bitrix\Main\DB\MysqliConnection $main
    )
  {}
}
 ?>
