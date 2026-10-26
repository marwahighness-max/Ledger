<?php

namespace Tests\Unit;

use App\Services\LedgerEngine;
use PHPUnit\Framework\TestCase;

class LedgerEngineTest extends TestCase
{
    public function test_it_calculates_balance_and_decisions(): void
    {
        $engine = new LedgerEngine();

        $steps = [
            ['id' => 'a', 'give' => ['v' => 5], 'ask' => ['weight' => 1]],
            ['id' => 'b', 'give' => ['v' => 2], 'ask' => ['weight' => 3]],
            ['id' => 'c', 'give' => ['v' => 0], 'ask' => ['weight' => 2]],
        ];

        $result = $engine->run($steps);

        $this->assertSame([4, 3, 1], array_column($result['steps'], 'after'));
        $this->assertSame(1, $result['final_balance']);

        $plan = [
            'boost' => ['c' => true],
            'reason' => ['b' => true],
        ];

        $resultWithPlan = $engine->run($steps, $plan);

        $this->assertSame(4, $resultWithPlan['final_balance']);
    }
}


