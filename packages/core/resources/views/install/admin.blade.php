<x-codeart::install.layout :title="__('codeart::install.admin.title')" step="admin">
    <x-codeart::heading :title="__('codeart::install.admin.title')" :text="__('codeart::install.admin.text')" />
    <form method="POST" action="{{ route('install.admin.store') }}" class="space-y-5">
        @csrf
        <x-codeart::field name="first_name" :label="__('codeart::install.admin.first_name')" :value="old('first_name', $admin['first_name'] ?? '')" autocomplete="given-name" required autofocus />
        <x-codeart::field name="last_name" :label="__('codeart::install.admin.last_name')" :value="old('last_name', $admin['last_name'] ?? '')" autocomplete="family-name" required />
        <x-codeart::field name="email" type="email" :label="__('codeart::install.admin.email')" :value="old('email', $admin['email'] ?? '')" autocomplete="username" required />
        @if ($generated)
            <input type="hidden" name="generated" value="1">
            <x-codeart::alert type="warning" :title="__('codeart::install.admin.generated')">
                <p>{{ __('codeart::install.admin.generated_text') }}</p>
                <p class="mt-2 flex flex-wrap items-center gap-3"><code class="select-all break-all font-mono text-base" data-generated-password>{{ $generated }}</code>
                    <x-codeart::copy-button :value="$generated" /></p>
            </x-codeart::alert>
            <input type="hidden" name="password" value="{{ $generated }}">
        @else
            <x-codeart::field name="password" type="password" :label="__('codeart::install.admin.password')" :hint="collect($rules)->pluck('label')->implode(', ')" autocomplete="new-password" required />
            <x-codeart::field name="password_confirmation" type="password" :label="__('codeart::install.admin.confirmation')" autocomplete="new-password" required />
            <x-codeart::button variant="secondary" :href="route('install.admin', ['generate' => 1])" icon="key-round">{{ __('codeart::install.admin.generate') }}</x-codeart::button>
        @endif
        <x-codeart::button icon="arrow-right">{{ $generated ? __('codeart::install.admin.use_generated') : __('codeart::install.continue') }}</x-codeart::button>
    </form>
</x-codeart::install.layout>
