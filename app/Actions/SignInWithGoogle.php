<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as GoogleIdentity;

/**
 * Turns the identity Google vouched for into an account. The domain is checked before any lookup or write,
 * so a refused sign-in leaves nothing behind (FR-018).
 */
final class SignInWithGoogle
{
    /**
     * @throws SignInRefused
     */
    public function __invoke(GoogleIdentity $google): User
    {
        $email = Str::lower((string) $google->getEmail());
        $domain = Str::lower((string) config('getid.allowed_email_domain'));

        if (! $this->inDomain($email, $domain) || ! $this->verified($google)) {
            throw SignInRefused::outsideDomain($domain);
        }

        // By email as well: the corporate address is the identity, its Google account id may be reissued.
        $user = User::query()->where('google_id', (string) $google->getId())->first()
            ?? User::query()->where('email', $email)->first();

        if ($user?->deactivated_at !== null) {
            throw SignInRefused::deactivated();
        }

        $user ??= $this->newAccount($email);
        $user->fill([
            'name' => $google->getName() ?? $email,
            'google_id' => (string) $google->getId(),
            'avatar_url' => $google->getAvatar(),
        ])->save();

        return $user;
    }

    private function inDomain(string $email, string $domain): bool
    {
        [$local, $emailDomain] = array_pad(explode('@', $email, 2), 2, '');

        return $local !== '' && $domain !== '' && $emailDomain === $domain;
    }

    /**
     * Google reports email_verified for every address it returns; an explicit false is a claim nobody owns.
     */
    private function verified(GoogleIdentity $google): bool
    {
        return ! $google instanceof AbstractUser || ($google->getRaw()['email_verified'] ?? true) !== false;
    }

    /**
     * FR-021: ADMIN_EMAILS and the default role apply only here, at creation; later changes belong to user:role.
     */
    private function newAccount(string $email): User
    {
        $user = new User(['email' => $email]);
        $admins = array_map(Str::lower(...), (array) config('getid.admin_emails'));
        $user->role = in_array($email, $admins, true) ? UserRole::Admin : config('getid.default_role');

        return $user;
    }
}
