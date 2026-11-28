<x-codeart::install.layout :title="__('codeart::install.db.title')" step="database">
    <x-codeart::heading :title="__('codeart::install.db.title')" :text="__('codeart::install.db.text')" />
    <form method="POST" action="{{ route('install.database.store') }}" class="space-y-5">
        @csrf
        @if ($errors->has('connection')) <x-codeart::alert type="danger">{{ $errors->first('connection') }}</x-codeart::alert> @endif
        <x-codeart::field name="host" :label="__('codeart::install.db.host')" :hint="__('codeart::install.db.host_hint')" :value="old('host', $db['host'])" required />
        <x-codeart::field name="port" type="number" :label="__('codeart::install.db.port')" :value="old('port', $db['port'])" required />
        <x-codeart::field name="database" :label="__('codeart::install.db.database')" :value="old('database', $db['database'])" autocomplete="off" required />
        <x-codeart::field name="username" :label="__('codeart::install.db.username')" :value="old('username', $db['username'])" autocomplete="off" required />
        <x-codeart::field name="password" type="password" :label="__('codeart::install.db.password')" autocomplete="off" />
        @if ($errors->has('confirm_existing') || $existing > 0)
            <x-codeart::alert type="warning">{{ __('codeart::install.db.existing', ['count' => $existing]) }}</x-codeart::alert>
            <x-codeart::checkbox id="confirm-existing" name="confirm_existing" value="1" :label="__('codeart::install.db.existing_check')" />
        @endif
        <x-codeart::button icon="arrow-right">{{ __('codeart::install.db.test') }}</x-codeart::button>
    </form>
</x-codeart::install.layout>
