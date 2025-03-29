<?php

namespace Kwizer15\TradingBot\Strategy\DynamicPosition;

final readonly class BacktestPositionDataListStorage implements PositionDataListStorageInterface
{

    public function __construct(
        private DynamicPositionParameters $parameters
    ) {}

    public function load(): PositionDataList
    {
        return new PositionDataList($this->parameters);
    }

    public function save(PositionDataList $positionDataList): void
    {
        // Do nothing
    }
}