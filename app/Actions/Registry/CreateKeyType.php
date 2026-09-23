<?php

namespace App\Actions\Registry;

use App\Models\KeyType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

final class CreateKeyType
{
    public function __construct(private readonly RegistryChangeLog $changeLog) {}

    /**
     * @param  array<string, mixed>  $data  validated by KeyTypeRules::store()
     *
     * @throws UniqueConstraintViolationException when a concurrent registration of the code won the race, as in CreateProject
     */
    public function __invoke(User $admin, array $data): KeyType
    {
        $keyType = KeyType::query()->create($data);

        $this->changeLog->created($admin, $keyType);

        return $keyType;
    }
}
