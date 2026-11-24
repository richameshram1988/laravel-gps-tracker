<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreLocationRequest;
use App\Services\LocationIngestService;
class LocationController extends Controller
{
    public function store(StoreLocationRequest $request, LocationIngestService $service)
    {
        $result = $service->ingest(
            $request->user()->id,                 // token se user, body se nahi
            $request->validated('locations')
        );

        return response()->json($result, 201);
    }
}
