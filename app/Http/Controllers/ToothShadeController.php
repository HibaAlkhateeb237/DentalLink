<?php

namespace App\Http\Controllers;

use App\Http\Resources\ToothShadeResource;
use App\Http\Responses\ApiResponse;
use App\Models\ToothShade;
use Illuminate\Http\JsonResponse;

class ToothShadeController extends Controller
{
    public function __construct(private ApiResponse $apiResponse) {}

    public function index(): JsonResponse
    {
        $shades = ToothShade::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->apiResponse->success(
            ToothShadeResource::collection($shades),
            __('tooth_shades.list_retrieved'),
        );
    }
}
