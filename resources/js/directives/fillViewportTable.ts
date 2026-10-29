import type { Directive, DirectiveBinding } from 'vue'

type FillViewportOptions = {
    bottomGap?: number
    minHeight?: number
    mode?: 'fill' | 'max'
    enabled?: boolean
}

type FillViewportElement = HTMLElement & {
    __ghadamsFillViewportCleanup?: () => void
    __ghadamsFillViewportRaf?: number
}

function readOptions(
    binding?: DirectiveBinding<FillViewportOptions>,
) {
    return {
        bottomGap: Number(
            binding?.value?.bottomGap ?? 16,
        ),
        minHeight: Number(
            binding?.value?.minHeight ?? 320,
        ),
        mode: binding?.value?.mode ?? 'max',
        enabled: binding?.value?.enabled !== false,
    }
}

function clearManagedStyles(
    el: FillViewportElement,
) {
    el.style.height = ''
    el.style.maxHeight = ''
    el.style.minHeight = ''
    el.style.overflowY = ''
    el.style.overscrollBehavior = ''
}

function scheduleUpdate(
    el: FillViewportElement,
    binding?: DirectiveBinding<FillViewportOptions>,
) {
    if (el.__ghadamsFillViewportRaf) {
        cancelAnimationFrame(
            el.__ghadamsFillViewportRaf,
        )
    }

    el.__ghadamsFillViewportRaf =
        requestAnimationFrame(() => {
            const {
                bottomGap,
                minHeight,
                mode,
                enabled,
            } = readOptions(binding)

            if (!enabled) {
                clearManagedStyles(el)
                return
            }

            const top =
                el.getBoundingClientRect().top

            const viewportHeight =
                window.visualViewport?.height
                ?? window.innerHeight

            const available = Math.max(
                minHeight,
                Math.floor(
                    viewportHeight
                    - Math.max(top, 0)
                    - bottomGap,
                ),
            )

            /*
             * FILL:
             * Shared DataTable pages intentionally occupy
             * the remaining viewport.
             *
             * MAX:
             * Native/custom tables may grow up to the
             * remaining viewport but are NOT stretched when
             * they contain only a few rows. This prevents
             * large empty table panels.
             */
            if (mode === 'fill') {
                el.style.height =
                    `${available}px`
                el.style.maxHeight = 'none'
            } else {
                el.style.height = 'auto'
                el.style.maxHeight =
                    `${available}px`
            }

            el.style.minHeight =
                mode === 'fill'
                    ? `${minHeight}px`
                    : ''

            el.style.overflowY = 'auto'
            el.style.overscrollBehavior =
                'contain'

            const thead =
                el.querySelector(
                    'thead',
                ) as HTMLElement | null

            if (thead) {
                thead.style.position = 'sticky'
                thead.style.top = '0'
                thead.style.zIndex = '20'
            }
        })
}

export const fillViewportTable: Directive<
    FillViewportElement,
    FillViewportOptions
> = {
    mounted(el, binding) {
        const update = () =>
            scheduleUpdate(el, binding)

        update()

        window.addEventListener(
            'resize',
            update,
        )

        window.addEventListener(
            'orientationchange',
            update,
        )

        window.visualViewport
            ?.addEventListener(
                'resize',
                update,
            )

        const observer =
            typeof ResizeObserver
                !== 'undefined'
                ? new ResizeObserver(
                      update,
                  )
                : null

        if (el.parentElement) {
            observer?.observe(
                el.parentElement,
            )
        }

        el.__ghadamsFillViewportCleanup =
            () => {
                window.removeEventListener(
                    'resize',
                    update,
                )

                window.removeEventListener(
                    'orientationchange',
                    update,
                )

                window.visualViewport
                    ?.removeEventListener(
                        'resize',
                        update,
                    )

                observer?.disconnect()

                if (
                    el.__ghadamsFillViewportRaf
                ) {
                    cancelAnimationFrame(
                        el.__ghadamsFillViewportRaf,
                    )
                }
            }
    },

    updated(el, binding) {
        scheduleUpdate(el, binding)
    },

    beforeUnmount(el) {
        el.__ghadamsFillViewportCleanup?.()
        clearManagedStyles(el)
    },
}
