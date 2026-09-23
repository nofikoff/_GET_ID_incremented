<?php

namespace App\Http\Controllers\Web;

use App\Http\Concerns\RethrowsUniqueConflictAsValidation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreTokenRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;

class TokenController extends Controller
{
    use RethrowsUniqueConflictAsValidation;

    public function index(#[CurrentUser] User $user): View
    {
        return view('tokens.index', ['tokens' => $user->tokens()->orderBy('name')->get()]);
    }

    public function store(StoreTokenRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        try {
            $token = $user->createToken($request->string('name')->value());
        } catch (UniqueConstraintViolationException $conflict) {
            $this->rethrowAsValidation($request, $request->rules(...), $conflict);
        }

        // Sanctum keeps only a hash, so this one flash is the only time the value exists outside the client (FR-019a).
        return to_route('tokens.index')->with('plainTextToken', $token->plainTextToken);
    }

    /**
     * Looked up among the user's own tokens, so another person's token answers 404 and its existence stays hidden.
     */
    public function destroy(string $token, #[CurrentUser] User $user): RedirectResponse
    {
        $revoked = $user->tokens()->whereKey($token)->firstOrFail();
        $revoked->delete();

        return to_route('tokens.index')->with('status', "Токен «{$revoked->name}» отозван.");
    }
}
