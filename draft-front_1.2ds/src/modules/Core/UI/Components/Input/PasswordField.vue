<template>
  <v-text-field
    prepend-inner-icon="mdi-lock-outline"
    v-model="internalValue"
    :type="showPassword ? 'text' : 'password'"
    :label="label"
    :rules="rules"
    :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
    @click:append-inner="showPassword = !showPassword"
    :error-messages="errorMessages"
    v-bind="$attrs"
  />
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: string
  label?: string
  rules?: ((v: string) => string | true)[]
  errorMessages?: string[]
}>(), {
  label: 'Пароль',
  rules: () => [],
  errorMessages: () => [],
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const showPassword = ref(false)

const internalValue = computed({
  get: () => props.modelValue,
  set: (val: string) => emit('update:modelValue', val),
})
</script>
