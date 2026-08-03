<script setup lang="ts">
import { computed, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  modelValue: number
  min?: number
  max?: number
  minWidth?: string | number
}>(), {
  minWidth: '100px',
})

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

const attrs = useAttrs()
const outerClass = computed(() => (typeof attrs.class === 'string' ? attrs.class : ''))
const outerStyle = computed(() => attrs.style)
const inputAttrs = computed(() => {
  const { style: _style, class: _class, ...rest } = attrs as Record<string, unknown>
  return rest
})

const rootStyle = computed(() => [
  { minWidth: props.minWidth },
  outerStyle.value,
])

function clamp(v: unknown): number {
  const n = Number(v)
  if (!Number.isFinite(n)) return props.min ?? n
  if (props.min !== undefined && n < props.min) return props.min
  if (props.max !== undefined && n > props.max) return props.max
  return n
}

function onInput(v: unknown) {
  emit('update:modelValue', clamp(v))
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'ArrowUp') {
    emit('update:modelValue', clamp(props.modelValue + 1))
    e.preventDefault()
    return
  }
  if (e.key === 'ArrowDown') {
    emit('update:modelValue', clamp(props.modelValue - 1))
    e.preventDefault()
    return
  }
  if (e.key.length === 1 && e.key >= '0' && e.key <= '9') {
    e.preventDefault()
    const next = parseInt(String(props.modelValue) + e.key)
    const clamped = props.max !== undefined && next > props.max ? props.max : next
    emit('update:modelValue', clamped)
  }
}
</script>

<template>
  <v-number-input
    :model-value="modelValue"
    :min="min"
    :max="max"
    v-bind="inputAttrs"
    :class="outerClass"
    :style="rootStyle"
    @update:model-value="onInput"
    @keydown="onKeydown"
  />
</template>
