<?php
/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Core\Access;

use Carbon\Carbon;
use Flarum\Core\Discussion;
use Flarum\Core\User;
use Flarum\Event\ScopeHiddenDiscussionVisibility;
use Flarum\Settings\SettingsRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;

class DiscussionPolicy extends AbstractPolicy
{
    /**
     * {@inheritdoc}
     */
    protected $model = Discussion::class;

    /**
     * @var SettingsRepository
     */
    protected $settings;

    /**
     * @var Gate
     */
    protected $gate;

    /**
     * @var Dispatcher
     */
    protected $events;

    /**
     * @param SettingsRepository $settings
     * @param Gate $gate
     */
    public function __construct(SettingsRepository $settings, Gate $gate, Dispatcher $events)
    {
        $this->settings = $settings;
        $this->gate = $gate;
        $this->events = $events;
    }

    /**
     * @param User $actor
     * @param string $ability
     * @return bool|null
     */
    public function before(User $actor, $ability)
    {
        if ($actor->hasPermission('discussion.'.$ability)) {
            return true;
        }
    }

    /**
     * @param User $actor
     * @param Builder $query
     */
    public function find(User $actor, Builder $query)
    {
        if (! $actor->hasPermission('discussion.hide')) {
            $query->where(function ($query) use ($actor) {
                $query->whereNull('discussions.hide_time')
                    ->where('comments_count', '>', 0)
                    ->orWhere('start_user_id', $actor->id);

                $this->events->fire(
                    new ScopeHiddenDiscussionVisibility($query, $actor, 'discussion.hide')
                );
            });
        }
    }

    /**
     * @param User $actor
     * @param Discussion $discussion
     * @return bool|null
     */
    public function rename(User $actor, Discussion $discussion)
    {
        if ($discussion->start_user_id == $actor->id) {
            $allowRenaming = $this->settings->get('allow_renaming');

            if ($allowRenaming === '-1'
                || ($allowRenaming === 'reply' && $discussion->participants_count <= 1)
                || ($discussion->start_time->diffInMinutes(new Carbon) < $allowRenaming)) {
                return true;
            }
        }
    }

    /**
     * @param User $actor
     * @param Discussion $discussion
     * @return bool|null
     */
    public function delete(User $actor, Discussion $discussion)
    {
        return $this->rename($actor, $discussion);
    }
}
