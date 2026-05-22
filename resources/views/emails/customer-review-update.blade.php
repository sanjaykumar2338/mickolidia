<x-emails.layout
    status="Account Review"
    title="Update Regarding Your Wolforix Account Review"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $customerName }}</strong>, thank you for your patience while our team reviewed your Wolforix account.
    </x-slot:intro>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">
        Account Update
    </p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Technical synchronization review completed
    </p>
    <p style="margin:14px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        We completed an internal technical review for account <strong style="color:#ffffff;">{{ $accountReference }}</strong>. During that review, an MT5 synchronization and mapping issue was identified and corrected on the Wolforix side.
    </p>
    <p style="margin:14px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        This correction did not affect broker-side trading data. Your broker-side trading records remain separate from the Wolforix synchronization issue that was repaired.
    </p>
    <p style="margin:14px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Your case remains under internal review for trade-duration rule validation, including the trade duration and scalping-rule checks. This message is an update on the technical repair and is not a final pass or fail decision.
    </p>

    <p style="margin:22px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        We appreciate the opportunity to review this carefully and will keep the remaining account review focused on the relevant trade-duration validation.
    </p>

    <x-slot:footer>
        This update does not change account status, challenge outcome, or broker-side trading data.
    </x-slot:footer>
</x-emails.layout>
