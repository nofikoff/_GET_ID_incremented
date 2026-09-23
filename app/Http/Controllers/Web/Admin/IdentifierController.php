<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Registry\WithdrawIdentifier;
use App\Domain\Sequence\Exceptions\NotTheLastIdentifier;
use App\Http\Controllers\Controller;
use App\Models\Identifier;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class IdentifierController extends Controller
{
    public function destroy(Project $project, Identifier $identifier, WithdrawIdentifier $withdraw, #[CurrentUser] User $admin): RedirectResponse
    {
        try {
            $withdrawn = $withdraw($admin, $identifier);
        } catch (NotTheLastIdentifier $refused) {
            // Keyed by pair, so the card shows it above the table it is about (specs/003-delete-last-identifier/research.md R5).
            throw ValidationException::withMessages(["identifiers.{$identifier->key_type_id}" => $refused->getMessage()]);
        }

        return to_route('admin.projects.show', $project)
            ->with('status', "Номер {$withdrawn->type} {$withdrawn->sequenceNumber} удалён. Следующий номер — {$withdrawn->nextSequence}.");
    }
}
