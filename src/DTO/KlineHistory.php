<?php

namespace Kwizer15\TradingBot\DTO;

final readonly class KlineHistory
{
    /**
     * @param non-empty-array<Kline> $data
     */
    private function __construct(
        private string $pairSymbol,
        private array $originalData,
        private array $data
    ) {
    }

    public static function create(string $pair, iterable $historycalData): self
    {
        $originalData = [];
        $data = [];
        foreach ($historycalData as $kline) {
            $originalData[] = $kline;
            $data[] = new Kline(
                (int) $kline[0],
                (float) $kline[1],
                (float) $kline[2],
                (float) $kline[3],
                (float) $kline[4],
                (float) $kline[5],
                (int) $kline[6],
            );
        }

        if ([] === $data) {
            throw new \Exception('Data is empty');
        }

        return new self($pair, $originalData, $data);
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

        return new self($this->pairSymbol, $slice, $slice2);
    }

    public function getData(): array
    {
        return $this->originalData;
    }

    public function from(): string
    {
        return $this->first()->openDate();
    }

    public function to(): string
    {
        return $this->last()->closeDate();
    }

    public function listCloses(): array
    {
        $closes = [];
        foreach ($this->data as $kline) {
            $closes[] = $kline->close;
        }
        return $closes;
    }
}
