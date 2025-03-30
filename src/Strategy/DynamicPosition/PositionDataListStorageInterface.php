<?php

namespace Kwizer15\TradingBot\Strategy\DynamicPosition;

interface PositionDataListStorageInterface
{
    public function load(): PositionDataList;

    public function save(PositionDataList $positionDataList): void;
}
