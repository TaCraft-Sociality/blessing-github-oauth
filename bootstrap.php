<?php

use App\Models\User;
use App\Services\Hook;
use Blessing\Filter;
use Blessing\Rejection;
use Tacraft\TcsGithubLogin\Support\FakeAccount;

return function (Filter $filter) {
    // Keep one stable order no matter which plugin the server loads first:
    // QQ -> Microsoft -> GitHub -> the remaining providers.
    $insert = function (array $rows, string $formRow): array {
        $self = 'Tacraft\TcsGithubLogin::button';
        $ranks = [
            'Tacraft\TcsQqLogin::button' => 10,
            'Tacraft\TcsMicrosoftLogin::button' => 20,
            'Tacraft\TcsGithubLogin::button' => 30,
            'Tacraft\TcsLogin::button' => 40,
            'Blessing\OAuthCore::providers' => 50,
        ];

        $at = false;

        foreach ($rows as $index => $row) {
            if (isset($ranks[$row]) && $ranks[$row] > $ranks[$self]) {
                $at = $index;

                break;
            }
        }

        if ($at === false) {
            $form = array_search($formRow, $rows, true);
            $at = $form === false ? count($rows) : $form;
        }

        array_splice($rows, $at, 0, [$self]);

        return $rows;
    };

    $filter->add('auth_page_rows:login', function ($rows) use ($insert) {
        return $insert($rows, 'auth.rows.login.form');
    });

    $filter->add('auth_page_rows:register', function ($rows) use ($insert) {
        return $insert($rows, 'auth.rows.register.form');
    });

    // GitHub accounts own no password and receive a generated address, so the
    // profile page must not offer to change either. If another OAuth plugin
    // already put its notice in the grid, keep that one instead.
    $filter->add('grid:user.profile', function (array $grid) {
        $user = auth()->user();

        if (! $user instanceof User || ! FakeAccount::isManaged($user)) {
            return $grid;
        }

        if (FakeAccount::hasNotice($grid)) {
            return $grid;
        }

        return FakeAccount::hideCredentialWidgets($grid);
    });

    // Server-side counterpart: a hand-crafted request cannot change the
    // password or the email of a GitHub account either.
    $filter->add('user_can_edit_profile', function ($can, $action = '') {
        if ($can instanceof Rejection) {
            return $can;
        }

        $user = auth()->user();

        if (
            $user instanceof User
            && FakeAccount::isManaged($user)
            && in_array($action, ['password', 'email'], true)
        ) {
            return new Rejection(trans('Tacraft\TcsGithubLogin::general.profile-managed'));
        }

        return $can;
    });

    try {
        Hook::addMenuItem('admin', 1, [
            'title' => 'Tacraft\TcsGithubLogin::general.config-title',
            'link' => '/admin/tcs-github-login',
            'icon' => 'fa-code-branch',
        ]);
    } catch (\Throwable $e) {
        report($e);
    }

    try {
        Hook::addRoute(function () {
            Route::prefix('github-oauth')
                ->namespace('Tacraft\TcsGithubLogin\Controllers')
                ->middleware(['web', 'guest'])
                ->group(function () {
                    Route::get('', 'GithubController@redirect');
                    Route::get('callback', 'GithubController@callback');
                });

            Route::prefix('admin/tcs-github-login')
                ->namespace('Tacraft\TcsGithubLogin\Controllers')
                ->middleware(['web', 'auth', 'role:admin'])
                ->group(function () {
                    Route::any('', 'GithubAdminController@config');
                });
        });
    } catch (\Throwable $e) {
        report($e);
    }
};
