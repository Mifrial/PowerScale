<script setup lang="ts">
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    modelValue: number;
    min?: number;
    max?: number;
    minWidth?: string | number;
    /** Пустое поле → null наверх (лимит не задан), а не min/0. */
    nullable?: boolean;
  }>(),
  {
    minWidth: '100px',
    nullable: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: number];
}>();

const attrs = useAttrs();
const outerClass = computed(() => (typeof attrs.class === 'string' ? attrs.class : ''));
const outerStyle = computed(() => attrs.style);
const inputAttrs = computed(() => {
  const { style: _style, class: _class, ...rest } = attrs as Record<string, unknown>;

  return rest;
});

const rootStyle = computed(() => [{ minWidth: props.minWidth }, outerStyle.value]);

function clamp(v: unknown): number {
  if (v === null || v === undefined || v === '') {
    return (props.nullable ? null : (props.min ?? 0)) as number;
  }
  const n = Number(v);
  if (!Number.isFinite(n)) return (props.nullable ? null : (props.min ?? 0)) as number;
  if (props.min !== undefined && n < props.min) return props.min;
  if (props.max !== undefined && n > props.max) return props.max;

  return n;
}

function onInput(v: unknown) {
  emit('update:modelValue', clamp(v));
}

function onKeydown(e: KeyboardEvent) {
  const current = props.modelValue ?? 0;
  if (e.key === 'ArrowUp') {
    emit('update:modelValue', clamp(current + 1));
    e.preventDefault();

    return;
  }
  if (e.key === 'ArrowDown') {
    emit('update:modelValue', clamp(current - 1));
    e.preventDefault();

    return;
  }
  if (e.key.length === 1 && e.key >= '0' && e.key <= '9') {
    e.preventDefault();
    const prefix = props.modelValue == null ? '' : String(props.modelValue);
    const next = parseInt(prefix + e.key, 10);
    const clamped = props.max !== undefined && next > props.max ? props.max : next;
    emit('update:modelValue', clamped);
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
