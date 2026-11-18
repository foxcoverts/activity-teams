{{--
    Tippy (alpine-tooltip) can stick over Filament modals on touch: tippies use
    z-index 9999 and are not dismissed when a modal opens. Filament's Escape
    handler in vendor tooltip.js already hides visible [data-tippy-root]
    instances; mirror that when x-modal-opened fires so hover tooltips stay
    available without covering the modal.
--}}
<script>
    document.addEventListener('x-modal-opened', () => {
        document.querySelectorAll('[data-tippy-root]').forEach((popover) => {
            const tooltip = popover._tippy

            if (tooltip?.state.isVisible) {
                tooltip.hide()
            }
        })
    })
</script>
