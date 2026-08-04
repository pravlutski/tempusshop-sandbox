<?php


/**
 *
 */
class ClassName
{

  public function check( $arg ){
    if ( $arg != 4 ){
      trigger_error('ОШИБКА: СОСАТЬ', E_USER_ERROR);
    }
    print_r( 'Вот тебе твой аругмент: ' . $arg  . "\n");
  }
}



 ?>
