@props(['href' => '#'])
<a href="{{ $href }}" {{ $attributes->class('rounded-sm font-medium text-link underline underline-offset-4 hover:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus') }}>{{ $slot }}</a>
