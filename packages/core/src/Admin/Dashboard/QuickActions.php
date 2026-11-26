<?php

namespace Codeart\Core\Admin\Dashboard;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/** Quick actions registered by core and modules; collected into one widget. */
class QuickActions
{
    /** @var array<string, QuickAction> */
    private array $actions = [];

    public function __construct(private readonly Gate $gate) {}

    public function add(QuickAction ...$actions): void
    {
        foreach ($actions as $action) {
            $this->actions[$action->key] = $action;
        }
    }

    /** @return list<QuickAction> allowed for the user, whose route exists, by order */
    public function visible(?Authenticatable $user): array
    {
        $actions = array_filter($this->actions, fn (QuickAction $a): bool => Route::has($a->route)
            && ($a->permission === null || ($user !== null && $this->gate->forUser($user)->allows($a->permission))));
        usort($actions, fn (QuickAction $a, QuickAction $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        return $actions;
    }
}
