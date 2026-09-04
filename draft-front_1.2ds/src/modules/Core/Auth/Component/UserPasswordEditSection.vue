<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import { getAuthApi, passwordValidatorService } from '@/modules/Core/Auth/init';
import { accessService, useCurrentUser } from '@/modules/Core/User/init';
import PasswordField from '@/modules/Core/UI/Component/Input/PasswordField.vue';
import type { VForm } from 'vuetify/components';

const route = useRoute();
const auth = useAuthStore();
const { currentUser } = useCurrentUser();

const formRef = ref<VForm | null>(null);
const currentPassword = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const saving = ref(false);
const snackbar = ref({ show: false, text: '', color: '' });

const targetUserId = computed(() => Number(route.params.id));
const isSelf = computed(() => currentUser.value?.id === targetUserId.value);
const canEditPassword = computed(
  () => isSelf.value || accessService.hasAnyPermission(currentUser.value, ['auth.user.edit']),
);

onMounted(() => {
  auth.fetchPasswordPolicy(targetUserId.value);
});

const passwordRules = computed(() => [
  (v: string) => {
    const errors = passwordValidatorService.validate(v, auth.passwordPolicy);

    return errors.length === 0 ? true : errors[0];
  },
]);

async function handleSubmit() {
  const { valid } = (await formRef.value?.validate()) ?? { valid: false };
  if (!valid) return;
  saving.value = true;
  try {
    await getAuthApi().setPassword(
      targetUserId.value,
      newPassword.value,
      isSelf.value ? currentPassword.value : undefined,
    );
    currentPassword.value = '';
    newPassword.value = '';
    confirmPassword.value = '';
    snackbar.value = { show: true, text: 'Пароль изменён', color: 'success' };
  } catch (e: unknown) {
    snackbar.value = {
      show: true,
      text: e instanceof Error ? e.message : 'Не удалось сменить пароль',
      color: 'error',
    };
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <v-card v-if="canEditPassword" class="mt-4" style="border: thin solid rgba(var(--v-border-color), var(--v-border-opacity))">
    <v-card-item>
      <v-card-title class="text-body-1 font-weight-bold">
        <v-icon start size="small" class="mb-1">mdi-lock-outline</v-icon>
        Пароль
      </v-card-title>
    </v-card-item>
    <v-divider />
    <v-card-text>
      <v-form ref="formRef" @submit.prevent="handleSubmit">
        <PasswordField
          v-if="isSelf"
          v-model="currentPassword"
          label="Текущий пароль *"
          :rules="[(v: string) => !!v || 'Введите текущий пароль']"
          autocomplete="current-password"
          class="mb-3"
        />
        <PasswordField
          v-model="newPassword"
          label="Новый пароль *"
          :rules="passwordRules"
          autocomplete="new-password"
          class="mb-3"
        />
        <PasswordField
          v-model="confirmPassword"
          label="Подтверждение пароля *"
          :rules="[...passwordRules, (v: string) => v === newPassword || 'Пароли не совпадают']"
          autocomplete="new-password"
          class="mb-3"
        />
        <v-btn type="submit" color="primary" :loading="saving">Сохранить пароль</v-btn>
      </v-form>
    </v-card-text>
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
      {{ snackbar.text }}
    </v-snackbar>
  </v-card>
</template>
