<?php

namespace App\Services;

class LedgerEngine
{
    public function baseline(array $config = []): int
    {
        return (int) (
            $config['project']['baseline']
            ?? $config['workspace']['baseline']
            ?? $config['plana']['baseline']
            ?? 0
        );
    }

    public function giveOf(array $step, array $config = []): int
    {
        $id = $step['id'] ?? '';

        return (int) (
            $config['project']['steps'][$id]['give']
            ?? $config['workspace']['steps'][$id]['give']
            ?? $step['overrides']['give']
            ?? $step['give']['v']
            ?? 0
        );
    }

    public function weightOf(array $step, array $config = []): int
    {
        $id = $step['id'] ?? '';

        $configuredWeight =
            $config['project']['steps'][$id]['weight']
            ?? $config['workspace']['steps'][$id]['weight']
            ?? $step['overrides']['weight']
            ?? $step['ask']['weight']
            ?? null;

        if ($configuredWeight !== null) {
            return (int) $configuredWeight;
        }

        $ask = $step['ask'] ?? [];
        $type = strtolower((string) ($ask['type'] ?? ''));
        $detail = strtolower((string) (
            $ask['detail']
            ?? $ask['subtype']
            ?? $ask['category']
            ?? ''
        ));

        return match (true) {
            $type === 'attention' && in_array(
                $detail,
                ['one-off', 'one_off', 'oneoff'],
                true
            ) => 1,

            $type === 'attention' => 2,

            $type === 'data' && in_array(
                $detail,
                ['financial', 'finance', 'payment'],
                true
            ) => 3,

            $type === 'data' => 2,

            $type === 'permission' => 3,
            $type === 'belief' => 3,
            $type === 'money' => 4,
            in_array($type, ['identity', 'biometric'], true) => 5,

            default => 1,
        };
    }

    public function cost(
        array $step,
        array $plan = [],
        array $config = []
    ): int {
        $id = $step['id'] ?? '';
        $ask = $step['ask'] ?? null;

        if (
            $ask === null
            || !empty($plan['dropped'][$id])
        ) {
            return 0;
        }

        $weight = $this->weightOf($step, $config);

        $reason = !empty($plan['reason'][$id])
            || !empty($ask['reason']);

        $exit = !empty($plan['exit'][$id])
            || !empty($ask['exit']);

        $defaultOn = !empty($ask['defaultOn'])
            && empty($plan['defOff'][$id]);

        $bundled = !empty($ask['bundled'])
            && empty($plan['unbundled'][$id]);

        return max(
            1,
            $weight
                - (int) $reason
                - (int) $exit
                + (int) $defaultOn
                + (int) $bundled
        );
    }

    public function run(
        array $steps,
        array $plan = [],
        array $config = []
    ): array {
        $baseline = $this->baseline($config);
        $balance = $baseline;
        $results = [];

        if (!empty($plan['order'])) {
            $positions = array_flip($plan['order']);

            usort($steps, function ($a, $b) use ($positions) {
                $positionA = $positions[$a['id'] ?? ''] ?? PHP_INT_MAX;
                $positionB = $positions[$b['id'] ?? ''] ?? PHP_INT_MAX;

                return $positionA <=> $positionB;
            });
        }

        foreach ($steps as $step) {
            $id = $step['id'] ?? '';
            $before = $balance;
            $give = $this->giveOf($step, $config);

            if (!empty($plan['boost'][$id])) {
                $give += 2;
            }

            $cost = $this->cost($step, $plan, $config);
            $balance += $give - $cost;

            $results[] = [
                'id' => $id,
                'before' => $before,
                'give' => $give,
                'cost' => $cost,
                'after' => $balance,
            ];
        }

        return [
            'baseline' => $baseline,
            'steps' => $results,
            'final_balance' => $balance,
        ];
    }
}
