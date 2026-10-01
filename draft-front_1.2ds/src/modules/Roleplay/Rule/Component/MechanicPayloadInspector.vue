<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { mechanicPayloadInspectorService } from '@/modules/Roleplay/Rule/Service/Instance/mechanicPayloadInspectorService';

const props = defineProps<{
  payload: unknown;
  readonly?: boolean;
  isNew?: boolean;
  mechanicChanged?: boolean;
  error?: string | null;
}>();

const emit = defineEmits<{
  commit: [];
}>();

const text = ref('');

const mode = computed(() => {
  if (props.readonly) {
    return mechanicPayloadInspectorService.isAbsent(props.payload) ? 'hidden' : 'view';
  }
  if (props.isNew) return 'hidden';
  if (props.mechanicChanged) {
    return mechanicPayloadInspectorService.isAbsent(props.payload) ? 'hidden' : 'reset';
  }
  if (isEditablePayload(props.payload)) return 'edit';

  return 'hidden';
});

const known = computed(() => mechanicPayloadInspectorService.isKnownType(props.payload));
const pretty = computed(() => mechanicPayloadInspectorService.format(props.payload));

watch(
  () => props.payload,
  () => {
    text.value = mechanicPayloadInspectorService.isAbsent(props.payload)
      ? ''
      : mechanicPayloadInspectorService.format(props.payload);
  },
  { immediate: true },
);

function isEditablePayload(payload: unknown): boolean {
  if (payload === null || typeof payload !== 'object') return false;
  if (Array.isArray(payload)) return payload.length > 0;

  return true;
}

function readText(): string {
  return text.value;
}

function isEditing(): boolean {
  return mode.value === 'edit';
}

defineExpose({ readText, isEditing });
</script>

<template>
  <v-card v-if="mode !== 'hidden'" class="mb-4">
    <v-card-title class="d-flex align-center">
      Payload механики
      <v-chip v-if="mode !== 'reset'" class="ml-2" size="small" variant="tonal">
        {{ known ? 'тип известен фронту' : 'поддержка не заявлена' }}
      </v-chip>
    </v-card-title>
    <v-card-text>
      <p v-if="mode === 'reset'" class="text-body-2 mb-0">
        При смене механики payload будет сброшен и не попадёт в черновик.
      </p>
      <pre v-else-if="mode === 'view'" class="text-body-2">{{ pretty }}</pre>
      <v-textarea
        v-else
        v-model="text"
        label="JSON payload"
        auto-grow
        rows="6"
        :error-messages="error ? [error] : []"
        @blur="emit('commit')"
      />
    </v-card-text>
  </v-card>
</template>
