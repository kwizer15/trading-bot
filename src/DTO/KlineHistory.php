<?php

namespace Kwizer15\TradingBot\DTO;

class KlineHistory
{
    /**
     * @param non-empty-array<Kline> $data
     */
    private function __construct(
        private readonly array $originalData,
        private readonly array $data
    ) {
    }

    public static function create(array $historycalData): self
    {
        if ([] === $historycalData) {
            throw new \Exception('Data is empty');
        }

        $data = [];
        foreach ($historycalData as $kline) {
            $data[] = new Kline(...$kline);
        }

        return new self($historycalData, $data);
    }

    public function count(): int
    {
        return \count($this->data);
    }

    public function first(): Kline
    {
        return $this->get(0);
    }

    public function last(): Kline
    {
        return $this->get($this->count() - 1);
    }

    public function get(int $index): Kline
    {
        return $this->data[$index] ?? throw new \Exception('Index not found');
    }

    public function slice(int $length): self
    {
        $slice = array_slice($this->originalData, 0, $length);
        $slice2 = array_slice($this->data, 0, $length);
        return new self($slice, $slice2);
    }

    public function getData(): array
    {
        return $this->originalData;
    }
}