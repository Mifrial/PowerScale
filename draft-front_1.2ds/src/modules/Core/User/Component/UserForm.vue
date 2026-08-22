<script setup lang="ts">
import { ref, reactive } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import type { CreateUserData } from '@/modules/Core/User/Dto/CreateUserData';
import type { UpdateUserData } from '@/modules/Core/User/Dto/UpdateUserData';

const props = defineProps<{
  mode: 'create' | 'edit';
  user?: User;
  saving?: boolean;
}>();

const emit = defineEmits<{
  submit: [data: CreateUserData | UpdateUserData];
  cancel: [];
}>();

import type { VForm } from 'vuetify/components';

const formRef = ref<VForm | null>(null);

const form = reactive({
  name: props.user?.name ?? '',
  surname: props.user?.surname ?? '',
  nickname: props.user?.nickname ?? '',
  login: props.user?.login ?? '',
  email: props.user?.email ?? '',
  password: '',
  groups: props.user?.groups ?? [],
  active: props.user?.active ?? true,
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
      email: form.email,
      password: form.password,
      groups: form.groups,
    });
  } else {
    const data: UpdateUserData = {
      name: form.name,
      surname: clean(form.surname),
      nickname: clean(form.nickname),
      email: form.email,
      groups: form.groups,
      active: form.active,
    };
    emit('submit', data);
  }
}
</script>

<template>
  <v-form ref="formRef" @submit.prevent="handleSubmit">
    <v-text-field v-model="form.name" label="Имя" :rules="[required]" />
    <v-text-field v-model="form.surname" label="Фамилия" />
    <v-text-field v-model="form.nickname" label="Псевдоним" />
    <v-text-field v-model="form.login" label="Логин" :rules="[required]" :disabled="mode === 'edit'" />
    <v-text-field v-model="form.email" label="Email" type="email" :rules="[required]" />
    <v-text-field
      v-if="mode === 'create'"
      v-model="form.password"
      label="Пароль"
      type="password"
      :rules="mode === 'create' ? [required] : []"
    />
    <v-combobox v-model="form.groups" label="Группы" multiple chips small-chips deletable-chips />
    <v-switch v-if="mode === 'edit'" v-model="form.active" label="Активен" color="success" />
    <div class="d-flex ga-2 mt-2">
      <v-btn type="submit" color="primary" :loading="saving">{{ mode === 'create' ? 'Создать' : 'Сохранить' }}</v-btn>
      <v-btn variant="text" @click="$emit('cancel')">Отмена</v-btn>
    </div>
  </v-form>
</template>
