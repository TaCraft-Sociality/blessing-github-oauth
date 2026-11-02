<?php

namespace Tacraft\TcsGithubLogin;

use App\Services\OptionForm;
use Option;

class Configuration
{
    public function render()
    {
        $form = Option::form('tcs_github_login', trans('Tacraft\TcsGithubLogin::general.config-title'), function (OptionForm $form) {
            $form->text('tcs_github_login_client_id', trans('Tacraft\TcsGithubLogin::general.client-id'))
                ->hint(trans('Tacraft\TcsGithubLogin::general.client-id-hint'));

            $form->text('tcs_github_login_client_secret', trans('Tacraft\TcsGithubLogin::general.client-secret'))
                ->hint(trans('Tacraft\TcsGithubLogin::general.client-secret-hint'));

            $form->text('tcs_github_login_callback_url', trans('Tacraft\TcsGithubLogin::general.callback-url'))
                ->hint(trans('Tacraft\TcsGithubLogin::general.callback-url-hint'));

            $form->checkbox('tcs_github_login_verify_ssl', trans('Tacraft\TcsGithubLogin::general.verify-ssl'))
                ->value(option('tcs_github_login_verify_ssl', true))
                ->hint(trans('Tacraft\TcsGithubLogin::general.verify-ssl-hint'));
        })->handle();

        if (request()->isMethod('post')) {
            @unlink(storage_path('options.php'));
        }

        $callback = trim((string) option('tcs_github_login_callback_url'));

        return view('Tacraft\TcsGithubLogin::config', [
            'form' => $form,
            'callback' => $callback !== '' ? $callback : url('github-oauth/callback'),
        ]);
    }
}
