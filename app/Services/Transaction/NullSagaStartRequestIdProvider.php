<?php

declare(strict_types=1);

namespace App\Services\Transaction;

final class NullSagaStartRequestIdProvider implements SagaStartRequestIdProvider
{
    public function requestIdForSagaStart(): ?string
    {
        return null;
    }
}
