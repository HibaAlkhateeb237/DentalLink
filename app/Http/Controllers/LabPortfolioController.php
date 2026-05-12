<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabPortfolioIndexRequest;
use App\Http\Requests\LabPortfolioStoreRequest;
use App\Http\Resources\LabPortfolioCaseResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\LabPortfolioService;
use App\Models\Lab;
use App\Models\PortfolioCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class LabPortfolioController extends Controller
{
    public function __construct(
        private LabPortfolioService $labPortfolioService,
        private ApiResponse $apiResponse,
    ) {}

    public function index(LabPortfolioIndexRequest $request, Lab $lab): JsonResponse
    {
        // Gate::authorize('viewAny', [PortfolioCase::class, $lab]);

        if (! data_get($lab, 'is_active', true)) {
            return $this->apiResponse->error(__('messages.not_found'), 404);
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $portfolioCases = $this->labPortfolioService->getPublishedPortfolioByLab($lab, $perPage);

        $resource = LabPortfolioCaseResource::collection($portfolioCases)->toArray(request());

        return $this->apiResponse->success(
            $resource,
            __('lab_portfolio.retrieved_successfully'),
            200,
        );
    }

    public function store(LabPortfolioStoreRequest $request, Lab $lab): JsonResponse
    {
        // Gate::authorize('create', [PortfolioCase::class, $lab]);

        if (! data_get($lab, 'is_active', true)) {
            return $this->apiResponse->error(__('messages.not_found'), 404);
        }

        /** @var UploadedFile $beforeImage */
        $beforeImage = $request->file('before_image');

        /** @var UploadedFile $afterImage */
        $afterImage = $request->file('after_image');

        $portfolioCase = $this->labPortfolioService->createCaseForLab(
            $lab,
            $request->validated(),
            $beforeImage,
            $afterImage,
        );

        $resource = LabPortfolioCaseResource::make($portfolioCase)->toArray(request());

        return $this->apiResponse->success(
            $resource,
            __('lab_portfolio.created_successfully'),
            201,
        );
    }
}
