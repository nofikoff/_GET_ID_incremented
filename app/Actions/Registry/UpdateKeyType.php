<?php

namespace App\Actions\Registry;

use App\Models\KeyType;
use App\Models\User;

final class UpdateKeyType
{
    public function __construct(private readonly RegistryChangeLog $changeLog) {}

    /**
     * @param  array<string, mixed>  $data  validated by KeyTypeRules::update()
     */
    public function __invoke(User $admin, KeyType $keyType, array $data): KeyType
    {
        $keyType->fill($data);

        if ($keyType->isDirty()) {
            $keyType->save();
            $this->changeLog->updated($admin, $keyType);
        }

        return $keyType;
    }
}
