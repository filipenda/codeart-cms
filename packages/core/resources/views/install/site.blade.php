<x-codeart::install.layout :title="__('codeart::install.site.title')" step="site">
    <x-codeart::heading :title="__('codeart::install.site.title')" />
    <form method="POST" action="{{ route('install.site.store') }}" class="space-y-5">
        @csrf
        <x-codeart::field name="site_name" :label="__('codeart::install.site.name')" :value="old('site_name', $site['site_name'])" required autofocus />
        <x-codeart::field name="url" type="url" :label="__('codeart::install.site.url')" :hint="__('codeart::install.site.url_hint')" :value="old('url', $site['url'])" required />
        <x-codeart::select name="timezone" :label="__('codeart::install.site.timezone')" :value="old('timezone', $site['timezone'])" :options="array_combine($timezones, $timezones)" />
        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">{{ __('codeart::install.site.locales') }}</legend>
            @foreach ($locales as $code => $info)
                <x-codeart::checkbox :id="'locale-'.$code" name="locales[]" :value="$code" :label="$info['label'] ?? $code" :checked="in_array($code, old('locales', $site['locales']), true)" />
            @endforeach
            @error('locales') <p class="text-sm font-medium text-danger-text">{{ $message }}</p> @enderror
        </fieldset>
        <x-codeart::select name="default_locale" :label="__('codeart::install.site.default_locale')" :value="old('default_locale', $site['default_locale'])" :options="collect($locales)->map(fn ($l) => $l['label'] ?? '')->all()" />
        <x-codeart::field name="admin_path" :label="__('codeart::install.site.admin_path')" :hint="__('codeart::install.site.admin_path_hint')" :value="old('admin_path', $site['admin_path'])" required />
        <x-codeart::button icon="arrow-right">{{ __('codeart::install.continue') }}</x-codeart::button>
    </form>
</x-codeart::install.layout>
