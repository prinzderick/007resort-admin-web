@extends('layouts.app')

@section('title', 'Dashboard - '.config('app.name'))

@section('content')
    <h1 class="text-2xl font-semibold">Management dashboard</h1>
    <p class="mt-2 text-stone-600">
        Placeholder. Reporting, finance, inventory oversight, staff and configuration screens
        will be built here on top of the Otueke API.
    </p>

    <section class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="Planned modules">
        @foreach (['Reports', 'Finance & reconciliation', 'Inventory oversight', 'Staff & roles', 'Facilities & operating points', 'Configuration'] as $module)
            <div class="rounded-lg border border-stone-200 bg-white p-4">
                <h2 class="font-medium">{{ $module }}</h2>
                <p class="mt-1 text-sm text-stone-500">Not yet implemented.</p>
            </div>
        @endforeach
    </section>

    <p class="mt-8 text-xs text-stone-500">
        Reporting hierarchy: Property &rarr; Facility &rarr; Operating Point &rarr; Terminal &rarr; Staff &rarr; Transaction
    </p>
@endsection
