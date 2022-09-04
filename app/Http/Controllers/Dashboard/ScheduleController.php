<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CachetHQ\Cachet\Http\Controllers\Dashboard;

use AltThree\Validator\ValidationException;
use CachetHQ\Cachet\Bus\Commands\Incident\CreateIncidentCommand;
use CachetHQ\Cachet\Bus\Commands\Schedule\CreateScheduleCommand;
use CachetHQ\Cachet\Bus\Commands\Schedule\DeleteScheduleCommand;
use CachetHQ\Cachet\Bus\Commands\Schedule\UpdateScheduleCommand;
use CachetHQ\Cachet\Integrations\Contracts\System;
use CachetHQ\Cachet\Models\Incident;
use CachetHQ\Cachet\Models\IncidentTemplate;
use CachetHQ\Cachet\Models\Schedule;
use GrahamCampbell\Binput\Facades\Binput;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;

/**
 * This is the schedule controller class.
 *
 * @author James Brooks <james@alt-three.com>
 */
class ScheduleController extends Controller
{
    /**
     * Stores the sub-sidebar tree list.
     *
     * @var array
     */
    protected $subMenu = [];

    /**
     * The system instance.
     *
     * @var \CachetHQ\Cachet\Integrations\Contracts\System
     */
    protected $system;

    /**
     * Creates a new schedule controller instance.
     *
     * @return void
     */
    public function __construct(System $system)
    {
        $this->system = $system;
        View::share('subTitle', trans('dashboard.schedule.title'));
    }

    /**
     * Lists all scheduled maintenance.
     *
     * @return \Illuminate\View\View
     */
    public function showIndex()
    {
        $schedule = Schedule::orderBy('created_at')->get();

        return View::make('dashboard.maintenance.index')
            ->withPageTitle(trans('dashboard.schedule.schedule').' - '.trans('dashboard.dashboard'))
            ->withSchedule($schedule);
    }

    /**
     * Shows the add schedule maintenance form.
     *
     * @return \Illuminate\View\View
     */
    public function showAddSchedule()
    {
        $incidentTemplates = IncidentTemplate::all();

        return View::make('dashboard.maintenance.add')
            ->withPageTitle(trans('dashboard.schedule.add.title').' - '.trans('dashboard.dashboard'))
            ->withIncidentTemplates($incidentTemplates)
            ->withNotificationsEnabled($this->system->canNotifySubscribers());
    }

    /**
     * Creates a new scheduled maintenance.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addScheduleAction()
    {
        try {
            execute(new CreateScheduleCommand(
                Binput::get('name'),
                Binput::get('message', null, false, false),
                Binput::get('status', Schedule::UPCOMING),
                Binput::get('scheduled_at'),
                Binput::get('completed_at'),
                Binput::get('components', []),
                Binput::get('notify', false)
            ));

            $data = [
                'user_id'  => 1,
                'name'     => Binput::get('name'),
                'message'  => Binput::get('message', null, false, false),
                'status'   => 4,
                'notify'   => 0,
                'visible'  => 1,
                'stickied' => false,
                'component_id' => 0
            ];
        } catch (ValidationException $e) {
            return cachet_redirect('dashboard.schedule.create')
                ->withInput(Binput::all())
                ->withTitle(sprintf('%s %s', trans('dashboard.notifications.whoops'), trans('dashboard.schedule.edit.failure')))
                ->withErrors($e->getMessageBag());
        }

        try {
            $incident = execute(new CreateIncidentCommand(
                Binput::get('name'),
                4, // status
                Binput::get('message', null, false, false),
                1, // visible
                0, // component_id
                4, // component_status
                0, // notify
                0, // stickied
                Binput::get('occurred_at',Binput::get('scheduled_at')),
                null,
                [],
                ['seo' => Binput::get('seo', [])]
            ));
        } catch (ValidationException $e) {
            return cachet_redirect('dashboard.incidents.create')
                ->withInput(Binput::all())
                ->withTitle(sprintf('%s %s', trans('dashboard.notifications.whoops'), trans('dashboard.incidents.add.failure')))
                ->withErrors($e->getMessageBag());
        }

        return cachet_redirect('dashboard.schedule')
            ->withSuccess(sprintf('%s %s', trans('dashboard.notifications.awesome'), trans('dashboard.schedule.add.success')));
    }

    /**
     * Shows the edit schedule maintenance form.
     *
     * @param \CachetHQ\Cachet\Models\Schedule $schedule
     *
     * @return \Illuminate\View\View
     */
    public function showEditSchedule(Schedule $schedule)
    {
        $incidentTemplates = IncidentTemplate::all();

        return View::make('dashboard.maintenance.edit')
            ->withPageTitle(trans('dashboard.schedule.edit.title').' - '.trans('dashboard.dashboard'))
            ->withIncidentTemplates($incidentTemplates)
            ->withSchedule($schedule);
    }

    /**
     * Updates the given incident.
     *
     * @param \CachetHQ\Cachet\Models\Schedule $schedule
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function editScheduleAction(Schedule $schedule)
    {
        try {
            $schedule = execute(new UpdateScheduleCommand(
                $schedule,
                Binput::get('name', null),
                Binput::get('message', null),
                Binput::get('status', null),
                Binput::get('scheduled_at', null),
                Binput::get('completed_at', null),
                Binput::get('components', [])
            ));
        } catch (ValidationException $e) {
            return cachet_redirect('dashboard.schedule.edit', [$schedule->id])
                ->withInput(Binput::all())
                ->withTitle(sprintf('%s %s', trans('dashboard.notifications.whoops'), trans('dashboard.schedule.edit.failure')))
                ->withErrors($e->getMessageBag());
        }

        return cachet_redirect('dashboard.schedule.edit', [$schedule->id])
            ->withSuccess(sprintf('%s %s', trans('dashboard.notifications.awesome'), trans('dashboard.schedule.edit.success')));
    }

    /**
     * Deletes a given schedule.
     *
     * @param \CachetHQ\Cachet\Models\Schedule $schedule
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteScheduleAction(Schedule $schedule)
    {
        execute(new DeleteScheduleCommand($schedule));

        return cachet_redirect('dashboard.schedule')
            ->withSuccess(sprintf('%s %s', trans('dashboard.notifications.awesome'), trans('dashboard.schedule.delete.success')));
    }
}
