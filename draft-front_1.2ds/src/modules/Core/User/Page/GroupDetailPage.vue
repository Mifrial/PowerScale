<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGroupStore } from '@/modules/Core/User/Store/groups';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import PermissionMatrix from '@/modules/Core/User/Component/PermissionMatrix.vue';

const route = useRoute();
const router = useRouter();
const store = useGroupStore();
const { signal } = useAbortable();

const group = ref(store.currentGroup);
const showDeactivateDialog = ref(false);

onMounted(async () => {
  const id = Number(route.params.id);
  await store.fetchGroup(id, signal.value);
  await store.fetchGroupMembers(id, signal.value);
  group.value = store.currentGroup;
});

async function deactivate() {
  if (!group.value) return;
  await store.deactivateGroup(group.value.id, signal.value);
  group.value = store.currentGroup;
  showDeactivateDialog.value = false;
}
</script>

<template>
  <v-container v-if="group">
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ group.name }}</h1>
      <v-chip :color="group.active ? 'success' : 'grey'" variant="tonal" size="small" class="ml-3">
        {{ group.active ? 'Активна' : 'Деактивирована' }}
      </v-chip>
      <v-spacer />
      <v-btn variant="text" prepend-icon="mdi-pencil" @click="router.push(`/admin/groups/${group.id}/edit`)">
        Редактировать
      </v-btn>
      <v-btn
        v-if="group.active"
        variant="text"
        color="error"
        prepend-icon="mdi-cancel"
        @click="showDeactivateDialog = true"
      >
        Деактивировать
      </v-btn>
    </div>

    <v-card class="mb-4">
      <v-card-title>Участники ({{ group.memberCount }})</v-card-title>
      <v-card-text>
        <v-list v-if="store.groupMembers.length > 0">
          <v-list-item v-for="member in store.groupMembers" :key="member.id" :to="`/users/${member.id}`">
            <template #prepend>
              <v-avatar color="primary" size="36">
                <span class="text-body-2">{{ member.initials }}</span>
              </v-avatar>
            </template>
            <v-list-item-title>{{ member.name }}</v-list-item-title>
            <v-list-item-subtitle>@{{ member.login }}</v-list-item-subtitle>
          </v-list-item>
        </v-list>
        <div v-else class="text-body-2 text-medium-emphasis">Нет участников</div>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-title>Права доступа</v-card-title>
      <v-card-text>
        <PermissionMatrix v-model="group.permissions" disabled />
      </v-card-text>
    </v-card>

    <v-dialog v-model="showDeactivateDialog" max-width="400">
      <v-card>
        <v-card-title>Деактивировать группу?</v-card-title>
        <v-card-text> Группа «{{ group.name }}» перестанет давать права участникам. </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showDeactivateDialog = false">Отмена</v-btn>
          <v-btn color="error" @click="deactivate">Деактивировать</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
