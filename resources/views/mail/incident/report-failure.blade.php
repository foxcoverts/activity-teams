<x-mail::message>
# {{ __('Incident report delivery failed') }}

{{ __('The queued incident report could not be delivered after all retry attempts.') }}

- **{{ __('Team') }}:** {{ $teamName }}
- **{{ __('Reporter') }}:** {{ $reporterName }} ({{ $reporterEmail }})
- **{{ __('Error') }}:** {{ $exceptionMessage }}

{{ __('Retry the failed job with `php artisan queue:retry` after resolving the delivery issue.') }}

</x-mail::message>
