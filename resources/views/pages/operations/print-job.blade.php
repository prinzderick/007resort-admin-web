<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receipt {{ $job['receiptNumber'] ?? '' }}</title>
<style>
    body { font-family: system-ui, sans-serif; margin: 0; padding: 1rem; background: #f6f4ef; color: #1c1917; }
    .bar { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem; }
    button, a.btn { font: inherit; padding: .6rem 1rem; border-radius: .5rem; border: 1px solid #0e5a4b; background: #0e5a4b; color: #fff; cursor: pointer; text-decoration: none; }
    a.btn.alt { background: #fff; color: #0e5a4b; }
    pre { background: #fff; width: 80mm; margin: 0 auto; padding: 4mm; font: 12px/1.35 ui-monospace, Menlo, Consolas, monospace; white-space: pre; border: 1px solid #d6d3d1; }
    @media print { body { background: #fff; padding: 0; } .bar { display: none; } pre { border: 0; width: auto; } }
</style></head>
<body>
    <div class="bar">
        <button type="button" onclick="window.print()">Print</button>
        <form method="POST" action="{{ route('print-job.printed', $job['id']) }}">@csrf<button type="submit" class="alt" style="background:#fff;color:#0e5a4b">I printed it - mark as done</button></form>
        <a class="btn alt" href="{{ route('print-queue') }}">Back to the queue</a>
        <span>Receipt {{ $job['receiptNumber'] ?? '' }} &middot; {{ $job['copies'] ?? 1 }} cop{{ ($job['copies'] ?? 1) > 1 ? 'ies' : 'y' }}</span>
    </div>
    <pre>{{ implode("\n", (array) ($job['receipt']['printLines'] ?? [])) }}</pre>
</body></html>
