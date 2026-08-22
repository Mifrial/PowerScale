<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import { passwordValidatorService } from '@/modules/Core/Auth/init';
import type { VForm } from 'vuetify/components';
import PasswordField from '@/modules/Core/UI/Component/Input/PasswordField.vue';

const router = useRouter();
const auth = useAuthStore();

const formRef = ref<VForm | null>(null);
const login = ref('');
const email = ref('');
const password = ref('');
const confirm = ref('');

onMounted(() => {
  auth.fetchPasswordPolicy();
});

const passwordRules = computed(() => [
  (v: string) => {
    const errors = passwordValidatorService.validate(v, auth.passwordPolicy);

    return errors.length === 0 ? true : errors[0];
  },
]);

async function handleRegister() {
  const { valid } = (await formRef.value?.validate()) ?? { valid: false };
  if (!valid) return;
  const ok = await auth.register(login.value, email.value, password.value);
  if (ok) {
    router.push('/');
  }
}
</script>

<template>
  <div class="auth-page bg-background">
    <v-container class="fill-height" fluid>
      <v-row align="center" justify="center">
        <v-col cols="12" sm="8" md="4" lg="3">
          <v-card class="pa-6">
            <v-card-title class="text-center text-h4 font-weight-bold pa-0 mb-1 card-header">
              Регистрация
            </v-card-title>
            <v-card-subtitle class="text-center text-body-2 mb-8 card-header"> Создайте новый аккаунт </v-card-subtitle>

            <v-card-text class="pa-0">
              <v-form ref="formRef" @submit.prevent="handleRegister">
                <v-alert
                  v-if="auth.error"
                  type="error"
                  variant="tonal"
                  density="compact"
                  class="mb-3 mt-2"
                  closable
                  @click:close="auth.error = null"
                >
                  {{ auth.error }}
                </v-alert>

                <v-text-field
                  v-model="login"
                  label="Логин *"
                  prepend-inner-icon="mdi-account-outline"
                  :rules="[(v) => !!v || 'Введите логин', (v) => v.length >= 3 || 'Минимум 3 символа']"
                  autocomplete="username"
                  class="mb-3"
                />

                <v-text-field
                  v-model="email"
                  label="Email"
                  prepend-inner-icon="mdi-email-outline"
                  type="email"
                  :rules="[(v) => !v || /.+@.+/.test(v) || 'Некорректный email']"
                  autocomplete="email"
                  hint="Рекомендуется для сброса пароля"
                  class="mb-3"
                />

                <PasswordField
                  v-model="password"
                  label="Пароль *"
                  :rules="passwordRules"
                  autocomplete="new-password"
                  class="mb-3"
                />

                <PasswordField
                  v-model="confirm"
                  label="Подтверждение пароля *"
                  :rules="[...passwordRules, (v) => v === password || 'Пароли не совпадают']"
                  autocomplete="new-password"
                  class="mb-3"
                />

                <v-btn type="submit" color="primary" size="large" block :loading="auth.loading" class="mb-3">
                  Зарегистрироваться
                </v-btn>

                <div class="text-center text-body-2">
                  Уже есть аккаунт?
                  <router-link to="/login" class="text-primary text-decoration-none"> Войти </router-link>
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
