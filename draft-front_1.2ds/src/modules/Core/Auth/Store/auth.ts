import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { getAuthApi } from '@/modules/Core/Auth/init';
import { useUserStore } from '@/modules/Core/User/Store/users';
import type { PasswordPolicy } from '@/modules/Core/Auth/Dto/PasswordPolicy';

export const useAuthStore = defineStore('auth', () => {
  const userId = ref<number | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const passwordPolicy = ref<PasswordPolicy>({
    minLength: 4,
    requireMixedCase: false,
    requireDigit: false,
    requireSpecialChar: false,
  });

  const isAuthenticated = computed(() => userId.value !== null);
  const isGuest = computed(() => userId.value === 0);

  async function fetchPasswordPolicy(): Promise<PasswordPolicy> {
    try {
      const policy = await getAuthApi().getPasswordPolicy();
      passwordPolicy.value = policy;

      return policy;
    } catch {
      return passwordPolicy.value;
    }
  }

  async function login(emailOrLogin: string, password: string): Promise<boolean> {
    loading.value = true;
    error.value = null;
    try {
      const user = await getAuthApi().login(emailOrLogin, password);
      userId.value = user.id;
      useUserStore().setCurrent(user);

      return true;
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : String(e);

      return false;
    } finally {
      loading.value = false;
    }
  }

  async function register(loginVal: string, email: string, password: string): Promise<boolean> {
    loading.value = true;
    error.value = null;
    try {
      const user = await getAuthApi().register(loginVal, email, password);
      userId.value = user.id;
      useUserStore().setCurrent(user);

      return true;
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : String(e);

      return false;
    } finally {
      loading.value = false;
    }
  }

  async function guestLogin(): Promise<boolean> {
    loading.value = true;
    error.value = null;
    try {
      userId.value = 0;
      useUserStore().setGuest();

      return true;
    } finally {
      loading.value = false;
    }
  }

  async function findUser(loginOrEmail: string) {
    return getAuthApi().findUser(loginOrEmail);
  }

  async function resetPassword(loginVal: string, resetToken: string, newPassword: string): Promise<boolean> {
    return getAuthApi().resetPassword(loginVal, resetToken, newPassword);
  }

  async function logout(): Promise<void> {
    try {
      await getAuthApi().logout();
    } finally {
      userId.value = null;
      useUserStore().clearCurrent();
    }
  }

  async function checkAuth(): Promise<boolean> {
    if (userId.value !== null) return true;
    try {
      const user = await getAuthApi().getCurrentUser();
      if (user) {
        userId.value = user.id;
        useUserStore().setCurrent(user);

        return true;
      }
    } catch {
      userId.value = null;
    }

    return false;
  }

  return {
    userId,
    loading,
    error,
    passwordPolicy,
    isAuthenticated,
    isGuest,
    login,
    register,
    guestLogin,
    findUser,
    resetPassword,
    logout,
    checkAuth,
    fetchPasswordPolicy,
  };
});
