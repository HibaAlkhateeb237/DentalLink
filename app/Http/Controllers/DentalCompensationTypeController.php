<?php

namespace App\Http\Controllers;

use App\Http\Requests\DentalCompensationTypeStoreRequest;
use App\Http\Requests\DentalCompensationTypeUpdateRequest;
use App\Http\Resources\DentalCompensationTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\DentalCompensationType;
use App\Services\DentalCompensationTypeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DentalCompensationTypeController extends Controller
{
    public function __construct(private DentalCompensationTypeService $service)
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $query = $this->service->search($request->query('q'), $request->user());
        $compensations = $query->paginate(20);

        return DentalCompensationTypeResource::collection($compensations);
    }

    public function store(DentalCompensationTypeStoreRequest $request)
    {
        $compensation = $this->service->create($request->validated(), $request->user());

        return (new DentalCompensationTypeResource($compensation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(DentalCompensationType $dental_compensation_type)
    {
        return new DentalCompensationTypeResource($dental_compensation_type);
    }

    public function update(DentalCompensationTypeUpdateRequest $request, DentalCompensationType $dental_compensation_type)
    {
        $compensation = $this->service->update($dental_compensation_type, $request->validated(), $request->user());

        return new DentalCompensationTypeResource($compensation);
    }

    public function destroy(Request $request, DentalCompensationType $dental_compensation_type)
    {
        $this->service->delete($dental_compensation_type, $request->user());

        return (new ApiResponse)->success(null, __('messages.deleted'));
    }
}
