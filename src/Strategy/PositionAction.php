<?php

namespace Kwizer15\TradingBot\Strategy;

enum PositionAction
{
    case SELL;
    case INCREASE_POSITION;
    case PARTIAL_EXIT;
    case HOLD;
}
