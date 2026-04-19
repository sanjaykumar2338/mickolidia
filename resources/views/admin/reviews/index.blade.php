@extends('admin.layout')

@section('title', 'Trustpilot Review Emails | '.__('site.meta.brand'))

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <span class="section-label">Admin testing</span>
            <h1 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">Trustpilot review emails</h1>
            <p class="mt-4 max-w-3xl text-base leading-8 text-slate-300">
                Monitor initial review requests, reminder timing, and send controlled test emails without opening production shells.
            </p>
        </div>
        <div class="gold-pill rounded-full px-4 py-2 text-sm font-medium">
            {{ number_format($summary['tracked_accounts']) }} tracked accounts
        </div>
    </div>

    <div class="mt-10 grid gap-4 md:grid-cols-4">
        <div class="surface-panel rounded-[1.6rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Initial sent</p>
            <p class="mt-3 text-3xl font-semibold text-white">{{ number_format($summary['initial_sent']) }}</p>
        </div>
        <div class="surface-panel rounded-[1.6rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Reminders due</p>
            <p class="mt-3 text-3xl font-semibold text-white">{{ number_format($summary['reminders_due']) }}</p>
        </div>
        <div class="surface-panel rounded-[1.6rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Reminders sent</p>
            <p class="mt-3 text-3xl font-semibold text-white">{{ number_format($summary['reminders_sent']) }}</p>
        </div>
        <div class="surface-panel rounded-[1.6rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Automation</p>
            <p class="mt-3 text-lg font-semibold text-white">{{ $config['enabled'] ? 'Enabled' : 'Disabled' }}</p>
            <p class="mt-1 text-sm text-slate-400">
                Reminder: {{ $config['reminder_enabled'] ? $config['reminder_delay_days'].' days' : 'disabled' }}
            </p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
        <section class="surface-panel rounded-[2rem] p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-amber-300">Manual test send</p>
            <p class="mt-3 text-sm leading-7 text-slate-400">
                Sends the same neutral Trustpilot review request template to a test inbox. This does not mark any client account as contacted.
            </p>

            <form method="POST" action="{{ route('admin.reviews.test') }}" class="mt-6 grid gap-4 sm:grid-cols-[1fr_0.75fr_auto]">
                @csrf
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Email</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="qa@example.com"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-amber-300/50"
                        required
                    >
                    @error('email')
                        <span class="mt-2 block text-xs font-medium text-rose-200">{{ $message }}</span>
                    @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Test name</span>
                    <input
                        type="text"
                        name="trader_name"
                        value="{{ old('trader_name', 'Test Trader') }}"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-amber-300/50"
                    >
                    @error('trader_name')
                        <span class="mt-2 block text-xs font-medium text-rose-200">{{ $message }}</span>
                    @enderror
                </label>

                <button type="submit" class="self-end rounded-2xl border border-amber-400/25 bg-amber-400/12 px-5 py-3 text-sm font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/18">
                    Send test
                </button>
            </form>
        </section>

        <section class="surface-panel rounded-[2rem] p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-amber-300">Reminder control</p>
            <p class="mt-3 text-sm leading-7 text-slate-400">
                The scheduled job runs daily at {{ $config['reminder_schedule_time'] }}. Use this button to send currently due reminders immediately.
            </p>

            <div class="mt-5 rounded-[1.4rem] border border-white/8 bg-slate-950/60 p-4 text-sm text-slate-300">
                <p class="font-semibold text-white">Trustpilot URL</p>
                <p class="mt-2 break-all text-slate-400">{{ $reviewUrl }}</p>
            </div>

            <form method="POST" action="{{ route('admin.reviews.reminders.run') }}" class="mt-5 flex flex-wrap items-end gap-3">
                @csrf
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Scan limit</span>
                    <input
                        type="number"
                        name="limit"
                        min="1"
                        max="500"
                        value="100"
                        class="mt-2 w-28 rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-amber-300/50"
                    >
                </label>
                <button type="submit" class="rounded-2xl border border-sky-400/25 bg-sky-400/10 px-5 py-3 text-sm font-semibold text-sky-100 transition hover:border-sky-300/40 hover:bg-sky-400/16">
                    Run due reminders
                </button>
            </form>
        </section>
    </div>

    <div class="mt-10 surface-panel overflow-hidden rounded-[2rem]">
        <div class="border-b border-white/6 px-6 py-5">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-400">Review request activity</p>
            <p class="mt-2 text-sm text-slate-400">Showing the latest 200 non-trial final-status accounts and their Trustpilot email metadata.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-white/6 text-left text-sm text-slate-300">
                <thead class="bg-white/3 text-xs uppercase tracking-[0.2em] text-slate-400">
                    <tr>
                        <th class="px-6 py-4 font-semibold">Client</th>
                        <th class="px-6 py-4 font-semibold">Recipient</th>
                        <th class="px-6 py-4 font-semibold">Account</th>
                        <th class="px-6 py-4 font-semibold">Trigger</th>
                        <th class="px-6 py-4 font-semibold">Initial request</th>
                        <th class="px-6 py-4 font-semibold">Reminder</th>
                        <th class="px-6 py-4 font-semibold">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/6">
                    @forelse ($reviewRows as $row)
                        <tr class="align-top">
                            <td class="px-6 py-5">
                                <p class="font-semibold text-white">{{ $row['user_name'] }}</p>
                                @if ($row['profile_email'])
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['profile_email'] }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-5">
                                <p class="font-medium text-slate-200">{{ $row['user_email'] }}</p>
                            </td>
                            <td class="px-6 py-5">
                                <p class="font-semibold text-white">{{ $row['account_reference'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['plan'] }}</p>
                                <p class="mt-2 inline-flex rounded-full border border-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-300">
                                    {{ $row['challenge_status'] }}
                                </p>
                            </td>
                            <td class="px-6 py-5">
                                <p class="font-semibold text-white">{{ $row['trigger_source'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['triggered_at'] }}</p>
                            </td>
                            <td class="px-6 py-5">
                                <p class="font-semibold text-white">{{ $row['initial_requested_at'] }}</p>
                            </td>
                            <td class="px-6 py-5">
                                <span class="{{ $row['reminder_status_class'] }} inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]">
                                    {{ $row['reminder_status'] }}
                                </span>
                                <p class="mt-3 text-xs text-slate-500">Due: {{ $row['reminder_due_at'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">Sent: {{ $row['reminder_sent_at'] }}</p>
                            </td>
                            <td class="px-6 py-5 text-xs text-slate-500">{{ $row['updated_at'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                No review request activity is available yet. Final-status challenge accounts will appear here once they pass or fail.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
