<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUserStore } from '@/modules/Core/User/Store/users';
import type { User } from '@/modules/Core/User/Dto/User';
import type { CreateUserData } from '@/modules/Core/User/Dto/CreateUserData';
import type { UpdateUserData } from '@/modules/Core/User/Dto/UpdateUserData';
import { getUserEditSections } from '@/modules/Core/User/init';
import UserForm from '@/modules/Core/User/Component/UserForm.vue';
import { initials as userInitials } from '@/modules/Core/User/Utils/initials';
import { displayName as userDisplayName } from '@/modules/Core/User/Utils/displayName';

const route = useRoute();
const router = useRouter();
const store = useUserStore();

const isNew = computed(() => route.name === 'UserNew');
const userEditSections = computed(() => (isNew.value ? [] : getUserEditSections()));
const user = ref<User | null>(null);
const loading = ref(!isNew.value);
const error = ref<string | null>(null);
const saving = ref(false);
const formRef = ref<{ submit: () => Promise<void> } | null>(null);

const snackbar = ref({ show: false, text: '', color: '' });

const initials = computed(() => {
  if (!user.value) return '??';

  return userInitials(user.value.name, user.value.surname);
});

const displayName = computed(() => {
  if (!user.value) return '';

  return userDisplayName(user.value.name, user.value.surname, user.value.login);
});

function cancel(): void {
  if (isNew.value) {
    void router.push('/users');

    return;
  }

  void router.push(`/users/${Number(route.params.id)}`);
}

async function handleSubmit(data: CreateUserData | UpdateUserData) {
  saving.value = true;
  try {
    if (isNew.value) {
      const created = await store.createUser(data as CreateUserData);
      snackbar.value = { show: true, text: 'Пользователь создан', color: 'success' };
      await router.push(`/users/${created.id}`);
    } else {
      const id = Number(route.params.id);
      const updated = await store.updateUser(id, data as UpdateUserData);
      store.setProfileUser(updated);
      snackbar.value = { show: true, text: 'Пользователь обновлён', color: 'success' };
      await router.push(`/users/${id}`);
    }
  } catch {
    snackbar.value = { show: true, text: 'Ошибка сохранения', color: 'error' };
  } finally {
    saving.value = false;
  }
}

async function loadUser() {
  loading.value = true;
  error.value = null;
  try {
    user.value = await store.getUser(Number(route.params.id));
    store.setProfileUser(user.value);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки пользователя';
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  if (!isNew.value) {
    await loadUser();
  }
});
</script>

<template>
  <v-container fluid>
    <Teleport to="#editor-actions">
      <v-btn variant="tonal" size="small" @click="cancel">Отмена</v-btn>
      <v-btn
        color="primary"
        size="small"
        prepend-icon="mdi-check"
        :loading="saving"
        :disabled="loading || !!error"
        @click="formRef?.submit()"
      >
        {{ isNew ? 'Создать' : 'Сохранить' }}
      </v-btn>
    </Teleport>

    <div v-if="isNew || user" class="d-flex ga-6">
      <div v-if="user" class="profile-side text-center">
        <v-avatar color="primary" size="120" class="mb-2">
          <span class="text-h3 font-weight-medium text-white">{{ initials }}</span>
        </v-avatar>
        <div class="text-h6 font-weight-medium text-center">{{ displayName }}</div>
        <div class="text-caption text-medium-emphasis text-center">@{{ user.login }}</div>
      </div>

      <div class="profile-main">
        <UserForm
          ref="formRef"
          :mode="isNew ? 'create' : 'edit'"
          :user="user ?? undefined"
          :saving="saving"
          @submit="handleSubmit"
        />

        <div v-if="userEditSections.length" class="mt-4">
          <component :is="section.component" v-for="section in userEditSections" :key="section.id" />
        </div>
      </div>
    </div>

    <div v-else-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>
    <div v-else-if="error" class="text-center pa-8">
      <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
      <p class="text-body-1 mb-4">{{ error }}</p>
      <v-btn color="primary" @click="loadUser">Попробовать снова</v-btn>
    </div>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</template>

<style scoped>
.profile-side {
  width: 180px;
  flex-shrink: 0;
}
.profile-main {
  flex: 1;
  min-width: 0;
}
</style>
