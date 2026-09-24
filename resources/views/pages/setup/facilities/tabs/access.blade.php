<x-card title="Who can work here" subtitle="People whose role covers this facility: assigned to it directly, or inherited from the whole site or organization" flush>
    <x-fetch :of="$access" what="Staff" />
    @if ($access->ok())
        <div class="table-scroll"><table class="data-table" data-testid="access-table"><thead><tr><th>Person</th><th>Role</th><th>Access</th><th></th></tr></thead><tbody>
        @forelse ($rows as $r)
            <tr><td class="font-medium">{{ $r['name'] }}<div class="text-xs font-normal text-stone-500">{{ $r['number'] }}</div></td><td>{{ $r['role'] }}</td>
                <td><x-badge :tone="$r['inherited'] ? 'default' : 'info'">{{ $r['inherited'] ? 'Inherited ('.strtolower($r['scope']).'-wide)' : 'This facility' }}</x-badge></td>
                <td><a class="text-sm underline" href="{{ route('staff.show', $r['staffId']) }}">Manage</a></td></tr>
        @empty<tr><td colspan="4"><x-empty title="Nobody has access yet" text="Give someone a role at this facility on their staff page." icon="user" /></td></tr>@endforelse
        </tbody></table></div>
    @endif
</x-card>
