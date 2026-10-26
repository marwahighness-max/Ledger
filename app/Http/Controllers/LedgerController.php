<?php

namespace App\Http\Controllers;

use App\Services\LedgerEngine;
use App\Services\LedgerRuleChecker;
use App\Services\LedgerScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LedgerController extends Controller
{
    private function steps(): array
    {
        return [
            [
                'id' => 'home',
                'give' => ['v' => 4],
            ],
            [
                'id' => 'account',
                'give' => ['v' => 0],
                'ask' => [
                    'type' => 'data',
                    'weight' => 2,
                ],
            ],
            [
                'id' => 'notifications',
                'give' => ['v' => 0],
                'ask' => [
                    'type' => 'permission',
                    'weight' => 3,
                    'defaultOn' => true,
                ],
            ],
            [
                'id' => 'identity',
                'give' => ['v' => 0],
                'ask' => [
                    'type' => 'biometric',
                    'weight' => 5,
                ],
            ],
        ];
    }

    private function config(): array
    {
        return [
            'project' => [
                'baseline' => 0,
            ],
        ];
    }

    public function index(
        Request $request,
        LedgerEngine $engine,
        LedgerRuleChecker $ruleChecker,
        LedgerScoreCalculator $scoreCalculator
    ): View {
        $steps = $this->steps();
        $config = $this->config();
        $plan = $request->session()->get('ledger_plan', []);

        $run = $engine->run($steps, $plan, $config);

        $findings = $ruleChecker->check(
            $steps,
            $run,
            $plan,
            $config
        );

        $score = $scoreCalculator->calculate(
            $run,
            $steps,
            $findings,
            $plan,
            [],
            $config
        );

        return view('ledger.index', [
            'steps' => $steps,
            'run' => $run,
            'findings' => $findings,
            'score' => $score,
            'plan' => $plan,
        ]);
    }

    public function updatePlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'step_id' => [
                'required',
                'in:account,notifications,identity',
            ],
            'action' => [
                'required',
                'in:move_after_home,boost,add_reason,add_exit,default_off,add_proxy,keep',
            ],
        ]);

        $stepId = $validated['step_id'];
        $action = $validated['action'];

        $plan = $request->session()->get('ledger_plan', []);

        switch ($action) {
            case 'move_after_home':
                $plan['order'] = ['home', $stepId];

                foreach ($this->steps() as $step) {
                    $id = $step['id'];

                    if ($id !== 'home' && $id !== $stepId) {
                        $plan['order'][] = $id;
                    }
                }

                break;

            case 'boost':
                $plan['boost'][$stepId] = true;
                break;

            case 'add_reason':
                $plan['reason'][$stepId] = true;
                break;

            case 'add_exit':
                $plan['exit'][$stepId] = true;
                break;

            case 'default_off':
                $plan['defOff'][$stepId] = true;
                break;

            case 'add_proxy':
                $plan['proxy'][$stepId] = 'engagement_rate';
                break;

            case 'keep':
                break;
        }

        $request->session()->put('ledger_plan', $plan);

        return redirect()
            ->route('ledger.index')
            ->with('status', 'Decision applied successfully.');
    }
}
