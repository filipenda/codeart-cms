{{-- White card, 480px, 40px padding (24px and full width on phones), logo centered on top. --}}
<div {{ $attributes->class('relative z-10 w-full max-w-120 space-y-8 rounded-card bg-surface p-10 shadow-card max-[480px]:rounded-none max-[480px]:p-6') }}>
    <div class="flex justify-center"><x-codeart::logo /></div>
    {{ $slot }}
</div>
