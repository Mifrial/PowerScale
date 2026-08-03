import { onBeforeUnmount, ref } from 'vue'
import type { Ref } from 'vue'

export interface ColumnResizeApi {
  resizingKey: Ref<string | null>
  resizePerformed: Ref<boolean>
  start: (key: string, e: MouseEvent) => void
}

export function useColumnResize(options: {
  columnWidths: Ref<Record<string, number>>
  saveWidths: () => void
}): ColumnResizeApi {
  const resizingKey = ref<string | null>(null)
  const resizePerformed = ref(false)
  const startX = ref(0)
  const startWidth = ref(0)

  function onMove(e: MouseEvent) {
    if (!resizingKey.value) return
    const dx = e.clientX - startX.value
    if (Math.abs(dx) > 2) resizePerformed.value = true
    const w = Math.max(60, startWidth.value + dx)
    options.columnWidths.value = { ...options.columnWidths.value, [resizingKey.value]: w }
  }

  function onEnd() {
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onEnd)
    if (resizingKey.value) {
      options.saveWidths()
    }
    resizingKey.value = null
    setTimeout(() => {
      resizePerformed.value = false
    }, 0)
  }

  function start(key: string, e: MouseEvent) {
    e.preventDefault()
    resizingKey.value = key
    const th = (e.currentTarget as HTMLElement).parentElement as HTMLElement
    startX.value = e.clientX
    startWidth.value = th.offsetWidth
    document.addEventListener('mousemove', onMove)
    document.addEventListener('mouseup', onEnd)
  }

  onBeforeUnmount(() => {
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onEnd)
  })

  return { resizingKey, resizePerformed, start }
}
