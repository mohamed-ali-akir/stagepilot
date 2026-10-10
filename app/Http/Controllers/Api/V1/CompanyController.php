<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * /api/v1/companies: the companies the student applied to (the shared catalog itself is not exposed).
 */
class CompanyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $companies = Company::query()
            ->whereHas('jobApplications', fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderBy('name')
            ->paginate(20);

        return CompanyResource::collection($companies);
    }
}
