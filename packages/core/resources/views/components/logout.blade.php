{{-- Sign-out button (POST to Fortify's logout). --}}
<form method="POST" action="{{ route('logout') }}" {{ $attributes }}>
    @csrf
    <x-codeart::button variant="ghost">{{ $slot->isEmpty() ? __('codeart::ui.logout') : $slot }}</x-codeart::button>
</form>
