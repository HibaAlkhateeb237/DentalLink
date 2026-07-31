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
        private LabPortfolioRepository $labPortfolioRepository,
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

    public function updateCase(
        PortfolioCase $portfolioCase,
        array $validated,
        ?UploadedFile $beforeImage = null,
        ?UploadedFile $afterImage = null,
    ): PortfolioCase {
        $attributes = [];

        if (isset($validated['case_name'])) {
            $attributes['case_name'] = $validated['case_name'];
        }

        if (isset($validated['is_published'])) {
            $attributes['is_published'] = (bool) $validated['is_published'];
        }

        if ($beforeImage !== null) {
            if ($portfolioCase->before_image_path) {
                Storage::disk('public')->delete($portfolioCase->before_image_path);
            }
            $attributes['before_image_path'] = $beforeImage->store('labs/portfolio', 'public');
        }

        if ($afterImage !== null) {
            if ($portfolioCase->after_image_path) {
                Storage::disk('public')->delete($portfolioCase->after_image_path);
            }
            $attributes['after_image_path'] = $afterImage->store('labs/portfolio', 'public');
        }

        return DB::transaction(function () use ($portfolioCase, $attributes): PortfolioCase {
            return $this->labPortfolioRepository->updateCase($portfolioCase, $attributes);
        });
    }

    public function updateCaseForLab(
        Lab $lab,
        int $portfolioCaseId,
        array $validated,
        ?UploadedFile $beforeImage = null,
        ?UploadedFile $afterImage = null,
    ): PortfolioCase {
        $portfolioCase = $this->labPortfolioRepository->findPortfolioCaseForLab($portfolioCaseId, $lab);

        if ($portfolioCase === null) {
            throw ValidationException::withMessages([
                'portfolio_case_id' => [__('lab_portfolio.case_not_found')],
            ]);
        }

        $beforeImagePath = null;
        $afterImagePath = null;

        if ($beforeImage !== null) {
            $beforeImagePath = $beforeImage->store('labs/portfolio', 'public');
        }

        if ($afterImage !== null) {
            $afterImagePath = $afterImage->store('labs/portfolio', 'public');
        }

        try {
            return DB::transaction(function () use ($portfolioCase, $validated, $beforeImagePath, $afterImagePath): PortfolioCase {
                $attributes = [];

                if (isset($validated['case_name'])) {
                    $attributes['case_name'] = $validated['case_name'];
                }

                if (isset($validated['is_published'])) {
                    $attributes['is_published'] = (bool) $validated['is_published'];
                }

                if ($beforeImagePath !== null) {
                    $attributes['before_image_path'] = $beforeImagePath;
                    if ($portfolioCase->before_image_path) {
                        Storage::disk('public')->delete($portfolioCase->before_image_path);
                    }
                }

                if ($afterImagePath !== null) {
                    $attributes['after_image_path'] = $afterImagePath;
                    if ($portfolioCase->after_image_path) {
                        Storage::disk('public')->delete($portfolioCase->after_image_path);
                    }
                }

                return $this->labPortfolioRepository->updateCase($portfolioCase, $attributes);
            });
        } catch (\Throwable $exception) {
            if ($beforeImagePath !== null) {
                Storage::disk('public')->delete($beforeImagePath);
            }
            if ($afterImagePath !== null) {
                Storage::disk('public')->delete($afterImagePath);
            }

            throw $exception;
        }
    }
}
