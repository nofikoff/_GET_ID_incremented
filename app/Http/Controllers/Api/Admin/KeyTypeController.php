<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreKeyTypeRequest;
use App\Http\Requests\Api\Admin\UpdateKeyTypeRequest;
use App\Http\Resources\KeyTypeResource;
use App\Models\KeyType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class KeyTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', KeyType::class);

        return KeyTypeResource::collection(KeyType::query()->orderBy('code')->get());
    }

    public function store(StoreKeyTypeRequest $request): KeyTypeResource
    {
        return new KeyTypeResource(KeyType::query()->create($request->validated()));
    }

    public function update(UpdateKeyTypeRequest $request, KeyType $keyType): KeyTypeResource
    {
        $keyType->update($request->validated());

        return new KeyTypeResource($keyType);
    }
}
