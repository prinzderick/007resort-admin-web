<x-layouts.app :title="$title">
    <x-page-header :title="$title" :subtitle="$intro" />
    <x-config-nav />
    <x-pending-api :items="$items" />
</x-layouts.app>
