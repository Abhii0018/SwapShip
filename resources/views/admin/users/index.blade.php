<x-app-layout>
    <div class="admin-shell">
        @include('admin.partials.hero', [
            'title' => 'Users',
            'subtitle' => 'All buyers and sellers registered on SwapShip.',
        ])
        @include('admin.partials.nav')
        @include('admin.partials.alerts')

        <section class="card admin-panel admin-anim-in admin-anim-delay-2">
            <div class="admin-panel-head">
                <h2>Platform users</h2>
                <span class="admin-meta-pill">{{ $users->total() }} total</span>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr class="admin-table-row">
                                <td>
                                    <span class="admin-table-user">
                                        <span class="admin-avatar admin-avatar-sm">{{ $user->initials() }}</span>
                                        {{ $user->name }}
                                    </span>
                                </td>
                                <td class="muted">{{ $user->email }}</td>
                                <td class="muted">{{ $user->created_at?->format('d M Y') }}</td>
                                <td>
                                    @if($user->isBanned())
                                        <span class="admin-badge admin-badge-danger">Suspended</span>
                                    @else
                                        <span class="admin-badge admin-badge-ok">Active</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted admin-empty">No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $users->links() }}</div>
        </section>
    </div>
</x-app-layout>
