<?php

namespace Kwizer15\TradingBot\Strategy\DynamicPosition;

final class BacktestPositionDataListStorage implements PositionDataListStorageInterface
{
    private static PositionDataList $positionDataList;

    public function __construct(
        private DynamicPositionParameters $parameters
    ) {}

    public function load(): PositionDataList
    {
        self::$positionDataList ??= new PositionDataList($this->parameters);

        return self::$positionDataList;
    }

    public function save(PositionDataList $positionDataList): void
    {
        self::$positionDataList = $positionDataList;
    }
}