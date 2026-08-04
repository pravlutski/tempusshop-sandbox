<?php
class UIConfigProvider extends ConfigProviderBase
{
  private array $promosModes = ['MAP', 'FIX', 'NoMAP'];

  public function getPromosModes():array
  {
    return $this->promosModes;
  }
}
 ?>
