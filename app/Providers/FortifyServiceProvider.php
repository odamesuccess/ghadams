<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Admin\ResetUserTwoFactorAuthenticationController;
use App\Http\Controllers\Admin\TwoFactorResetPageController;
use App\Http\Middleware\EnsureTwoFactorConfigured;
use App\Http\Middleware\PreventTwoFactorDisable;
use App\Models\User;
use App\Security\GhanaDamsTwoFactorAuthenticationProvider;
use App\Services\LoginTrackerService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            TwoFactorAuthenticationProvider::class,
            GhanaDamsTwoFactorAuthenticationProvider::class,
        );
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureAuthentication();
        $this->configureRateLimiting();
        $this->configureGhanaDamsTwoFactorRoutes();

        /*
         * Mandatory for ALL authenticated GhanaDAMS users.
         *
         * The middleware redirects an unconfirmed user to setup,
         * but it does NOT regenerate the TOTP secret.
         */
        $this->app->booted(function (): void {
            $router = $this->app['router'];

            $router->pushMiddlewareToGroup(
                'web',
                PreventTwoFactorDisable::class
            );

            $router->pushMiddlewareToGroup(
                'web',
                EnsureTwoFactorConfigured::class
            );
        });
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    private function configureViews(): void
    {
        Fortify::loginView(
            fn (Request $request) => Inertia::render(
                'auth/Login',
                [
                    'canResetPassword' =>
                        Features::enabled(
                            Features::resetPasswords()
                        ),

                    'canRegister' =>
                        Features::enabled(
                            Features::registration()
                        ),

                    'status' =>
                        $request->session()->get('status'),
                ]
            )
        );

        Fortify::resetPasswordView(
            fn (Request $request) => Inertia::render(
                'auth/ResetPassword',
                [
                    'email' => $request->email,
                    'token' => $request->route('token'),
                ]
            )
        );

        Fortify::requestPasswordResetLinkView(
            fn (Request $request) => Inertia::render(
                'auth/ForgotPassword',
                [
                    'status' =>
                        $request->session()->get('status'),
                ]
            )
        );

        Fortify::verifyEmailView(
            fn (Request $request) => Inertia::render(
                'auth/VerifyEmail',
                [
                    'status' =>
                        $request->session()->get('status'),
                ]
            )
        );

        Fortify::registerView(
            fn () => Inertia::render('auth/Register')
        );

        Fortify::twoFactorChallengeView(
            fn () => Inertia::render(
                'auth/TwoFactorChallenge'
            )
        );

        Fortify::confirmPasswordView(
            fn () => Inertia::render(
                'auth/ConfirmPassword'
            )
        );
    }

    /**
     * Preserve the existing GhanaDAMS custom login checks.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(
            function (Request $request) {
                $email = (string) $request->input('email');

                $user = User::where(
                    'email',
                    $email
                )->first();

                if (
                    ! $user ||
                    ! Hash::check(
                        (string) $request->input('password'),
                        $user->password
                    )
                ) {
                    LoginTrackerService::record(
                        $request,
                        'login_failed',
                        $user,
                        $email,
                        'Failed login attempt.'
                    );

                    return null;
                }

                if (! $user->isActive()) {
                    LoginTrackerService::record(
                        $request,
                        'inactive_blocked',
                        $user,
                        $user->email,
                        'Login blocked because account is inactive.'
                    );

                    return null;
                }

                if ($user->isExpired()) {
                    LoginTrackerService::record(
                        $request,
                        'expired_blocked',
                        $user,
                        $user->email,
                        'Login blocked because account has expired.'
                    );

                    return null;
                }

                $user->forceFill([
                    'last_login_at' => now(),
                    'last_seen_at' => now(),
                ])->saveQuietly();

                LoginTrackerService::record(
                    $request,
                    'login_success',
                    $user,
                    $user->email,
                    'User logged in successfully.'
                );

                return $user;
            }
        );
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for(
            'two-factor',
            function (Request $request) {
                return Limit::perMinute(5)->by(
                    $request
                        ->session()
                        ->get('login.id')
                );
            }
        );

        RateLimiter::for(
            'login',
            function (Request $request) {
                $throttleKey =
                    Str::transliterate(
                        Str::lower(
                            $request->input(
                                Fortify::username()
                            )
                        ).
                        '|'.
                        $request->ip()
                    );

                return Limit::perMinute(5)->by(
                    $throttleKey
                );
            }
        );
    }

    /**
     * LMS-style secure 2FA reset routes.
     */
    private function configureGhanaDamsTwoFactorRoutes(): void
    {
        Route::middleware([
            'web',
            'auth',
        ])->group(function (): void {
            Route::get(
                '/admin/two-factor-reset',
                TwoFactorResetPageController::class
            )->name('admin.two-factor-reset');

            Route::post(
                '/users/{user}/two-factor/reset',
                ResetUserTwoFactorAuthenticationController::class
            )->name('users.two-factor.reset');
        });
    }
}