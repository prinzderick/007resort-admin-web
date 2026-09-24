<x-layouts.guest title="Sign in">
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <x-field name="identifier" label="Staff number or username" required autofocus />
        <x-field name="password" label="Password" type="password" required />
        <x-btn class="mt-2 w-full">Sign in</x-btn>
        @if (config('r007.mock'))
            <p class="mt-4 rounded-lg bg-fuchsia-50 p-3 text-xs text-fuchsia-900">Mock mode: sign in as <b>owner</b>, <b>manager</b>, <b>accounts</b>, <b>it</b> or <b>cashier</b> with password <b>password</b>.</p>
        @endif
    </form>
</x-layouts.guest>
