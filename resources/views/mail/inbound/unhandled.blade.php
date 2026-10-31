<x-mail::message>
# {{ __('Inbound email needs attention') }}

{{ __('We could not process this email automatically.') }}

- {{ __('From') }}: {{ $inboundMail->from_address ?: __('Unknown') }}
- {{ __('Subject') }}: {{ $inboundMail->subject ?: __('(no subject)') }}
@if ($resourceLabel)
- {{ __('Related to') }}: {{ $resourceLabel }}
@endif

@if ($resourceUrl)
<x-mail::button :url="$resourceUrl">
{{ __('Open related record') }}
</x-mail::button>
@endif

<x-mail::button :url="$reviewUrl">
{{ __('Review in admin') }}
</x-mail::button>

</x-mail::message>
