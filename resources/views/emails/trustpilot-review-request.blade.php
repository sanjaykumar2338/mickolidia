<x-emails.layout
    :status="$reminder ? __('Review Reminder') : __('Review Request')"
    :title="__('Share your Wolforix experience')"
    :primary-url="$reviewUrl"
    :primary-label="__('Open Trustpilot')"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $traderName }}</strong>, if you would like to share feedback about your Wolforix experience, you can do so on Trustpilot.
    </x-slot:intro>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">
        Optional Feedback
    </p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Your honest review is welcome
    </p>
    <p style="margin:14px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        This request is optional and is sent to eligible users after a completed evaluation. There is no incentive for leaving a review, and we welcome honest feedback whether it highlights what worked well or what could be improved.
    </p>
    <p style="margin:14px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        The same review access is available to all eligible users in this flow. You can ignore this message if you do not want to leave a review.
    </p>

    <x-slot:footer>
        No reward, discount, payout change, account decision, or service access depends on whether you leave a review or what rating you choose.
    </x-slot:footer>
</x-emails.layout>
