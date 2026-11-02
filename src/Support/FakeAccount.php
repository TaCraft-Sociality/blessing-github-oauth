<?php

namespace Tacraft\TcsGithubLogin\Support;

use App\Models\User;
use Tacraft\BlessingOauthCore\Support\FakeEmail;

final class FakeAccount
{
    public const NOTICE_WIDGET = 'Tacraft\TcsGithubLogin::profile-notice';

    /**
     * Cards that make no sense for an account without a local password or a
     * real address of its own.
     */
    public const HIDDEN_WIDGETS = [
        'user.widgets.profile.password',
        'user.widgets.profile.email',
        'user.widgets.profile.delete-account',
    ];

    /**
     * Derive the virtual address of a GitHub account.
     *
     * `$identity` is the numeric GitHub user id, which is global and identical
     * under every GitHub OAuth App. The community uses the same formula, so one
     * GitHub account always resolves to the same skin site account.
     */
    public static function emailFor(string $identity): string
    {
        return FakeEmail::emailFor('github', $identity);
    }

    /**
     * Whether the account was created by an OAuth plugin: those users are
     * stored with an empty password column, while every account registered
     * with a password stores a hash.
     */
    public static function isManaged(User $user): bool
    {
        return (string) $user->password === '';
    }

    public static function isVirtualEmail(string $email): bool
    {
        return FakeEmail::isVirtualEmail($email);
    }

    public static function hasNotice(array $grid): bool
    {
        foreach ($grid['widgets'] ?? [] as $row) {
            foreach ((array) $row as $column) {
                foreach ((array) $column as $widget) {
                    if (is_string($widget) && str_contains($widget, '::profile-notice')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Replace the password / email / delete cards with a short explanation.
     */
    public static function hideCredentialWidgets(array $grid): array
    {
        $widgets = $grid['widgets'] ?? [];
        $placed = false;
        $lastRow = null;
        $lastCol = null;

        foreach ($widgets as $r => $row) {
            foreach ((array) $row as $c => $column) {
                foreach ((array) $column as $k => $widget) {
                    if (! in_array($widget, self::HIDDEN_WIDGETS, true)) {
                        continue;
                    }

                    if ($widget === 'user.widgets.profile.password' && ! $placed) {
                        $widgets[$r][$c][$k] = self::NOTICE_WIDGET;
                        $placed = true;
                    } else {
                        unset($widgets[$r][$c][$k]);
                    }
                }

                $widgets[$r][$c] = array_values((array) ($widgets[$r][$c] ?? []));
                $lastRow = $r;
                $lastCol = $c;
            }
        }

        if (! $placed) {
            if ($lastRow !== null && $lastCol !== null) {
                $widgets[$lastRow][$lastCol][] = self::NOTICE_WIDGET;
            } else {
                $widgets = [[[self::NOTICE_WIDGET]]];

                if (empty($grid['layout'])) {
                    $grid['layout'] = ['md-12'];
                }
            }
        }

        $grid['widgets'] = $widgets;

        return $grid;
    }
}
