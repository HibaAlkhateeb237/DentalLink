<?php

namespace App\Http\Controllers;

use App\Http\Resources\DoctorOrdersResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DoctorOrdersService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorOrdersController extends Controller
{
    public function __construct(
        private DoctorOrdersService $doctorOrdersService,
        private ApiResponse $apiResponse,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $doctors = $this->doctorOrdersService->getDoctorsWithOrders($search);

        return $this->apiResponse->success(
            DoctorOrdersResource::collection($doctors)->toArray($request),
            __('orders.retrieved_successfully'),
            200,
        );
    }

    public function show(Request $request, User $doctor): JsonResponse
    {
        $doctor = $this->doctorOrdersService->getDoctorWithOrders($doctor);

        if ($doctor === null) {
            return $this->apiResponse->error(__('messages.not_found'), 404);
        }

        return $this->apiResponse->success(
            DoctorOrdersResource::make($doctor)->toArray($request),
            __('orders.retrieved_successfully'),
            200,
        );
    }
}
