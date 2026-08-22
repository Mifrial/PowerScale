<script setup lang="ts">
import { computed } from 'vue';
import { formatDatetime } from '@/modules/Core/UI/Utils/formatDatetime';

const props = defineProps<{ value: unknown }>();

const formatted = computed(() => (props.value ? formatDatetime(String(props.value)) : '—'));
</script>

<template>
  <span class="text-caption text-medium-emphasis">{{ formatted }}</span>
</template>
