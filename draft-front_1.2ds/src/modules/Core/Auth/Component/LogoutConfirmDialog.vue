<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';

const open = defineModel<boolean>({ default: false });

const router = useRouter();
const auth = useAuthStore();
const submitting = ref(false);
const submitError = ref<string | null>(null);

watch(open, (value) => {
  if (value) submitError.value = null;
});

async function confirmLogout(): Promise<void> {
  submitting.value = true;
  submitError.value = null;
  try {
    await auth.logout();
    open.value = false;
    await router.replace({ name: 'Login' });
  } catch (e) {
    submitError.value = e instanceof Error ? e.message : 'Не удалось выйти';
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <v-dialog v-model="open" max-width="360" :persistent="submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">Выйти</v-card-title>
      <v-card-text class="text-body-2">
        <v-alert v-if="submitError" type="error" variant="tonal" density="compact" class="mb-3">
          {{ submitError }}
        </v-alert>
        Вы уверены, что хотите выйти?
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="submitting" @click="open = false">Отмена</v-btn>
        <v-btn color="error" variant="tonal" :loading="submitting" @click="confirmLogout">Выйти</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
