<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ledger — Findings Panel</title>
    <style>
        :root {
            font-family: Arial, sans-serif;
            color: #e8edf5;
            background: #0b1020;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 32px 18px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .eyebrow {
            color: #71e1d1;
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        h1 { margin-bottom: 8px; }

        .muted { color: #98a5ba; }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin: 28px 0;
        }

        .card, .finding, .step {
            background: #141c30;
            border: 1px solid #29354d;
            border-radius: 14px;
            padding: 20px;
        }

        .metric {
            font-size: 30px;
            font-weight: bold;
            margin-top: 10px;
        }

        .section-title { margin: 32px 0 14px; }

        .finding-list, .step-list {
            display: grid;
            gap: 12px;
        }

        .finding-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .badge {
            display: inline-block;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 12px;
            background: #26364b;
        }

        .high { color: #ff929b; }
        .medium { color: #ffd27d; }
        .low { color: #83d9ff; }

        .hesitation {
            color: #71e1d1;
            font-size: 12px;
            letter-spacing: 1px;
            margin-top: 14px;
        }

        .options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .option {
    display: inline-block;
    border: 1px solid #43516b;
    border-radius: 8px;
    padding: 7px 10px;
    color: #dce6f7;
    background: #172238;
    font-size: 12px;
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease;
}

button.option:hover {
    background: #263752;
    border-color: #71e1d1;
}

.options form {
    display: inline-block;
    margin: 0;
}
        .bar {
            height: 7px;
            background: #29354d;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 12px;
        }

        .bar span {
            display: block;
            height: 100%;
            background: #71e1d1;
        }

        .empty {
            border: 1px dashed #43516b;
            border-radius: 12px;
            padding: 24px;
            color: #98a5ba;
        }

        footer {
            margin-top: 36px;
            color: #68778f;
            font-size: 12px;
        }
    </style>
</head>
<body>
<main class="container">
    <p class="eyebrow">Ledger / Product flow analysis</p>
    <h1>Findings Panel</h1>
    <p class="muted">
        Review the asks in your product flow, understand the risks,
        and identify possible improvements.
    </p>

    <section class="summary">
        <article class="card">
            <span class="muted">Ledger Score</span>
            <div class="metric">{{ $score['score'] }}<span class="muted">/100</span></div>
            <strong>{{ $score['zone'] }}</strong>
            <div class="bar">
                <span style="width: {{ $score['score'] }}%"></span>
            </div>
            @if ($score['capped'])
                <p class="muted">Capped at 79: {{ $score['steps_in_debt'] }} step(s) end in debt.</p>
            @endif
        </article>

        <article class="card">
            <span class="muted">Final balance</span>
            <div class="metric">{{ $run['final_balance'] }}</div>
            <span class="muted">After all flow steps</span>
        </article>

        <article class="card">
            <span class="muted">Findings detected</span>
            <div class="metric">{{ count($findings) }}</div>
            <span class="muted">Rule-based checks</span>
        </article>

        <article class="card">
            <span class="muted">Steps in debt</span>
            <div class="metric">{{ $score['steps_in_debt'] }}</div>
            <span class="muted">Steps ending below zero</span>
        </article>
    </section>

    <h2 class="section-title">Detected findings</h2>

    @if (count($findings) > 0)
        <section class="finding-list">
            @foreach ($findings as $finding)
                <article class="finding">
                    <div class="finding-header">
                        <h3>{{ $finding['check'] }} · {{ $finding['id'] }}</h3>
                        <span class="badge {{ strtolower($finding['severity']) }}">
                            {{ $finding['severity'] }} severity
                        </span>
                    </div>

                    <p>{{ $finding['note'] }}</p>

                    <div class="hesitation">
                        {{ $finding['hesitation'] }}
                    </div>

                    <div class="options">
    @foreach ($finding['options'] as $option)
        @if ($option === 'keep')
            <span class="option">
                {{ str_replace('_', ' ', ucfirst($option)) }}
            </span>
        @else
            <form method="POST" action="{{ route('ledger.plan.update') }}">
                @csrf
            <input type="hidden" name="step_id" value="{{ $finding['id'] }}">
            <input type="hidden" name="action" value="{{ $option }}">

            <button type="submit" class="option">
                {{ str_replace('_', ' ', ucfirst($option)) }}
            </button>
        </form>
    @endif
@endforeach


</div>

                </article>
            @endforeach
        </section>
    @else
        <div class="empty">No findings detected for this flow.</div>
    @endif

    <h2 class="section-title">Flow balance</h2>

    <section class="step-list">
        @foreach ($run['steps'] as $step)
            <article class="step">
                <div class="finding-header">
                    <strong>{{ $step['id'] }}</strong>
                    <span class="badge {{ $step['after'] < 0 ? 'high' : 'low' }}">
                        Balance: {{ $step['after'] }}
                    </span>
                </div>

                <p class="muted">
                    Before: {{ $step['before'] }}
                    &nbsp;·&nbsp;
                    Give: +{{ $step['give'] }}
                    &nbsp;·&nbsp;
                    Cost: -{{ $step['cost'] }}
                </p>
            </article>
        @endforeach
    </section>

    <footer>
        Ledger · Demonstration data · Rule-based findings only
    </footer>
</main>
</body>
</html>
