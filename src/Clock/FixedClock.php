<?php

namespace Kwizer15\TradingBot\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(private readonly DateTimeImmutable $date)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->date;
    }
}