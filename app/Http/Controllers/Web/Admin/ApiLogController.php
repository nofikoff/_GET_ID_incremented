<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\ApiLogFilterRequest;
use App\Models\User;
use App\Queries\ApiLogQuery;
use Illuminate\Contracts\View\View;

class ApiLogController extends Controller
{
    public function index(ApiLogFilterRequest $request, ApiLogQuery $query): View
    {
        return view('admin.logs.index', [
            'logs' => $query->filter($request->validated()),
            // FR-015: every employee who could have an entry, deactivated ones included.
            'users' => User::query()->orderBy('email')->get(),
        ]);
    }
}
