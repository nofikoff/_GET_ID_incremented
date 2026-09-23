<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\KeyType;
use Illuminate\Contracts\View\View;

/**
 * Read-only, as the project screen and for the same reason.
 */
class KeyTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.key-types.index', ['keyTypes' => KeyType::query()->orderBy('code')->get()]);
    }
}
