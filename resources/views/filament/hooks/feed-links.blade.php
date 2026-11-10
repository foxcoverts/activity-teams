<link rel="alternate" type="application/atom+xml" title="{{ __(':Name News (Atom)', ['name' => config('app.name')]) }}"
    href="{{ $atomUrl }}">
<link rel="alternate" type="application/rss+xml" title="{{ __(':Name News (RSS)', ['name' => config('app.name')]) }}"
    href="{{ $rssUrl }}">
<link rel="alternate" type="text/calendar" title="{{ __(':Name Bookings', ['name' => config('app.name')]) }}"
    href="{{ $bookingsUrl }}">
<link rel="alternate" type="text/calendar" title="{{ __(':Name Rota', ['name' => config('app.name')]) }}"
    href="{{ $rotaUrl }}">
<link rel="alternate" type="text/calendar" title="{{ __(':Name Tasks', ['name' => config('app.name')]) }}"
    href="{{ $tasksUrl }}">
<link rel="alternate" type="text/calendar" title="{{ __(':Name Calendar', ['name' => config('app.name')]) }}"
    href="{{ $everythingUrl }}">
