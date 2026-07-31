<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'average_delivery_time' => $this->resource['average_delivery_time'] ?? [],
            'monthly_revenue' => $this->resource['monthly_revenue'] ?? [],
            'technician_productivity' => $this->resource['technician_productivity'] ?? [],
            'department_workload' => $this->resource['department_workload'] ?? [],
            'top_clinics' => $this->resource['top_clinics'] ?? [],
            'yearly_performance_chart' => $this->resource['yearly_performance_chart'] ?? [],
            'orders_summary' => $this->resource['orders_summary'] ?? [],
            'date_range' => $this->resource['date_range'] ?? [],
        ];
    }
}
