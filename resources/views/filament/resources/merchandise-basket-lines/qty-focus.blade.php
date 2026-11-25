{{-- Filament TextInputColumn disables the field while saving; browsers blur disabled
     inputs, which breaks arrow-key quantity stepping. Restore focus after save.

     Keep the flag on the wire:ignore.self wrapper — Livewire morphs the <input>
     child and would strip a data-* flag from the input itself. --}}
<script>
    (() => {
        const version = 3

        if (window.__basketQtyFocusBound === version) {
            return
        }

        window.__basketQtyFocusBound = version

        const qtyWrap = (target) => {
            if (! (target instanceof HTMLInputElement) || ! target.classList.contains('fi-input')) {
                return null
            }

            return target.closest('.basket-qty-input')
        }

        document.addEventListener('focusin', (event) => {
            const wrap = qtyWrap(event.target)

            if (wrap) {
                wrap.dataset.basketQtyKeepFocus = '1'
            }
        }, true)

        document.addEventListener('focusout', (event) => {
            const wrap = qtyWrap(event.target)

            if (! wrap) {
                return
            }

            const alpine = window.Alpine ? Alpine.$data(wrap) : null

            if (alpine?.isLoading) {
                return
            }

            delete wrap.dataset.basketQtyKeepFocus
        }, true)

        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.type !== 'attributes' || mutation.attributeName !== 'disabled') {
                    continue
                }

                const input = mutation.target
                const wrap = qtyWrap(input)

                if (! wrap || input.disabled || wrap.dataset.basketQtyKeepFocus !== '1') {
                    continue
                }

                queueMicrotask(() => {
                    if (wrap.dataset.basketQtyKeepFocus === '1' && ! input.disabled) {
                        input.focus()
                    }
                })
            }
        })

        observer.observe(document.body, {
            subtree: true,
            attributes: true,
            attributeFilter: ['disabled'],
        })
    })()
</script>
