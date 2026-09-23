<?php

namespace App\Actions\Registry;

use App\Domain\Sequence\Exceptions\NotTheLastIdentifier;
use App\Domain\Sequence\SequenceWithdrawer;
use App\Domain\Sequence\WithdrawnIdentifier;
use App\Models\Identifier;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class WithdrawIdentifier
{
    public function __construct(
        private readonly SequenceWithdrawer $withdrawer,
        private readonly RegistryChangeLog $changeLog,
    ) {}

    /**
     * @throws ModelNotFoundException
     * @throws NotTheLastIdentifier
     */
    public function __invoke(User $admin, Identifier $identifier): WithdrawnIdentifier
    {
        $withdrawn = $this->withdrawer->withdraw($identifier);

        $this->changeLog->withdrawn($admin, $identifier->project, $identifier->keyType, $withdrawn);

        return $withdrawn;
    }
}
