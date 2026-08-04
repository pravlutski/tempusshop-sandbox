<?php
class ImportPriceService
{
  public function __construct(
    private ?ApiManager $api = null,
    private ?DataProvider $data = null,
    private ?ConfigProviderInterface $config = null
  ){}

  public function getCampaignsSettings( string $cabinet ):array
  {
    $list = $this->data->settings()->getCampaignsMatchList( $cabinet );
    $default = $this->config->getDefaultMarkup();
    $result = [];

    foreach ( $list as $row ){
      $result[ $row['warehouse'] ] = [
        'campaignId' => $row['campaignId'],
        'markup' => $row['markup'] ?? $default
      ];
    }

    return $result;
  }

  public function sendBatch( array $offers ):array
  {
    $response = $this->api->updateBusinessPrices( data: $offers );

    return $response->getData()->decode();
  }

  public function calcualteDelay( int $offersCount ):int
  {
    $delay = $this->config->getUpdateBusinessPricesDelay();
    $threshold = $this->config->getDelayThreshold();
    $limitPerMinute = $this->config->getLimitPerMinute();
    $minDelay = $this->config->getMinDelay();

    $diff = round( $offersCount / $limitPerMinute, 2 );

    return ($diff < $threshold) ? $minDelay : $delay;
  }
}
 ?>
