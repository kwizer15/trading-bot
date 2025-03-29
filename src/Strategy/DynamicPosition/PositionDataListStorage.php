<?php

namespace Kwizer15\TradingBot\Strategy\DynamicPosition;

final readonly class PositionDataListStorage implements PositionDataListStorageInterface
{
    public function __construct(
        private DynamicPositionParameters $parameters
    ) {}

    public function load(): PositionDataList
    {
        $dataFile = $this->getPositionDataFile();

        if (file_exists($dataFile)) {
            $positionData = json_decode(file_get_contents($dataFile), true);

            return new PositionDataList($this->parameters, $positionData);
        }

        return new PositionDataList($this->parameters);
    }

    public function save(PositionDataList $positionDataList): void
    {
        $dataFile = $this->getPositionDataFile();
        file_put_contents($dataFile, json_encode($positionDataList->getPositionData(), JSON_PRETTY_PRINT));
    }

    private function getPositionDataFile(): string
    {
        return dirname(__DIR__, 3) . '/data/strategy_position_data.json';
    }
}