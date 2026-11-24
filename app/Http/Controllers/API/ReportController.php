<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\ReportFilterRequest;
use App\Services\TravelReportService;
use App\Models\User;
use App\Exports\TravelReportExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private TravelReportService $reports)
    {
    }
    public function user(ReportFilterRequest $request, User $user)
    {
        //dd($request->user()->only('id', 'email', 'is_admin'), $user->id);
        $auth = $request->user();
        ///Gate::authorize('view-report', $user);
        abort_unless($auth->is_admin || $auth->id === $user->id, 403, 'Not allowed');

        return response()->json($this->reports->forUser(
            $user,
            $request->validated('date'),
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 50),
        ));
    }
    public function summary(ReportFilterRequest $request)
    {
        $filters = $request->validated();
        $auth = $request->user();

        if (!$auth->is_admin) {
            // Normal user sirf apna data dekh sakta hai
            abort_if(
                !empty($filters['user_id']) && (int) $filters['user_id'] !== $auth->id,
                403,
                'Aap sirf apna report dekh sakte hain.'
            );
            $filters['user_id'] = $auth->id;
        }

        return response()->json($this->reports->summary(
            $filters['date'],
            $filters,
            (int) $request->input('per_page', 15)
        ));
    }
    public function export(ReportFilterRequest $request)
    {
        $filters = $request->validated();
        $auth = $request->user();

        if (!$auth->is_admin) {
            abort_if(
                !empty($filters['user_id']) && (int) $filters['user_id'] !== $auth->id,
                403,
                'Not allowed'
            );
            $filters['user_id'] = $auth->id;
        }

        $format = $request->input('format', 'csv');
        $rows = $this->reports->summaryRows($filters['date'], $filters);

        return Excel::download(
            new TravelReportExport($rows),
            "travel-report-{$filters['date']}.{$format}"
        );
    }
    public function mapbased(ReportFilterRequest $request, User $user)
    {


        $auth = $request->user();
        ///Gate::authorize('view-report', $user);
        abort_unless($auth->is_admin || $auth->id === $user->id, 403, 'Not allowed');

        return response()->json($this->reports->forMapBased(
            $user,
            $request->validated('date')
        ));
    }



}
