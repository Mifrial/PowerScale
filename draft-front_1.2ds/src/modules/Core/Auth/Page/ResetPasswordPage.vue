<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import { passwordValidatorService } from '@/modules/Core/Auth/init';
import type { VForm } from 'vuetify/components';
import PasswordField from '@/modules/Core/UI/Component/Input/PasswordField.vue';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

const formRef = ref<VForm | null>(null);
const login = ref('');
const resetToken = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const loading = ref(false);
const message = ref('');
const messageType = ref<'success' | 'error'>('success');

const routeLogin = computed(() => (route.query.login as string) || '');
const routeToken = computed(() => (route.query.token as string) || '');

onMounted(() => {
  auth.fetchPasswordPolicy();
});

const passwordRules = computed(() => [
  (v: string) => {
    const errors = passwordValidatorService.validate(v, auth.passwordPolicy);

    return errors.length === 0 ? true : errors[0];
  },
]);

async function handleReset() {
  const { valid } = (await formRef.value?.validate()) ?? { valid: false };
  if (!valid) return;
  loading.value = true;
  message.value = '';
  try {
    const loginVal = routeLogin.value || login.value;
    const tokenVal = routeToken.value || resetToken.value;
    const ok = await auth.resetPassword(loginVal, tokenVal, newPassword.value);
    if (ok) {
      messageType.value = 'success';
      message.value = 'Пароль успешно изменён';
      setTimeout(() => router.push('/login'), 1500);
    }
  } catch (e: unknown) {
    messageType.value = 'error';
    message.value = e instanceof Error ? e.message : 'Ошибка при сбросе пароля';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="auth-page bg-background">
    <v-container class="fill-height" fluid>
      <v-row align="center" justify="center">
        <v-col cols="12" sm="8" md="6" lg="4">
          <v-card class="pa-6">
            <v-card-title class="text-center text-h4 font-weight-bold pa-0 mb-1 card-header">
              Сброс пароля
            </v-card-title>
            <v-card-subtitle class="text-center text-body-2 mb-8 card-header">
              Придумайте новый пароль
            </v-card-subtitle>

            <v-card-text class="pa-0">
              <v-form ref="formRef" @submit.prevent="handleReset">
                <v-alert v-if="message" :type="messageType" variant="tonal" density="compact" class="mb-3 mt-2">
                  {{ message }}
                </v-alert>

                <v-text-field
                  v-if="!routeLogin"
                  v-model="login"
                  label="Логин"
                  prepend-inner-icon="mdi-account-outline"
                  :rules="[(v) => !!v || 'Введите логин']"
                  class="mb-3"
                />

                <v-text-field
                  v-if="!routeToken"
                  v-model="resetToken"
                  label="Код сброса"
                  prepend-inner-icon="mdi-key-variant"
                  :rules="[(v) => !!v || 'Введите код сброса']"
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
                  :rules="[...passwordRules, (v) => v === newPassword || 'Пароли не совпадают']"
                  autocomplete="new-password"
                  class="mb-3"
                />

                <v-btn type="submit" color="primary" size="large" block :loading="loading" class="mb-3">
                  Сохранить новый пароль
                </v-btn>

                <div class="text-center text-body-2">
                  <router-link to="/login" class="text-primary text-decoration-none"> Вернуться ко входу </router-link>
                </div>
              </v-form>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<style scoped>
.auth-page {
  min-height: 100vh;
}

.card-header {
  overflow-wrap: break-word;
  white-space: normal;
}
</style>
