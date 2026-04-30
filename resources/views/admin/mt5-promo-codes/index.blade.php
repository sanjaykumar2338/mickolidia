@extends('admin.layout')

@section('title', 'MT5 Promo Codes | Wolforix Admin')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="section-label">FusionMarkets giveaway</p>
            <h1 class="mt-4 text-3xl font-semibold text-white">MT5 Promo Codes</h1>
        </div>

        <div class="overflow-hidden rounded-[1.6rem] border border-white/8 bg-white/4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/8 text-left text-sm">
                    <thead class="bg-white/5 text-xs uppercase tracking-[0.2em] text-slate-400">
                        <tr>
                            <th class="px-5 py-4">Code</th>
                            <th class="px-5 py-4">Linked login</th>
                            <th class="px-5 py-4">Account size</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Used by</th>
                            <th class="px-5 py-4">Order</th>
                            <th class="px-5 py-4">Used at</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/6">
                        @forelse ($promoCodes as $promoCode)
                            <tr class="text-slate-200">
                                <td class="px-5 py-4 font-semibold text-white">{{ $promoCode->code }}</td>
                                <td class="px-5 py-4">{{ $promoCode->mt5_login }}</td>
                                <td class="px-5 py-4">
                                    {{ $promoCode->poolEntry ? '$'.number_format((int) $promoCode->poolEntry->account_size) : 'Missing account' }}
                                </td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $promoCode->used_at ? 'border-amber-300/20 bg-amber-400/10 text-amber-100' : 'border-emerald-300/20 bg-emerald-400/10 text-emerald-100' }}">
                                        {{ $promoCode->used_at ? 'Used' : 'Not used' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">{{ $promoCode->usedByUser?->email ?? '-' }}</td>
                                <td class="px-5 py-4">{{ $promoCode->usedOrder?->order_number ?? '-' }}</td>
                                <td class="px-5 py-4">{{ $promoCode->used_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-slate-400">No MT5 promo codes have been created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
