<?php

namespace Tests\Unit;

use App\Services\LedgerRuleChecker;
use PHPUnit\Framework\TestCase;

class LedgerRuleCheckerTest extends TestCase
{
    private LedgerRuleChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new LedgerRuleChecker();
    }

    public function test_it_detects_sequencing_when_ask_cost_exceeds_balance(): void
    {
        $steps = [
            [
                'id' => 's1',
                'ask' => ['weight' => 3, 'reason' => true],
            ],
        ];

        $run = [
            'steps' => [
                ['id' => 's1', 'before' => 1, 'cost' => 3, 'after' => -2],
            ],
        ];

        $findings = $this->checker->check($steps, $run);

        $this->assertContains(
            's1.seq',
            array_column($findings, 'key')
        );
    }

    public function test_it_detects_missing_justification(): void
    {
        $steps = [
            [
                'id' => 's2',
                'ask' => ['weight' => 2],
            ],
        ];

        $run = [
            'steps' => [
                ['id' => 's2', 'before' => 5, 'cost' => 2, 'after' => 3],
            ],
        ];

        $findings = $this->checker->check($steps, $run);

        $this->assertContains(
            's2.justification',
            array_column($findings, 'key')
        );
    }

    public function test_it_detects_missing_off_switch(): void
    {
        $steps = [
            [
                'id' => 's3',
                'ask' => ['weight' => 1, 'exit' => false],
            ],
        ];

        $run = [
            'steps' => [
                ['id' => 's3', 'before' => 5, 'cost' => 1, 'after' => 4],
            ],
        ];

        $findings = $this->checker->check($steps, $run);

        $this->assertContains(
            's3.exit',
            array_column($findings, 'key')
        );
    }

    public function test_it_detects_enabled_by_default(): void
    {
        $steps = [
            [
                'id' => 's4',
                'ask' => ['weight' => 1, 'defaultOn' => true],
            ],
        ];

        $run = [
            'steps' => [
                ['id' => 's4', 'before' => 5, 'cost' => 2, 'after' => 3],
            ],
        ];

        $findings = $this->checker->check($steps, $run);

        $this->assertContains(
            's4.default',
            array_column($findings, 'key')
        );
    }

    public function test_it_detects_missing_proxy_for_high_weight_ask(): void
    {
        $steps = [
            [
                'id' => 's5',
                'ask' => ['weight' => 3],
            ],
        ];

        $run = [
            'steps' => [
                ['id' => 's5', 'before' => 5, 'cost' => 3, 'after' => 2],
            ],
        ];

        $findings = $this->checker->check($steps, $run);

        $this->assertContains(
            's5.measurability',
            array_column($findings, 'key')
        );
    }

    public function test_findings_are_ordered_by_severity(): void
    {
        $steps = [
            [
                'id' => 's1',
                'ask' => [
                    'weight' => 3,
                    'exit' => false,
                    'defaultOn' => true,
                ],
            ],
        ];

        $run = [
            'steps' => [
                ['id' => 's1', 'before' => 0, 'cost' => 4, 'after' => -4],
            ],
        ];

        $findings = $this->checker->check($steps, $run);

        $this->assertSame(
    ['High', 'Medium', 'Medium', 'Medium', 'Low'],
    array_column($findings, 'severity')
);
    }

public function test_findings_include_required_structure(): void
{
    $steps = [
        [
            'id' => 's6',
            'ask' => ['weight' => 3],
        ],
    ];

    $run = [
        'steps' => [
            ['id' => 's6', 'before' => 0, 'cost' => 3, 'after' => -3],
        ],
    ];

    $findings = $this->checker->check($steps, $run);

    $finding = $findings[0];

    $this->assertArrayHasKey('key', $finding);
    $this->assertArrayHasKey('id', $finding);
    $this->assertArrayHasKey('check', $finding);
    $this->assertArrayHasKey('severity', $finding);
    $this->assertArrayHasKey('note', $finding);
    $this->assertArrayHasKey('hesitation', $finding);
    $this->assertArrayHasKey('source', $finding);
    $this->assertArrayHasKey('options', $finding);
    $this->assertArrayHasKey('cites', $finding);
    $this->assertArrayHasKey('xref', $finding);
    $this->assertSame('rule', $finding['source']);
}

public function test_plan_reason_resolves_justification_finding(): void
{
    $steps = [
        [
            'id' => 's7',
            'ask' => ['weight' => 2],
        ],
    ];

    $run = [
        'steps' => [
            ['id' => 's7', 'before' => 5, 'cost' => 2, 'after' => 3],
        ],
    ];

    $findings = $this->checker->check(
        $steps,
        $run,
        ['reason' => ['s7' => true]]
    );

    $this->assertNotContains(
        's7.justification',
        array_column($findings, 'key')
    );
}

public function test_plan_can_supply_proxy_for_measurability(): void
{
    $steps = [
        [
            'id' => 's8',
            'ask' => ['weight' => 3],
        ],
    ];

    $run = [
        'steps' => [
            ['id' => 's8', 'before' => 5, 'cost' => 3, 'after' => 2],
        ],
    ];

    $findings = $this->checker->check(
        $steps,
        $run,
        ['proxy' => ['s8' => 'completion_rate']]
    );

    $this->assertNotContains(
        's8.measurability',
        array_column($findings, 'key')
    );
}

}
