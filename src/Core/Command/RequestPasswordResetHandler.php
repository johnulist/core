<?php
/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Core\Command;

use Flarum\Settings\SettingsRepository;
use Flarum\Core\PasswordToken;
use Flarum\Core\Repository\UserRepository;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Flarum\Core;
use Flarum\Forum\UrlGenerator;

class RequestPasswordResetHandler
{
    /**
     * @var UserRepository
     */
    protected $users;

    /**
     * @var SettingsRepository
     */
    protected $settings;

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var UrlGenerator
     */
    protected $url;

    /**
     * @param UserRepository $users
     * @param SettingsRepository $settings
     * @param Mailer $mailer
     * @param UrlGenerator $url
     */
    public function __construct(UserRepository $users, SettingsRepository $settings, Mailer $mailer, UrlGenerator $url)
    {
        $this->users = $users;
        $this->settings = $settings;
        $this->mailer = $mailer;
        $this->url = $url;
    }

    /**
     * @param RequestPasswordReset $command
     * @return \Flarum\Core\User
     * @throws ModelNotFoundException
     */
    public function handle(RequestPasswordReset $command)
    {
        $user = $this->users->findByEmail($command->email);

        if (! $user) {
            throw new ModelNotFoundException;
        }

        $token = PasswordToken::generate($user->id);
        $token->save();

        $data = [
            'username' => $user->username,
            'url' => $this->url->toRoute('resetPassword', ['token' => $token->id]),
            'forumTitle' => $this->settings->get('forum_title'),
        ];

        $this->mailer->send(['text' => 'flarum::emails.resetPassword'], $data, function (Message $message) use ($user) {
            $message->to($user->email);
            $message->subject('Reset Your Password');
        });

        return $user;
    }
}
