<?php
declare(strict_types=1);
/**
 * PlanningManager Interface
 *
 * Contract for planning and booking: an organisation's time grid, the people
 * and rooms with calendars, rounds of availability collected through personal
 * links, sessions, short-notice fill-in, rates and subscriptions.
 *
 * Deliberately narrow: one dispatch method and one tick. The engine behind it
 * is a composition of single-responsibility managers, which grows without this
 * shape changing. Mirrors gCore's Extensions\PlanningManagerInterface.
 *
 * @optional
 * @package     gCore
 * @subpackage  Contracts\Premium
 * @version     1.0.0
 * @since       3.1.0
 */

namespace gCore\Contracts\Premium;

use gCore\Contracts\Shared\ModuleInterface;

interface PlanningManagerInterface extends ModuleInterface
{
    /**
     * Run one action.
     *
     * @param array $actor ['kind' => 'planner'|'agent'|'system', 'id' => ..., 'session' => ...]
     * @return array ['ok' => bool, 'result' => mixed] or ['ok' => false, 'error' => string, 'code' => string]
     */
    public function handle(string $siteId, string $action, array $input, array $actor): array;

    /** Move time-driven work forward (offer waves, reminders, deadlines). Idempotent. */
    public function tick(string $siteId): array;

    /** The actions this implementation offers. */
    public function actions(): array;
}
