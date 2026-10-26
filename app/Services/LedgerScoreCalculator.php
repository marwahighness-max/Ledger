<?php

namespace App\Services;

class LedgerScoreCalculator
{
    private LedgerEngine $engine;

    public function __construct(?LedgerEngine $engine = null)
    {
        $this->engine = $engine ?? new LedgerEngine();
    }

    public function calculate(
        array $asBuiltRun,
        array $steps,
        array $findings = [],
        array $decisions = [],
        array $plan = [],
        array $config = []
    ): array {
        $runSteps = $asBuiltRun['steps'] ?? [];

        $exchange = $this->exchangeRatio($runSteps);
        $decisionsCoverage = $this->decisionRatio(
            $findings,
            $decisions
        );
        $measured = $this->measuredRatio($steps, $plan, $config);

    
        $rawScore = (int) round(
        100 * (
            0.60 * $exchange
            + 0.25 * $decisionsCoverage
            + 0.15 * $measured
        )
    );


        $stepsInDebt = count(array_filter(
            $runSteps,
            static fn (array $step): bool =>
                (int) ($step['after'] ?? 0) < 0
        ));

        $score = $stepsInDebt > 0
            ? min(79, $rawScore)
            : $rawScore;

        return [
            'score' => $score,
            'raw_score' => $rawScore,
            'zone' => $this->zone($score),
            'exchange' => round($exchange, 4),
            'decisions' => round($decisionsCoverage, 4),
            'measured' => round($measured, 4),
            'steps_in_debt' => $stepsInDebt,
            'capped' => $stepsInDebt > 0,
        ];
    }

    private function exchangeRatio(array $runSteps): float
    {
        $asks = array_values(array_filter(
            $runSteps,
            static fn (array $step): bool =>
                (int) ($step['cost'] ?? 0) > 0
        ));

        if ($asks === []) {
            return 1.0;
        }

        $affordable = count(array_filter(
            $asks,
            static fn (array $step): bool =>
                (int) ($step['cost'] ?? 0)
                <= (int) ($step['before'] ?? 0)
        ));

        return $affordable / count($asks);
    }

    private function decisionRatio(
        array $findings,
        array $decisions
    ): float {
        if ($findings === []) {
            return 1.0;
        }

        $decisionMap = [];

        foreach ($decisions as $key => $decision) {
            if (!is_array($decision)) {
                continue;
            }

            $findingKey = (string) ($decision['key'] ?? $key);

            if ($findingKey === '') {
                continue;
            }

            $decisionMap[$findingKey] = $decision;
        }

        $decided = 0;
        $total = 0;

        foreach ($findings as $finding) {
            $key = (string) ($finding['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $total++;

            $decision = $decisionMap[$key] ?? null;

            if (
                is_array($decision)
                && (
                    ($decision['state'] ?? '') === 'decided'
                    || !empty($decision['decided'])
                )
            ) {
                $decided++;
            }
        }

        return $total === 0 ? 1.0 : $decided / $total;
    }

    private function measuredRatio(
        array $steps,
        array $plan,
        array $config
    ): float {
        $eligible = [];

        foreach ($steps as $step) {
            if (!is_array($step) || !is_array($step['ask'] ?? null)) {
                continue;
            }

            $weight = $this->engine->weightOf($step, $config);

            if ($weight >= 3) {
                $eligible[] = (string) ($step['id'] ?? '');
            }
        }

        if ($eligible === []) {
            return 1.0;
        }

        $measured = 0;

        foreach ($eligible as $id) {
            $proxy = $plan["proxy:{$id}"]
                ?? $plan['proxy'][$id]
                ?? null;

            if ($this->hasProxy($proxy)) {
                $measured++;
            }
        }

        return $measured / count($eligible);
    }

    private function hasProxy(mixed $proxy): bool
    {
        if (is_string($proxy)) {
            return trim($proxy) !== '';
        }

        return is_array($proxy) && $proxy !== [];
    }

    private function zone(int $score): string
    {
        return match (true) {
            $score <= 39 => 'Overdrawn',
            $score <= 59 => 'Strained',
            $score <= 79 => 'Watch',
            default => 'Earned',
        };
    }
}

