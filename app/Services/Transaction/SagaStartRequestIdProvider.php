<?php

declare(strict_types=1);

namespace App\Services\Transaction;

/**
 * Optional correlation id for {@see SagaCoordinatorClient::start()} (e.g. snowflake id as {@code X-Request-Id}).
 */
interface SagaStartRequestIdProvider
{
    /**
     * @return non-empty-string|null null: do not send {@code X-Request-Id}
     */
    public function requestIdForSagaStart(): ?string;
}
