<?php

declare(strict_types=1);

namespace App\Services\Adapters;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Paganini\XxlJobExecutor\Interfaces\CallbackClientInterface;
use Throwable;

/**
 * Guzzle callback to XXL-Job admin (same contract as job-executor).
 */
final class XxlJobGuzzleCallbackClientAdapter implements CallbackClientInterface
{
    public function __construct(
        private readonly string $adminAddress,
        private readonly string $accessToken,
        private readonly int $logDateTim,
        private readonly int $timeout = 10,
    ) {}

    public function sendCallback(int $logId, int $handleCode, string $handleMsg): bool
    {
        try {
            $callbackUrl = $this->adminAddress.'/api/callback';

            $headers = [
                'Content-Type' => 'application/json',
                'XXL-JOB-ACCESS-TOKEN' => $this->accessToken,
            ];

            $requestBody = [
                [
                    'logId' => $logId,
                    'logDateTim' => $this->logDateTim,
                    'handleCode' => $handleCode,
                    'handleMsg' => $handleMsg,
                ],
            ];

            $httpClient = new Client;
            $response = $httpClient->post($callbackUrl, [
                'headers' => $headers,
                'json' => $requestBody,
                'timeout' => $this->timeout,
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::error('[xxljob] callback failed, status code: '.$response->getStatusCode());

                return false;
            }

            return true;
        } catch (GuzzleException $e) {
            Log::error('[xxljob] callback exception of Guzzle: '.$e->getMessage());

            return false;
        } catch (Throwable $e) {
            Log::error('[xxljob] callback exception: '.$e->getMessage());

            return false;
        }
    }
}
