<?php
class AspectRatioProcessor
{
    private $targetRatio = 0.75; // 3:4 = 0.75

    public function __construct( private ?Intervention\Image\ImageManager $im = null ){}

    public function process( $imagePath )
    {
        $image = $this->im->make($imagePath)->trim();
        // Получаем размер холста в соотношении 3:4, наиболее близкий к оригиналу
        $canvasSize = $this->getCanvasSize($image->width(), $image->height());
        // Создаем холст
        $canvas = $this->im->canvas($canvasSize[0], $canvasSize[1], '#34ebae');

        return $canvas;
    }

    private function getCanvasSize( $imgWidth, $imgHeight )
    {
        // Вариант 1: на основе ширины
        $width1 = max(700, min(900, $imgWidth));
        $height1 = round($width1 / $this->targetRatio);
        $height1 = max(900, min(1200, $height1));
        $width1 = round($height1 * $this->targetRatio);

        // Вариант 2: на основе высоты
        $height2 = max(900, min(1200, $imgHeight));
        $width2 = round($height2 * $this->targetRatio);
        $width2 = max(700, min(900, $width2));
        $height2 = round($width2 / $this->targetRatio);

        // Выбираем вариант с меньшей разницей площадей
        $area1 = $width1 * $height1;
        $area2 = $width2 * $height2;
        $imgArea = $imgWidth * $imgHeight;

        if (abs($area1 - $imgArea) < abs($area2 - $imgArea)) {
            return [$width1, $height1];
        } else {
            return [$width2, $height2];
        }
    }
}
 ?>
