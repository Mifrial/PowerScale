<script setup lang="ts">
import type { NotificationButton } from '@/modules/Messages/Notifications/Dto/NotificationButton';
import { actionTypes } from '@/modules/Messages/Notifications/Constant/templateActionTypes';
import { templateSpecService } from '@/modules/Messages/Notifications/Service/Instance/templateSpecService';

const buttons = defineModel<NotificationButton[]>('buttons', { required: true });
</script>

<template>
  <div>
    <div class="d-flex align-center mb-3">
      <h3 class="text-h6">Кнопки действий</h3>
      <v-spacer />
      <v-btn size="small" prepend-icon="mdi-plus" @click="templateSpecService.addButton(buttons)">
        Добавить кнопку
      </v-btn>
    </div>

    <v-card v-for="(btn, idx) in buttons" :key="idx" variant="outlined" class="mb-3">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4">
            <v-text-field v-model="btn.label" label="Текст кнопки" density="compact" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="btn.actionType" :items="actionTypes" label="Тип действия" density="compact" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="btn.action" label="Действие / URL" density="compact" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn size="small" color="error" variant="text" @click="templateSpecService.removeButton(buttons, idx)">
          Удалить
        </v-btn>
      </v-card-actions>
    </v-card>

    <div v-if="buttons.length === 0" class="text-body-2 text-medium-emphasis text-center py-4">Нет кнопок действий</div>
  </div>
</template>
