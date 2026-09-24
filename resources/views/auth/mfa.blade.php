<x-layouts.guest title="Verification">
    <form method="POST" action="{{ route('mfa.verify') }}">
        @csrf
        <p class="mb-3 text-sm text-stone-600">Remote access requires a second factor. Enter the 6-digit code from your authenticator app.</p>
        @unless ($available)
            <div class="mb-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">The API has no MFA verification endpoint yet. Sign-in stays blocked until it does (fail-closed).</div>
        @endunless
        <x-field name="code" label="Verification code" required autofocus placeholder="000000" />
        <x-btn class="mt-2 w-full">Verify</x-btn>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">@csrf<button class="text-xs text-stone-500 underline">Cancel and sign out</button></form>
</x-layouts.guest>
