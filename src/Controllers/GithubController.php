<?php

namespace Tacraft\TcsGithubLogin\Controllers;

use App\Models\User;
use Blessing\Filter;
use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tacraft\TcsGithubLogin\Github\GithubClient;
use Tacraft\TcsGithubLogin\Github\GithubException;
use Tacraft\TcsGithubLogin\Support\FakeAccount;

class GithubController extends Controller
{
    private const STATE_KEY = 'tcs_github_login_state';

    public function redirect(Request $request)
    {
        $config = $this->config();

        if ($config['client_id'] === '' || $config['client_secret'] === '') {
            abort(500, trans('Tacraft\TcsGithubLogin::general.not-configured'));
        }

        $state = Str::random(40);

        $request->session()->put(self::STATE_KEY, $state);

        $client = $this->client($config);

        return redirect()->away($client->authorizationUrl($this->callbackUrl(), $state));
    }

    public function callback(Request $request, Dispatcher $dispatcher, Filter $filter)
    {
        $expected = (string) $request->session()->pull(self::STATE_KEY, '');
        $state = (string) $request->query('state', '');

        if ($expected === '' || ! hash_equals($expected, $state)) {
            abort(400, trans('Tacraft\TcsGithubLogin::general.state-failed'));
        }

        if ($request->query('error') !== null) {
            $message = (string) ($request->query('error_description') ?: $request->query('error'));

            abort(400, trans('Tacraft\TcsGithubLogin::general.oauth-error', ['message' => $message]));
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            abort(400, trans('Tacraft\TcsGithubLogin::general.no-code'));
        }

        $config = $this->config();

        if ($config['client_id'] === '' || $config['client_secret'] === '') {
            abort(500, trans('Tacraft\TcsGithubLogin::general.not-configured'));
        }

        $client = $this->client($config);

        try {
            $accessToken = $client->accessToken($code, $this->callbackUrl());
            $profile = $client->user($accessToken);
        } catch (GithubException $e) {
            report($e);

            abort(500, trans('Tacraft\TcsGithubLogin::general.http-failed', ['message' => $e->getMessage()]));
        }

        $email = FakeAccount::emailFor($profile['id']);

        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

        // A local account may already own this address (for example after a
        // manual migration). Never take it over silently.
        if ($user !== null && (string) $user->password !== '') {
            report(new \RuntimeException('GitHub identity collision: '.$email));

            abort(403, trans('Tacraft\TcsGithubLogin::general.email-taken'));
        }

        if ($user === null) {
            $user = new User();
            $user->email = $email;
            $user->nickname = $this->nickname($profile);
            $user->score = (int) option('user_initial_score');
            $user->avatar = 0;
            $user->password = '';
            $user->ip = (string) $filter->apply('client_ip', $request->ip() ?? '0.0.0.0');
            $user->permission = User::NORMAL;
            $user->register_at = Carbon::now();
            $user->last_sign_at = Carbon::now()->subDay();
            $user->verified = true;
            $user->save();

            $dispatcher->dispatch('auth.registration.completed', [$user]);
        }

        $dispatcher->dispatch('auth.login.ready', [$user]);

        Auth::login($user);

        $dispatcher->dispatch('auth.login.succeeded', [$user]);

        return redirect($request->session()->pull('last_requested_path', url('/user')));
    }

    /**
     * @return array{client_id: string, client_secret: string, verify_ssl: bool}
     */
    private function config(): array
    {
        return [
            'client_id' => trim((string) option('tcs_github_login_client_id')),
            'client_secret' => trim((string) option('tcs_github_login_client_secret')),
            'verify_ssl' => filter_var(option('tcs_github_login_verify_ssl', true), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function client(array $config): GithubClient
    {
        return new GithubClient(
            $config['client_id'],
            $config['client_secret'],
            $config['verify_ssl']
        );
    }

    private function callbackUrl(): string
    {
        $configured = trim((string) option('tcs_github_login_callback_url'));

        return $configured !== '' ? $configured : url('github-oauth/callback');
    }

    private function nickname(array $profile): string
    {
        $nickname = trim($profile['name']);

        if ($nickname === '') {
            $nickname = trim($profile['login']);
        }

        if ($nickname === '') {
            $nickname = 'GitHub '.Str::upper(Str::substr(hash('sha256', $profile['id']), 0, 6));
        }

        return Str::limit($nickname, 50, '');
    }
}
