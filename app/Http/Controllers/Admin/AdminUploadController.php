<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Mall\MallOssUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class AdminUploadController extends Controller
{
    public function __construct(
        private readonly MallOssUploadService $mallOssUpload,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:102400'],
        ]);

        try {
            $objectKey = $this->mallOssUpload->uploadProductFile($validated['file']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['path' => $objectKey]);
    }
}
