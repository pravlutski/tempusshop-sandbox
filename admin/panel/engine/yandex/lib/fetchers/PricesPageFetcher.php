<?php
class PricesPageFetcher extends PageFetcherBase implements PageFetcherInterface
{
  public function __call($name, $args)
  {
    throw new Exception("Fetcher is not implemented");
  }
}
 ?>
