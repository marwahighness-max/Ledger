<?php

namespace Tests\Unit;

use App\Services\LedgerEngine;
use PHPUnit\Framework\TestCase;

class UnoBankFixtureTest extends TestCase
{
    public function test_engine_calculates_balances_for_synthetic_steps(): void
    {
        $engine = new LedgerEngine();

        // Synthetic test data only.
        // This is not the official UnoBank fixture.
        $steps = [
            ['id' => 's1', 'give' => ['v' => 5], 'ask' => ['weight' => 1]],
            ['id' => 's2', 'give' => ['v' => 0], 'ask' => null],
            ['id' => 's3', 'give' => ['v' => 0], 'ask' => ['weight' => 3]],
            ['id' => 's4', 'give' => ['v' => 0], 'ask' => ['weight' => 3]],
            ['id' => 's5', 'give' => ['v' => 0], 'ask' => ['weight' => 3]],
            ['id' => 's6', 'give' => ['v' => 0], 'ask' => ['weight' => 4]],
            ['id' => 's7', 'give' => ['v' => 0], 'ask' => ['weight' => 3]],
            ['id' => 's8', 'give' => ['v' => 6], 'ask' => null],
            ['id' => 's9', 'give' => ['v' => 2], 'ask' => ['weight' => 4]],
        ];

        $result = $engine->run($steps);

        $this->assertSame(
            [4, 4, 1, -2, -5, -9, -12, -6, -8],
            array_column($result['steps'], 'after')
        );
    }
}
