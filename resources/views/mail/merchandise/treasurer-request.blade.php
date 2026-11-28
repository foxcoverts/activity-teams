<x-mail::message>
{{ __('Dear Treasurer,') }}

{{ __('Please can you raise an invoice for the below items.') }}

**{{ __('Invoice') }}:** {{ $member?->name ?? __('Unknown') }}@if (filled($member?->email)) &lt;{{ $member->email }}&gt;@endif

<x-mail::table>
| {{ __('Description') }} | {{ __('Amount') }} |
| --- | ---: |
@foreach ($billableLines as $line)
| {{ $line['description'] }} | {{ $line['total'] }} |
@endforeach
@if ($hasOrderDiscount)
| {{ __('Sub-total') }} | {{ $itemsSubtotalLabel }} |
| {{ __('Discount') }} | −{{ $orderDiscountLabel }} |
@endif
| **{{ __('Total') }}** | **{{ $amountDueLabel }}** |
</x-mail::table>

{{ __('Thanks,') }}  
{{ $actor->name }}
</x-mail::message>
