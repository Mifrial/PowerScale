import { ref } from 'vue'
import type { Ref } from 'vue'

export interface ScrollEarsApi {
  tableScrollEl: Ref<HTMLElement | null>
  earsLeft: Ref<boolean>
  earsRight: Ref<boolean>
  updateEars: () => void
  scroll: (dir: -1 | 1) => void
  initEars: () => void
  cleanup: () => void
}

export function useScrollEars(scope: {
  scrollContainer: Ref<HTMLElement | null>
}): ScrollEarsApi {
  const tableScrollEl = ref<HTMLElement | null>(null)
  const earsLeft = ref(false)
  const earsRight = ref(false)

  function updateEars() {
    const el = tableScrollEl.value
    if (!el) {
      earsLeft.value = false
      earsRight.value = false
      return
    }
    earsLeft.value = el.scrollLeft > 2
    earsRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 2
  }

  function scroll(dir: -1 | 1) {
    const el = tableScrollEl.value
    if (!el) return
    el.scrollBy({ left: dir * 300, behavior: 'smooth' })
  }

  function initEars() {
    const container = scope.scrollContainer.value
    if (!container) return
    const el = container.querySelector('.v-table__wrapper') as HTMLElement | null
    if (!el || el === tableScrollEl.value) return
    if (tableScrollEl.value) {
      tableScrollEl.value.removeEventListener('scroll', updateEars)
    }
    tableScrollEl.value = el
    el.addEventListener('scroll', updateEars)

    const wrapper = container.closest('.smart-ears-wrapper') as HTMLElement | null
    if (wrapper) {
      const headerEl = container.querySelector('thead') as HTMLElement | null
      const footerEl = container.querySelector('.smart-grid-footer') as HTMLElement | null
      const top = headerEl?.offsetHeight ?? 44
      const bottom = footerEl?.offsetHeight ?? 0
      wrapper.style.setProperty('--ear-top', `${top + 2}px`)
      wrapper.style.setProperty('--ear-bottom', `${bottom + 2}px`)
    }

    updateEars()
  }

  function cleanup() {
    if (tableScrollEl.value) {
      tableScrollEl.value.removeEventListener('scroll', updateEars)
    }
  }

  return { tableScrollEl, earsLeft, earsRight, updateEars, scroll, initEars, cleanup }
}
