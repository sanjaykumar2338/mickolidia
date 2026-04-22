@props([
    'title',
    'message',
    'meta' => [],
    'ctaHref' => null,
    'ctaLabel' => null,
])

<div class="rounded-3xl border border-amber-400/25 bg-amber-400/10 p-5 text-amber-50 shadow-lg shadow-amber-950/10">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-200">{{ $title }}</p>
            <p class="mt-3 text-base leading-7 text-amber-50">{{ $message }}</p>

            @if (filled($ctaHref) && filled($ctaLabel))
                <a
                    href="{{ $ctaHref }}"
                    class="primary-cta start-challenge-btn mt-5 inline-flex rounded-full px-6 py-3 text-sm font-semibold"
                >
                    {{ $ctaLabel }}
                </a>
            @endif
        </div>

        @if ($meta !== [])
            <div class="grid gap-2 text-sm text-amber-100 sm:grid-cols-3 lg:min-w-[24rem]">
                @foreach ($meta as $item)
                    <div class="rounded-2xl border border-amber-200/10 bg-black/15 px-4 py-3">
                        {{ $item }}
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
