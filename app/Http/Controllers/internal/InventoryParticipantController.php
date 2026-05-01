<?php

declare(strict_types=1);

namespace App\Http\Controllers\internal;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\Internal\InternalInventoryParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InventoryParticipantController extends Controller
{
    public function __construct(
        private readonly InternalInventoryParticipantService $inventory,
    ) {}

    public function action(Request $request): JsonResponse
    {
        $data = $this->sagaParticipantData($request);
        $uid = (int) ($data['uid'] ?? 0);
        $idem = (string) ($data['saga_step_idem_key'] ?? '');

        $lines = $this->linesFromPayload($data);
        $out = $this->inventory->actionPhase($uid, $lines, $idem);

        return response()->json(ApiResponse::ok($out));
    }

    public function compensate(Request $request): JsonResponse
    {
        $ctx = $request->input('context');
        $token = is_array($ctx) ? trim((string) ($ctx['inventory_token'] ?? '')) : '';

        $this->inventory->compensatePhase($token);

        return response()->json(ApiResponse::ok(['inventory_token' => $token]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{product_id: int, quantity: int}>
     */
    private function linesFromPayload(array $data): array
    {
        $raw = $data['lines'] ?? null;
        if (! is_array($raw)) {
            throw new RuntimeException('lines must be an array.');
        }

        $lines = [];
        foreach ($raw as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) ($line['product_id'] ?? 0),
                'quantity' => (int) ($line['quantity'] ?? 0),
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('lines must contain at least one line.');
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->input('payload');
        if (is_array($payload)) {
            return $payload;
        }

        $all = $request->all();

        return is_array($all) ? $all : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function sagaParticipantData(Request $request): array
    {
        $data = $this->payload($request);
        if (trim((string) ($data['saga_step_idem_key'] ?? '')) === '') {
            $sid = trim((string) $request->input('saga_instance_id', ''));
            $step = trim((string) $request->input('step_index', ''));
            if ($sid !== '' && $step !== '') {
                $data['saga_step_idem_key'] = $sid.':'.$step;
            }
        }

        return $data;
    }
}
