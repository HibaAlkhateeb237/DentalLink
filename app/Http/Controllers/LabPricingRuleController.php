<?php

namespace App\Http\Controllers;

use App\Http\Repositories\LabPricingRepository;
use App\Http\Requests\LabPricingRuleUpsertRequest;
use App\Http\Resources\LabPricingRuleResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\LabPricingRuleService;
use App\Models\Lab;
use App\Models\LabPricingRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LabPricingRuleController extends Controller
{
    public function __construct(
        private LabPricingRuleService $labPricingRuleService,
        private LabPricingRepository $labPricingRepository,
        private ApiResponse $apiResponse,
    ) {}

    public function index(Lab $lab): JsonResponse
    {
        Gate::authorize('viewAny', [LabPricingRule::class, $lab]);

        $at = request()->query('at');
        $effectiveAt = $at ? CarbonImmutable::parse($at) : CarbonImmutable::now();

        $rules = $this->labPricingRepository->getActiveRulesForLab($lab, $effectiveAt);

        return $this->apiResponse->success(
            [
                'lab_id' => $lab->id,
                'at' => $effectiveAt->toDateString(),
                'rules' => LabPricingRuleResource::collection($rules),
            ],
            __('pricing.rules_retrieved_successfully'),
            200,
        );
    }

    public function store(LabPricingRuleUpsertRequest $request, Lab $lab): JsonResponse
    {
        Gate::authorize('create', [LabPricingRule::class, $lab]);

        $rule = $this->labPricingRuleService->create($lab, $request->validated());

        return $this->apiResponse->success(
            LabPricingRuleResource::make($rule),
            __('pricing.rule_created_successfully'),
            201,
        );
    }

    public function update(LabPricingRuleUpsertRequest $request, Lab $lab, LabPricingRule $labPricingRule): JsonResponse
    {
        if ($labPricingRule->lab_id !== $lab->id) {
            abort(404);
        }

        Gate::authorize('update', $labPricingRule);

        $rule = $this->labPricingRuleService->update($labPricingRule, $request->validated());

        return $this->apiResponse->success(
            LabPricingRuleResource::make($rule),
            __('pricing.rule_updated_successfully'),
            200,
        );
    }

    public function destroy(Lab $lab, LabPricingRule $labPricingRule): JsonResponse
    {
        if ($labPricingRule->lab_id !== $lab->id) {
            abort(404);
        }

        Gate::authorize('delete', $labPricingRule);

        $rule = $this->labPricingRuleService->disable($labPricingRule);

        return $this->apiResponse->success(
            LabPricingRuleResource::make($rule),
            __('pricing.rule_deleted_successfully'),
            200,
        );
    }
}
