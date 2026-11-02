<?php

namespace Tacraft\TcsGithubLogin\Github;

use Illuminate\Support\Facades\Http;

/**
 * Minimal GitHub OAuth client. Only the numeric user id is needed for the
 * shared identity, so a single `/user` call is enough.
 */
class GithubClient
{
    private const AUTHORIZE_ENDPOINT = 'https://github.com/login/oauth/authorize';
    private const TOKEN_ENDPOINT = 'https://github.com/login/oauth/access_token';
    private const USER_ENDPOINT = 'https://api.github.com/user';

    private const SCOPE = 'read:user';

    private const TIMEOUT = 20;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private bool $verifySsl = true
    ) {
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => self::SCOPE,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return self::AUTHORIZE_ENDPOINT.'?'.$query;
    }

    public function accessToken(string $code, string $redirectUri): string
    {
        $response = Http::asForm()
            ->withOptions($this->options())
            ->acceptJson()
            ->post(self::TOKEN_ENDPOINT, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if (! $response->successful()) {
            throw new GithubException('GitHub returned HTTP '.$response->status());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new GithubException('GitHub returned an invalid token response');
        }

        if (isset($body['error'])) {
            $detail = $body['error_description'] ?? $body['error'];

            throw new GithubException(is_string($detail) ? $detail : (string) $body['error']);
        }

        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new GithubException('GitHub did not return an access token');
        }

        return $token;
    }

    /**
     * @return array{id: string, login: string, name: string, email: string}
     */
    public function user(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->withOptions($this->options())
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->get(self::USER_ENDPOINT);

        if (! $response->successful()) {
            throw new GithubException('GitHub returned HTTP '.$response->status());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new GithubException('GitHub returned an invalid profile response');
        }

        $id = $body['id'] ?? null;

        if (! is_scalar($id) || (string) $id === '') {
            throw new GithubException('GitHub did not return a user id');
        }

        return [
            'id' => (string) $id,
            'login' => (string) ($body['login'] ?? ''),
            'name' => (string) ($body['name'] ?? ''),
            'email' => (string) ($body['email'] ?? ''),
        ];
    }

    private function options(): array
    {
        return [
            'verify' => $this->verifySsl,
            'timeout' => self::TIMEOUT,
            'connect_timeout' => 10,
        ];
    }
}
