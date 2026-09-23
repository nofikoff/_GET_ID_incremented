<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Registry\CreateKeyType;
use App\Actions\Registry\UpdateKeyType;
use App\Http\Concerns\RethrowsUniqueConflictAsValidation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreKeyTypeRequest;
use App\Http\Requests\Web\Admin\UpdateKeyTypeRequest;
use App\Models\KeyType;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;

class KeyTypeController extends Controller
{
    use RethrowsUniqueConflictAsValidation;

    public function index(): View
    {
        return view('admin.key-types.index', ['keyTypes' => KeyType::query()->orderBy('code')->get()]);
    }

    public function create(): View
    {
        return view('admin.key-types.create');
    }

    public function store(StoreKeyTypeRequest $request, CreateKeyType $createKeyType, #[CurrentUser] User $admin): RedirectResponse
    {
        try {
            $keyType = $createKeyType($admin, $request->validated());
        } catch (UniqueConstraintViolationException $conflict) {
            $this->rethrowAsValidation($request, $request->rules(...), $conflict);
        }

        return to_route('admin.key-types.index')->with('status', "Тип «{$keyType->code}» заведён.");
    }

    public function edit(KeyType $keyType): View
    {
        return view('admin.key-types.edit', ['keyType' => $keyType]);
    }

    public function update(UpdateKeyTypeRequest $request, KeyType $keyType, UpdateKeyType $updateKeyType, #[CurrentUser] User $admin): RedirectResponse
    {
        $updateKeyType($admin, $keyType, $request->validated());

        return to_route('admin.key-types.index')->with('status', "Тип «{$keyType->code}» сохранён.");
    }
}
