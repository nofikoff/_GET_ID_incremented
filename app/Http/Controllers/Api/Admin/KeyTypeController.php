<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\RethrowsUniqueConflictAsValidation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreKeyTypeRequest;
use App\Http\Requests\Api\Admin\UpdateKeyTypeRequest;
use App\Http\Resources\KeyTypeResource;
use App\Models\KeyType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class KeyTypeController extends Controller
{
    use RethrowsUniqueConflictAsValidation;

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', KeyType::class);

        return KeyTypeResource::collection(KeyType::query()->orderBy('code')->get());
    }

    public function store(StoreKeyTypeRequest $request): KeyTypeResource
    {
        try {
            $keyType = KeyType::query()->create($request->validated());
        } catch (UniqueConstraintViolationException $conflict) {
            $this->rethrowAsValidation($request, $request->rules(...), $conflict);
        }

        return new KeyTypeResource($keyType);
    }

    public function update(UpdateKeyTypeRequest $request, KeyType $keyType): KeyTypeResource
    {
        $keyType->update($request->validated());

        return new KeyTypeResource($keyType);
    }
}
