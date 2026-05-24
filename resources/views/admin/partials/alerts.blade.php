@if (session('success'))
    <div class="nv-alert nv-alert-success admin-anim-in admin-anim-delay-2">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="nv-alert admin-anim-in admin-anim-delay-2" style="border-color:#f87171;color:#fecaca;">{{ session('error') }}</div>
@endif
