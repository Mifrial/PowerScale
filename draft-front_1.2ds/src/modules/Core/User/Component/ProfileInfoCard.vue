<script setup lang="ts">
import type { User } from '@/modules/Core/User/Dto/User';
import { formatUnix } from '@/modules/Core/User/Utils/formatUnix';

const props = defineProps<{
  user: User;
  canEdit: boolean;
  editing: boolean;
  saving: boolean;
}>();

const formName = defineModel<string>('formName', { required: true });
const formSurname = defineModel<string>('formSurname', { required: true });
const formNickname = defineModel<string>('formNickname', { required: true });

const emit = defineEmits<{
  start: [];
  save: [];
  cancel: [];
}>();
</script>

<template>
  <v-card>
    <v-card-item>
      <v-card-title class="text-body-1 font-weight-bold">
        <v-icon start size="small" class="mb-1">mdi-account-outline</v-icon>
        Основная информация
      </v-card-title>
      <template v-if="canEdit && !editing" #append>
        <v-btn icon variant="text" size="small" aria-label="Редактировать" @click="emit('start')">
          <v-icon size="small">mdi-pencil</v-icon>
        </v-btn>
      </template>
    </v-card-item>
    <v-divider />
    <v-list>
      <v-list-item>
        <template #title>Имя</template>
        <template v-if="editing" #subtitle>
          <v-text-field v-model="formName" density="compact" hide-details variant="outlined" />
        </template>
        <template v-else #subtitle>{{ props.user.name || '—' }}</template>
      </v-list-item>

      <v-list-item>
        <template #title>Фамилия</template>
        <template v-if="editing" #subtitle>
          <v-text-field v-model="formSurname" density="compact" hide-details variant="outlined" />
        </template>
        <template v-else #subtitle>{{ props.user.surname || '—' }}</template>
      </v-list-item>

      <v-list-item>
        <template #title>Псевдоним</template>
        <template v-if="editing" #subtitle>
          <v-text-field v-model="formNickname" density="compact" hide-details variant="outlined" />
        </template>
        <template v-else #subtitle>
          <span v-if="props.user.nickname">@{{ props.user.nickname }}</span>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
      </v-list-item>

      <v-list-item>
        <template #title>Статус</template>
        <template #subtitle>
          <v-chip :color="props.user.active ? 'success' : 'grey'" size="x-small" label>
            {{ props.user.active ? 'Активен' : 'Отключён' }}
          </v-chip>
          <template v-if="!props.user.active">
            <div v-if="props.user.deactivateReason" class="text-caption mt-1">
              Причина: {{ props.user.deactivateReason }}
            </div>
            <div v-if="props.user.deactivatedUntil" class="text-caption">
              Отключён до: {{ formatUnix(props.user.deactivatedUntil) }}
            </div>
          </template>
        </template>
      </v-list-item>
    </v-list>
    <v-card-actions v-if="canEdit && editing">
      <v-btn color="primary" :loading="saving" @click="emit('save')">
        <v-icon start size="small">mdi-check</v-icon>Сохранить
      </v-btn>
      <v-btn variant="text" color="medium-emphasis" @click="emit('cancel')">
        <v-icon start size="small">mdi-close</v-icon>Отмена
      </v-btn>
    </v-card-actions>
  </v-card>
</template>
