<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceTokenRequest;
use App\Http\Responses\ApiResponse;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function __construct(protected ApiResponse $response) {}

    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $user = $request->user();

        DeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $request->input('token'),
            ],
            [
                'device_type' => $request->input('device_type'),
            ],
        );

        return $this->response->success(null, 'تم حفظ رمز الجهاز بنجاح.');
    }

    public function destroy(DeviceToken $deviceToken): JsonResponse
    {
        if ($deviceToken->user_id !== request()->user()->id) {
            return $this->response->error('لا يمكنك حذف هذا الرمز.', 403);
        }

        $deviceToken->delete();

        return $this->response->success(null, 'تم حذف رمز الجهاز بنجاح.');
    }
}
