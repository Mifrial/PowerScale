<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import type { CreateUserData } from '@/modules/Core/User/Dto/CreateUserData';
import type { UpdateUserData } from '@/modules/Core/User/Dto/UpdateUserData';
import type { VForm } from 'vuetify/components';
import { useGroupStore } from '@/modules/Core/User/Store/groups';
import PasswordField from '@/modules/Core/UI/Component/Input/PasswordField.vue';

const props = defineProps<{
  mode: 'create' | 'edit';
  user?: User;
  saving?: boolean;
}>();

const emit = defineEmits<{
  submit: [data: CreateUserData | UpdateUserData];
}>();

const formRef = ref<VForm | null>(null);
const groupStore = useGroupStore();

const form = reactive({
  name: '',
  surname: '',
  nickname: '',
  login: '',
  email: '',
  password: '',
  groups: [] as number[],
  active: true,
});

function applyUser(user: User | undefined): void {
  form.name = user?.name ?? '';
  form.surname = user?.surname ?? '';
  form.nickname = user?.nickname ?? '';
  form.login = user?.login ?? '';
  form.email = user?.email ?? '';
  form.groups = [...(user?.groups ?? [])];
  form.active = user?.active ?? true;
}

watch(() => props.user, applyUser, { immediate: true });

onMounted(() => {
  void groupStore.findPage({ limit: 500, offset: 0 });
});

function required(v: unknown): string | boolean {
  if (!v || (typeof v === 'string' && !v.trim())) return 'Обязательное поле';

  return true;
}

async function handleSubmit() {
  const valid = await formRef.value?.validate();
  if (!valid?.valid) return;
  const clean = (val: string) => val || undefined;
  if (props.mode === 'create') {
    emit('submit', {
      name: form.name,
      surname: clean(form.surname),
      nickname: clean(form.nickname),
      login: form.login,
      email: form.email.trim() === '' ? null : form.email,
      password: form.password,
      groups: form.groups,
    });
  } else {
    const data: UpdateUserData = {
      name: form.name,
      surname: clean(form.surname),
      nickname: clean(form.nickname),
      email: form.email.trim() === '' ? null : form.email,
      groups: form.groups,
      active: form.active,
    };
    emit('submit', data);
  }
}

defineExpose({ submit: handleSubmit });
</script>

<template>
  <v-form ref="formRef" @submit.prevent="handleSubmit">
    <div class="profile-cards-grid">
      <v-card class="profile-card">
        <v-card-item>
          <v-card-title class="text-body-1 font-weight-bold">
            <v-icon start size="small" class="mb-1">mdi-account-outline</v-icon>
            Основная информация
          </v-card-title>
        </v-card-item>
        <v-divider />
        <v-card-text>
          <v-text-field v-model="form.name" label="Имя" :rules="[required]" variant="outlined" density="compact" class="mb-2" />
          <v-text-field v-model="form.surname" label="Фамилия" variant="outlined" density="compact" class="mb-2" />
          <v-text-field v-model="form.nickname" label="Псевдоним" variant="outlined" density="compact" class="mb-2" />
          <v-switch v-if="mode === 'edit'" v-model="form.active" label="Активен" color="success" hide-details />
        </v-card-text>
      </v-card>

      <v-card class="profile-card">
        <v-card-item>
          <v-card-title class="text-body-1 font-weight-bold">
            <v-icon start size="small" class="mb-1">mdi-shield-lock-outline</v-icon>
            Авторизация
          </v-card-title>
        </v-card-item>
        <v-divider />
        <v-card-text>
          <v-text-field
            v-model="form.login"
            label="Логин"
            :rules="[required]"
            :disabled="mode === 'edit'"
            variant="outlined"
            density="compact"
            class="mb-2"
          />
          <v-text-field v-model="form.email" label="Email" type="email" variant="outlined" density="compact" class="mb-2" />
          <PasswordField
            v-if="mode === 'create'"
            v-model="form.password"
            label="Пароль"
            :rules="[required]"
            autocomplete="new-password"
          />
        </v-card-text>
      </v-card>

      <v-card class="profile-card profile-card--full">
        <v-card-item>
          <v-card-title class="text-body-1 font-weight-bold">
            <v-icon start size="small" class="mb-1">mdi-account-group-outline</v-icon>
            Группы
          </v-card-title>
        </v-card-item>
        <v-divider />
        <v-card-text>
          <v-select
            v-model="form.groups"
            label="Группы"
            :items="groupStore.groups"
            item-title="name"
            item-value="id"
            multiple
            chips
            closable-chips
            variant="outlined"
            density="compact"
            hide-details
          />
        </v-card-text>
      </v-card>
    </div>
  </v-form>
</template>

<style scoped>
.profile-cards-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}
.profile-card {
  flex: 1 1 320px;
  min-width: 280px;
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.profile-card--full {
  flex-basis: 100%;
}
</style>
