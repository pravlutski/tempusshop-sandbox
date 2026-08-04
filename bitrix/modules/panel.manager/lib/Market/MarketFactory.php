<?php

namespace Panel\Manager\Market;

class MarketFactory
{
    public static function create(string $marketCode): AbstractMarket
    {
        $classMap = [
            'RU' => RuMarket::class,
            'BY' => ByMarket::class,
            'YA' => YandexMarket::class,
            'OS' => OzonMarket::class,
            'WB' => WbMarket::class,
            'WBTL' => WbMarket::class,
            'AV' => AvitoMarket::class,
            'SB' => SberMarket::class,
            'OZTI' => OzonMarket::class,
            'WBBY' => WbByMarket::class,
            //'OZIP' => OzonMarket::class,
        ];
        
        if (!isset($classMap[$marketCode])) {
            throw new \InvalidArgumentException("Неизвестный маркетплейс: {$marketCode}");
        }
        
        return new $classMap[$marketCode]($marketCode);
    }
}