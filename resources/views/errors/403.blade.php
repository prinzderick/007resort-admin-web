<x-layouts.app title="Not permitted">
    <x-page-header title="Not permitted" />
    <x-card><p class="text-sm">{{ $exception->getMessage() ?: 'Your account does not have access to this area.' }} Permissions are set by the API; ask an administrator if you need access.</p></x-card>
</x-layouts.app>
