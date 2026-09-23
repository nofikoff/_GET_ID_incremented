<?php

namespace App\Http\Controllers\Web;

use App\Actions\SignInRefused;
use App\Actions\SignInWithGoogle;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, SignInWithGoogle $signIn): RedirectResponse
    {
        try {
            // Called directly: a chained stateless() would drop the state check and bypass Socialite::fake in tests.
            $user = $signIn(Socialite::driver('google')->user());
        } catch (SignInRefused $refused) {
            return $this->refuse($refused->getMessage());
        } catch (InvalidStateException|GuzzleException $failed) {
            // An outage or a replayed callback blocks only people signing in; tokens never reach Google (FR-021c).
            Log::warning('Google sign-in failed', ['exception' => $failed::class, 'message' => $failed->getMessage()]);

            return $this->refuse('Google не подтвердил вход. Попробуйте войти ещё раз.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('tokens.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    private function refuse(string $reason): RedirectResponse
    {
        return to_route('login')->withErrors(['google' => $reason]);
    }
}
