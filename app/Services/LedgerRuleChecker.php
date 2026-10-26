<?php

namespace App\Services;

class LedgerRuleChecker
{
    private LedgerEngine $engine;

    public function __construct(?LedgerEngine $engine = null)
    {
        $this->engine = $engine ?? new LedgerEngine();
    }

    public function check(
        array $steps,
        array $run,
        array $plan = [],
        array $config = []
    ): array {
        $resultsById = [];

        foreach ($run['steps'] ?? [] as $result) {
            if (!isset($result['id'])) {
                continue;
            }

            $resultsById[(string) $result['id']] = $result;
        }

        $findings = [];

        foreach ($steps as $step) {
            $id = (string) ($step['id'] ?? '');
            $ask = $step['ask'] ?? null;

            if ($id === '' || !is_array($ask)) {
                continue;
            }

            $result = $resultsById[$id] ?? null;

            if (!is_array($result)) {
                continue;
            }

            $before = (int) ($result['before'] ?? 0);
            $cost = (int) ($result['cost'] ?? 0);
            $weight = $this->engine->weightOf($step, $config);

            $reason = !empty($plan['reason'][$id])
                || !empty($ask['reason']);

            $exit = !empty($plan['exit'][$id])
                || !empty($ask['exit']);

            $defaultOn = !empty($ask['defaultOn'])
                && empty($plan['defOff'][$id]);

            $proxy = $plan["proxy:{$id}"]
                ?? $plan['proxy'][$id]
                ?? $ask['proxy']
                ?? null;

            /*
             * Sequencing
             */
            if ($cost > $before) {
                $findings[] = $this->finding(
                    key: "{$id}.seq",
                    id: $id,
                    check: 'Sequencing',
                    severity: 'High',
                    note: 'The ask costs more than the balance available before it.',
                    hesitation: 'WHAT DO I GET FOR THIS?',
                    options: ['move_after_home', 'boost', 'keep']
                );
            }

            /*
             * Justification
             */
            if (!$reason && $weight >= 2) {
                $findings[] = $this->finding(
                    key: "{$id}.justification",
                    id: $id,
                    check: 'Justification',
                    severity: 'Medium',
                    note: 'This ask has a weight of at least 2 but no recorded reason.',
                    hesitation: 'WHY DOES IT NEED THAT?',
                    options: ['add_reason', 'keep']
                );
            }

            /*
             * Off-switch
             *
             * An explicitly false exit, or a missing exit field,
             * means that no opt-out mechanism is recorded.
             */
            if (!$exit) {
                $findings[] = $this->finding(
                    key: "{$id}.exit",
                    id: $id,
                    check: 'Off-switch',
                    severity: 'Medium',
                    note: 'No available opt-out mechanism is recorded for this ask.',
                    hesitation: 'CAN I TURN IT OFF LATER?',
                    options: ['add_exit', 'keep']
                );
            }

            /*
             * Default state
             */
            if ($defaultOn) {
                $findings[] = $this->finding(
                    key: "{$id}.default",
                    id: $id,
                    check: 'Default state',
                    severity: 'Medium',
                    note: 'This option is enabled by default.',
                    hesitation: 'EVERYONE DOES THIS, RIGHT?',
                    options: ['default_off', 'keep']
                );
            }

            /*
             * Measurability
             */
            if ($weight >= 3 && !$this->hasProxy($proxy)) {
                $findings[] = $this->finding(
                    key: "{$id}.measurability",
                    id: $id,
                    check: 'Measurability',
                    severity: 'Low',
                    note: 'This high-weight ask has no proxy signal recorded.',
                    hesitation: 'WOULD THE METRICS EVER SHOW IT?',
                    options: ['add_proxy', 'keep']
                );
            }
        }

        $severityOrder = [
            'High' => 0,
            'Medium' => 1,
            'Low' => 2,
        ];

        usort(
            $findings,
            static fn (array $a, array $b): int =>
                ($severityOrder[$a['severity']] ?? 99)
                <=>
                ($severityOrder[$b['severity']] ?? 99)
        );

        return $findings;
    }

    private function hasProxy(mixed $proxy): bool
    {
        if (is_string($proxy)) {
            return trim($proxy) !== '';
        }

        return is_array($proxy) && $proxy !== [];
    }

    private function finding(
        string $key,
        string $id,
        string $check,
        string $severity,
        string $note,
        string $hesitation,
        array $options
    ): array {
        return [
            'key' => $key,
            'id' => $id,
            'check' => $check,
            'severity' => $severity,
            'note' => $note,
            'hesitation' => $hesitation,
            'source' => 'rule',
            'cleared' => false,
            'options' => $options,
            'cites' => [],
            'xref' => null,
        ];
    }
}

