import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { getAuthApi } from '@/modules/Core/Auth/init';
import { currentUserSessionService } from '@/modules/Core/User/init';
import type { Session } from '@/modules/Core/Auth/Dto/Session';
import type { PasswordPolicy } from '@/modules/Core/Auth/Dto/PasswordPolicy';
import { DEFAULT_PASSWORD_POLICY } from '@/modules/Core/Auth/Constant/defaultPasswordPolicy';

export const useAuthStore = defineStore('auth', () => {
  const session = ref<Session>({ kind: 'anon' });
  const loading = ref(false);
  const error = ref<string | null>(null);
  const passwordPolicy = ref<PasswordPolicy>({ ...DEFAULT_PASSWORD_POLICY });

  const userId = computed(() => (session.value.kind === 'user' ? session.value.userId : null));
  const isAuthenticated = computed(() => session.value.kind !== 'anon');
  const isGuest = computed(() => session.value.kind === 'guest');

  function setUserSession(userIdValue: number): void {
    session.value = { kind: 'user', userId: userIdValue };
  }

  function setGuestSession(): void {
    session.value = { kind: 'guest' };
  }

  function setAnonSession(): void {
    session.value = { kind: 'anon' };
  }

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
      setUserSession(user.id);
      currentUserSessionService.setCurrent(user);

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
      setUserSession(user.id);
      currentUserSessionService.setCurrent(user);

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
      setGuestSession();
      currentUserSessionService.setGuest();

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
    loading.value = true;
    error.value = null;
    try {
      await getAuthApi().logout();
      setAnonSession();
      currentUserSessionService.clearCurrent();
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : String(e);
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function checkAuth(): Promise<boolean> {
    if (session.value.kind !== 'anon') return true;
    try {
      const user = await getAuthApi().getCurrentUser();
      if (user) {
        setUserSession(user.id);
        currentUserSessionService.setCurrent(user);

        return true;
      }
    } catch {
      setAnonSession();
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
