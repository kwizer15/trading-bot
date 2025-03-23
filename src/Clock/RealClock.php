<?php

namespace Kwizer15\TradingBot\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class RealClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
