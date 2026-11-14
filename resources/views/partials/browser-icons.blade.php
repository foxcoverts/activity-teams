<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
<link rel="icon" href="{{ asset('icon.svg') }}" type="image/svg+xml" class="js-site-favicon" data-icon-light="{{ asset('icon.svg') }}" data-icon-dark="{{ asset('icon-dark.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<link rel="mask-icon" href="{{ asset('safari-pinned-tab.svg') }}" color="#7815d2">
<meta name="theme-color" content="#ffffff">
<script>
    (() => {
        const link = document.querySelector('link.js-site-favicon');

        if (! (link instanceof HTMLLinkElement)) {
            return;
        }

        const light = link.dataset.iconLight;
        const dark = link.dataset.iconDark;

        if (! light || ! dark || ! window.matchMedia) {
            return;
        }

        const media = window.matchMedia('(prefers-color-scheme: dark)');

        const apply = () => {
            const href = media.matches ? dark : light;

            document.querySelectorAll('link[rel="icon"]').forEach((el) => {
                if (! (el instanceof HTMLLinkElement)) {
                    return;
                }

                const current = el.getAttribute('href') ?? '';
                const type = el.getAttribute('type') ?? '';

                if (type !== 'image/svg+xml' && ! current.includes('icon.svg') && ! current.includes('icon-dark.svg')) {
                    return;
                }

                el.type = 'image/svg+xml';
                el.href = href;
            });
        };

        apply();

        if (typeof media.addEventListener === 'function') {
            media.addEventListener('change', apply);
        } else if (typeof media.addListener === 'function') {
            media.addListener(apply);
        }
    })();
</script>
