<?php

declare(strict_types=1);

namespace App\Services\user;

use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Paganini\Aggregation\Support\DownstreamPayload;

readonly class UserFoundationGateway
{
    public function __construct(private ResolvedApiGatewayBaseUrl $resolvedFoundationBaseUrl) {}

    public function fetchCurrentUser(Request $request): array
    {
        $baseUrl = $this->resolvedFoundationBaseUrl->resolve();
        if ($baseUrl === '') {
            throw new ConfigurationMissingException('Missing user foundation base_url configuration.');
        }

        $timeout = (int) config('mall_agg.foundation.timeout_seconds', 3);
        $endpoint = (string) config('mall_agg.foundation.me_endpoint', '/api/user/me');
        $token = trim((string) $request->header('X-User-Access-Token', ''));

        $response = Http::timeout($timeout)
            ->withHeaders(['X-User-Access-Token' => $token])
            ->acceptJson()
            ->get($baseUrl.$endpoint);

        if (! $response->successful()) {
            if ($response->status() === 401) {
                throw $this->authRequiredFromHttpResponse($response);
            }
            throw new DownstreamServiceException('Failed to fetch base user info from foundation service.');
        }

        try {
            return DownstreamPayload::extractData($response->json(), 'foundation user service');
        } catch (DownstreamServiceException $e) {
            if (str_contains(strtolower($e->getMessage()), 'login required')) {
                throw new FoundationAuthRequiredException($e->getMessage(), 0, $e);
            }
            throw $e;
        }
    }

    private function authRequiredFromHttpResponse(ClientResponse $response): FoundationAuthRequiredException
    {
        $json = $response->json();
        $detail = 'login required';
        if (is_array($json) && isset($json['message']) && is_string($json['message']) && $json['message'] !== '') {
            $detail = $json['message'];
        }

        return new FoundationAuthRequiredException(
            'Downstream error from foundation user service: '.$detail
        );
    }
}
