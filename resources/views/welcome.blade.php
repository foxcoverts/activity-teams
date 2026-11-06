<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" prefix="og: https://ogp.me/ns#">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ __('Fox Coverts Climbing') }}</title>

    <!-- Social Metadata -->
    <meta property="twitter:title" content="{{ __('Fox Coverts Climbing') }}" />
    <meta property="og:title" content="{{ __('Fox Coverts Climbing') }}" />
    <meta property="og:url" content="{{ route('home') }}" />
    <meta name="description"
        content="{{ __('Climbing and Abseiling sessions at Fox Coverts Campsite are run by our team of qualified climbing instructors.') }}" />
    <meta property="og:description"
        content="{{ __('Climbing and Abseiling sessions at Fox Coverts Campsite are run by our team of qualified climbing instructors.') }}" />
    <meta name="twitter:description"
        content="{{ __('Climbing and Abseiling sessions at Fox Coverts Campsite are run by our team of qualified climbing instructors.') }}" />
    <meta property="og:image" content="{{ asset('images/about/climber@2x.jpg') }}" />
    <meta name="twitter:image" content="{{ asset('images/about/climber@2x.jpg') }}" />
    <meta property="og:image:width" content="576" />
    <meta property="og:image:height" content="296" />
    <meta property="og:updated_time"
        content="{{ \Illuminate\Support\Carbon::parse('2024-04-01T00:00:00Z')->toIso8601String() }}" />
    <meta property="og:type" content="website" />
    <meta property="og:locale" content="{{ config('app.locale', 'en') }}" />
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:site" content="@FoxCoverts" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body
    class="font-sans antialiased leading-normal tracking-normal bg-gray-100 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
    <div class="flex flex-col sm:justify-center items-center sm:mx-6">
        <h1 class="text-center font-semibold text-2xl text-sky-500 uppercase py-6">
            {{ __('Fox Coverts Climbing') }}
        </h1>

        <div class="w-full sm:max-w-xl bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
            <img src="{{ asset('images/about/climber.jpg') }}"
                srcset="{{ asset('images/about/climber.jpg') }}, {{ asset('images/about/climber@2x.jpg') }} 2x"
                alt="{{ __('Child climbing') }}" width="576" height="296" class="w-full max-w-full" />

            <div class="px-6 py-4 space-y-4">
                <p>{{ __('Climbing and Abseiling sessions at Fox Coverts Campsite are run by our team of qualified climbing instructors. Groups with their own instructors and equipment wishing to hire the climbing tower should contact the Climbing Manager for advice.') }}
                </p>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <dfn class="not-italic font-medium block">{{ __('Climbing suitable for') }}</dfn>
                        {{ __('All age groups') }}
                    </div>
                    <div>
                        <dfn class="not-italic font-medium block">{{ __('Abseiling suitable for') }}</dfn>
                        {{ __('Ages 8 and above') }}
                    </div>
                    <div>
                        <dfn class="not-italic font-medium block">{{ __('Minimum booking') }}</dfn>
                        {{ __('2 hours') }}
                    </div>
                    <div>
                        <dfn class="not-italic font-medium block">{{ __('Availability') }}</dfn>
                        {{ __('April to October') }}
                    </div>
                    <div class="col-span-2">
                        <dfn class="not-italic font-medium block">{{ __('Number of participants per hour') }}</dfn>
                        {{ __('12 aged 8 or older.') }}
                        <em
                            class="not-italic text-gray-500">{{ __('Please ask for advice about younger groups.') }}</em>
                    </div>
                </div>

                <div class="flex justify-center">
                    <a href="https://foxcoverts.org.uk/activities/climbing/"
                        class="inline-flex items-center gap-1 rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                        <span>{{ __('Prices and Booking') }}</span>
                        <span class="sr-only">({{ __('external link') }})</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"
                            aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z"
                                clip-rule="evenodd" />
                            <path fill-rule="evenodd"
                                d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z"
                                clip-rule="evenodd" />
                        </svg>
                    </a>
                </div>

                <p class="text-center group">
                    <a href="https://foxcoverts.org.uk/activities/climbing/">
                        {{ __('Please visit') }}
                        <em
                            class="not-italic text-sky-500 dark:text-sky-400 underline group-hover:text-sky-400 dark:group-hover:text-sky-300">foxcoverts.org.uk</em>
                        {{ __('for prices and to book.') }}
                    </a>
                </p>
            </div>
        </div>

        <div class="w-full max-w-xl">
            <a class="block w-full text-center py-6 text-gray-500 hover:text-zinc-800 dark:hover:text-zinc-200 hover:underline"
                href="{{ \App\Filament\Member\Pages\Dashboard::getUrl() }}"
                rel="nofollow">{{ __('Go to Team Dashboard') }}</a>
        </div>
    </div>
</body>

</html>
