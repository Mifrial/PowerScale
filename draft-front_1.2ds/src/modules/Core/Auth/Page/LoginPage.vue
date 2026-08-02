<template>
  <div class="auth-page bg-background" :style="{ backgroundImage: `url(${bgUrl})`, backgroundSize: 'cover', backgroundPosition: 'center', backgroundRepeat: 'no-repeat' }">
    <v-container class="fill-height" fluid>
      <v-row align="center" justify="center">
        <v-col cols="12" sm="8" md="4" lg="3">
          <v-card class="pa-6 card-translucent">
            <v-card-title class="text-center text-h4 font-weight-bold pa-0 mb-1 card-header">
              Вход
            </v-card-title>
            <v-card-subtitle class="text-center text-body-2 mb-8 card-header">
              Войдите в аккаунт
            </v-card-subtitle>

            <v-card-text class="pa-0">
              <v-form ref="formRef" @submit.prevent="handleLogin">
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
                  v-model="email"
                  label="Логин или email"
                  prepend-inner-icon="mdi-account-outline"
                  :rules="[v => !!v || 'Введите логин или email', v => v.length >= 3 || 'Минимум 3 символа']"
                  autocomplete="username"
                  class="mb-3"
                />

                <PasswordField
                  v-model="password"
                  label="Пароль"
                  autocomplete="current-password"
                />

                <v-switch
                  v-model="remember"
                  label="Запомнить меня"
                  color="primary"
                  hide-details
                  class="mb-3"
                />

                <Transition name="btn-swap" mode="out-in">
                  <div v-if="canLogin" key="login-layout" class="d-flex align-center ga-3 mb-3">
                    <v-btn
                      type="submit"
                      color="primary"
                      size="large"
                      class="flex-grow-1"
                      :loading="auth.loading"
                    >
                      Войти
                    </v-btn>

                    <v-btn
                      variant="outlined"
                      color="secondary"
                      size="large"
                      @click="handleGuestLogin"
                      title="Войти как гость"
                    >
                      <v-icon>mdi-account-question-outline</v-icon>
                    </v-btn>
                  </div>

                  <div v-else key="guest-layout" class="mb-3">
                    <v-btn
                      color="secondary"
                      variant="outlined"
                      size="large"
                      block
                      @click="handleGuestLogin"
                      prepend-icon="mdi-account-question-outline"
                    >
                      Войти как гость
                    </v-btn>
                  </div>
                </Transition>

                <div class="text-center text-body-2">
                  <router-link to="/forgot-password" class="text-primary text-decoration-none">
                    Забыли пароль?
                  </router-link>
                  <span class="mx-2 text-medium-emphasis">·</span>
                  <router-link to="/register" class="text-primary text-decoration-none">
                    Регистрация
                  </router-link>
                </div>
              </v-form>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/modules/Core/Auth/Store/auth'
import PasswordField from '@/modules/Core/UI/Component/Input/PasswordField.vue'
import bgUrl from '@/assets/img/login_background.jpg'

const router = useRouter()
const auth = useAuthStore()

interface FormRef {
  validate: () => Promise<{ valid: boolean }>
  reset: () => void
  resetValidation: () => void
}

const formRef = ref<FormRef | null>(null)
const email = ref('')
const password = ref('')
const remember = ref(true)

const canLogin = computed(() => email.value.length >= 3 && password.value.length > 0)

async function handleLogin() {
  const { valid } = await formRef.value?.validate() ?? { valid: false }
  if (!valid) return
  const ok = await auth.login(email.value, password.value)
  if (ok) {
    router.push('/')
  }
}

async function handleGuestLogin() {
  const ok = await auth.guestLogin()
  if (ok) {
    router.push('/')
  }
}
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
}

.card-header {
  overflow-wrap: break-word;
  white-space: normal;
}

.card-translucent.v-card {
  background: rgba(var(--v-theme-surface), 0.4);
  backdrop-filter: blur(6px);
}

.btn-swap-enter-active,
.btn-swap-leave-active {
  transition: all 0.1s ease;
}
.btn-swap-enter-from {
  opacity: 0;
  transform: translateY(-6px);
}
.btn-swap-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
</style>
