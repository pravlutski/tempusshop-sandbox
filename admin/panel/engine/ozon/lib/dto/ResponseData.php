<?php
class ResponseData
{
  public function __construct( private string $data = '' ){}

    public function decode():array
    {
      if ( empty($this->data) ) return [];
      return json_decode($this->data, true);
    }

    public function raw():string
    {
      if ( empty($this->data) ) return '';
      return $this->data;
    }
}
 ?>
