<?php

declare(strict_types=1);

namespace App\Enums;

enum PointsHoldState: int
{
    case TryPending = 10;
    case TrySucceeded = 20;
    case Confirmed = 40;
    case RolledBack = 50;
}
