<?php

namespace Tests\Unit;

use App\Services\LedgerScoreCalculator;
use PHPUnit\Framework\TestCase;

class LedgerScoreCalculatorTest extends TestCase
{
    public function test_it_returns_the_earned_zone_when_all_components_are_complete(): void
    {
        $calculator = new LedgerScoreCalculator();

        $result = $calculator->calculate(
            asBuiltRun: [
                'baseline' => 5,
                'steps' => [
                    [
                        'id' => 's1',
                        'before' => 5,
                        'give' => 3,
                        'cost' => 2,
                        'after' => 6,
                    ],
                ],
                'final_balance' => 6,
            ],
            steps: [
                [
                    'id' => 's1',
                    'ask' => [
                        'weight' => 3,
                    ],
                ],
            ],
            findings: [
                ['key' => 's1.justification'],
            ],
            decisions: [
                's1.justification' => [
                    'state' => 'decided',
                    'option' => 'keep',
                    'reason' => 'Required for this flow.',
                ],
            ],
            plan: [
                'proxy:s1' => 'completion_rate',
            ],
        );

        $this->assertSame(100, $result['score']);
        $this->assertSame('Earned', $result['zone']);
        $this->assertSame(0, $result['steps_in_debt']);
    }

    public function test_it_caps_the_score_at_79_when_a_step_ends_in_debt(): void
    {
        $calculator = new LedgerScoreCalculator();

        $result = $calculator->calculate(
            asBuiltRun: [
                'baseline' => 0,
                'steps' => [
                    [
                        'id' => 's1',
                        'before' => 0,
                        'give' => 0,
                        'cost' => 3,
                        'after' => -3,
                    ],
                ],
                'final_balance' => -3,
            ],
            steps: [
                [
                    'id' => 's1',
                    'ask' => ['weight' => 3],
                ],
            ],
            findings: [],
            decisions: [],
            plan: [
                'proxy:s1' => 'completion_rate',
            ],
        );

        $this->assertSame(40, $result['score']);
        $this->assertSame('Strained', $result['zone']);
        $this->assertSame(1, $result['steps_in_debt']);
        $this->assertTrue($result['capped']);
    }

    public function test_it_counts_only_affordable_asks_in_exchange(): void
    {
        $calculator = new LedgerScoreCalculator();

        $result = $calculator->calculate(
            asBuiltRun: [
                'steps' => [
                    [
                        'id' => 's1',
                        'before' => 4,
                        'cost' => 2,
                        'after' => 2,
                    ],
                    [
                        'id' => 's2',
                        'before' => 1,
                        'cost' => 3,
                        'after' => -2,
                    ],
                ],
            ],
            steps: [],
        );

        $this->assertSame(0.5, $result['exchange']);
        $this->assertSame(1, $result['steps_in_debt']);
        $this->assertSame(70, $result['score']);
    }
}