<?php
class PurchaseProcessorBase
{
  protected ?DataProvider $data = null;
  protected array $documents;

  public function __construct()
  {
    $this->init();
  }

  protected function init():void
  {
    CModule::IncludeModule('panel.manager');

    $this->data = new DataProvider(
      main: \Bitrix\Main\Application::getConnection()
    );
  }

  protected function getDocument( string $cabinet = 's1' ):DocumentProcessor
  {
    $ms = new MoyskladAPI( $cabinet );
    
    return new DocumentProcessor(
      api: new ApiManager( $ms ),
      data: $this->data,
      cabinet: $cabinet,
      isTestFlag: true,
    );
  }


}
 ?>
