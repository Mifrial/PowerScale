<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { useNotificationStore } from '@/modules/Messages/Notifications/Store/notifications';
import { debounce } from '@/modules/Core/UI/Utils/debounce';
import ChipFilter from '@/modules/Core/UI/Component/ChipFilter.vue';
import NotificationList from '@/modules/Messages/Notifications/Component/NotificationList.vue';
import { filters } from '@/modules/Messages/Notifications/Constant/notificationsFilters';

const store = useNotificationStore();
const localSearch = ref(store.searchQuery);

const debouncedUpdateSearch = debounce((value: string) => {
  store.searchQuery = value;
}, 300);

watch(localSearch, (value) => {
  debouncedUpdateSearch(value ?? '');
});

onMounted(() => {
  store.fetchData();
});
</script>

<template>
  <v-container fluid>
    <v-text-field
      v-model="localSearch"
      prepend-inner-icon="mdi-magnify"
      placeholder="Поиск по уведомлениям"
      density="compact"
      hide-details
      clearable
      variant="outlined"
      class="mb-2 search-field"
      color="primary"
    />

    <div class="d-flex ga-2 mb-4 align-center">
      <ChipFilter v-model="store.filter" :items="filters" />
      <v-spacer />
      <v-btn v-if="store.unreadCount > 0" variant="text" color="primary" size="small" @click="store.markAllAsRead()">
        Отметить все прочитанными
      </v-btn>
    </div>

    <v-alert v-if="store.error" type="error" class="mb-4">
      {{ store.error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="store.fetchData()">Повторить</v-btn>
      </template>
    </v-alert>

    <v-alert v-if="store.actionError" type="error" class="mb-4" closable @click:close="store.actionError = ''">
      {{ store.actionError }}
    </v-alert>

    <NotificationList :items="store.items" icon-size="24" action-size="small" @action="(p) => store.markAsRead(p.id)">
      <template #empty>
        {{ store.filter === 'unread' ? 'Нет непрочитанных уведомлений' : 'Нет уведомлений' }}
      </template>
    </NotificationList>

    <div v-if="store.totalPages > 1" class="d-flex justify-center mt-6">
      <v-pagination v-model="store.page" :length="store.totalPages" :total-visible="7" color="primary" />
    </div>
  </v-container>
</template>

<style scoped>
.search-field :deep(.v-field__outline) {
  color: rgba(var(--v-theme-divider), var(--v-border-opacity));
  --v-field-border-width: 1px;
}
</style>
