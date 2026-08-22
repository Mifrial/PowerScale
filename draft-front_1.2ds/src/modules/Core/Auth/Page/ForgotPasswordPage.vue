<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import type { VForm } from 'vuetify/components';

const router = useRouter();
const auth = useAuthStore();

const formRef = ref<VForm | null>(null);
const loginOrEmail = ref('');
const loading = ref(false);
const message = ref('');
const messageType = ref<'success' | 'error'>('success');

async function handleReset() {
  const { valid } = (await formRef.value?.validate()) ?? { valid: false };
  if (!valid) return;
  loading.value = true;
  message.value = '';
  try {
    const user = await auth.findUser(loginOrEmail.value);
    if (!user) {
      messageType.value = 'error';
      message.value = 'Аккаунт с таким логином или email не найден';
    } else if (!user.email) {
      messageType.value = 'error';
      message.value = 'Для этого аккаунта не указан email, обратитесь к администратору';
    } else {
      messageType.value = 'success';
      message.value = `Инструкция по сбросу пароля отправлена на ${user.email}`;
      setTimeout(() => {
        router.push({ name: 'ResetPassword', query: { login: user.login } });
      }, 1500);
    }
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
              Восстановление пароля
            </v-card-title>
            <v-card-subtitle class="text-center text-body-2 mb-8 card-header">
              Введите логин или email, привязанный к аккаунту
            </v-card-subtitle>

            <v-card-text class="pa-0">
              <v-form ref="formRef" @submit.prevent="handleReset">
                <v-alert v-if="message" :type="messageType" variant="tonal" density="compact" class="mb-3 mt-2">
                  {{ message }}
                </v-alert>

                <v-text-field
                  v-model="loginOrEmail"
                  label="Логин или email"
                  prepend-inner-icon="mdi-account-outline"
                  :rules="[(v) => !!v || 'Введите логин или email']"
                  class="mb-3"
                />

                <v-btn type="submit" color="primary" size="large" block :loading="loading" class="mb-3">
                  Сбросить пароль
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
