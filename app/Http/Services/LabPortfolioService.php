<?php

namespace App\Http\Services;

use App\Http\Repositories\LabPortfolioRepository;
use App\Models\Lab;
use App\Models\PortfolioCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LabPortfolioService
{
    public function __construct(
        private  LabPortfolioRepository $labPortfolioRepository,
    ) {}

    public function getPublishedPortfolioByLab(Lab $lab, int $perPage = 15): LengthAwarePaginator
    {
        return $this->labPortfolioRepository->paginatePublishedByLab($lab, $perPage);
    }

    /**
     * @param  array{order_id:int|string,case_name:string,is_published?:bool}  $validated
     */
    public function createCaseForLab(
        Lab $lab,
        array $validated,
        UploadedFile $beforeImage,
        UploadedFile $afterImage,
    ): PortfolioCase {
        $order = $this->labPortfolioRepository->findEligibleOrderForLab((int) $validated['order_id'], $lab);

        if ($order === null) {
            throw ValidationException::withMessages([
                'order_id' => [__('lab_portfolio.invalid_order_for_portfolio')],
            ]);
        }

        $existingCase = $this->labPortfolioRepository->portfolioCaseExistsForOrder($order);

        if ($existingCase) {
            throw ValidationException::withMessages([
                'order_id' => [__('lab_portfolio.case_already_exists_for_order')],
            ]);
        }

        $beforeImagePath = $beforeImage->store('labs/portfolio', 'public');
        $afterImagePath = $afterImage->store('labs/portfolio', 'public');

        try {
            return DB::transaction(function () use ($order, $validated, $beforeImagePath, $afterImagePath): PortfolioCase {
                return $this->labPortfolioRepository->createCase([
                    'order_id' => $order->id,
                    'case_name' => $validated['case_name'],
                    'before_image_path' => $beforeImagePath,
                    'after_image_path' => $afterImagePath,
                    'duration_minutes' => $this->labPortfolioRepository->calculateOrderDurationMinutes($order),
                    'is_published' => (bool) ($validated['is_published'] ?? true),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete([$beforeImagePath, $afterImagePath]);

            throw $exception;
        }
    }
}
