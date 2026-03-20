@props([
    'label',
    'value',
    'hint' => null,
])

<div class="surface-card rounded-3xl p-5">
    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">{{ $label }}</p>
    <p class="mt-4 text-3xl font-semibold text-white">{{ $value }}</p>

    @if ($hint)
        <p class="mt-3 text-sm leading-6 text-slate-400">{{ $hint }}</p>
    @endif
</div>
