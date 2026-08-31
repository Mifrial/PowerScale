<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import { isAdmin as checkIsAdmin, getAdminSections, useCurrentUser } from '@/modules/Core/User/init';
import { navItems } from '@/shell/navItems';
import LogoutConfirmDialog from '@/modules/Core/Auth/Component/LogoutConfirmDialog.vue';

const props = defineProps<{ collapsed: boolean }>();
const emit = defineEmits<{ 'update:collapsed': [value: boolean] }>();

const router = useRouter();
const auth = useAuthStore();
const { currentUser, username, userLogin, avatarLetters } = useCurrentUser();

const isCollapsed = computed(() => props.collapsed);
const showLogoutDialog = ref(false);
const adminOpen = ref(false);

function expand() {
  emit('update:collapsed', false);
}

function openProfile(): void {
  const userId = currentUser.value?.id;
  if (userId) void router.push('/users/' + userId);
}

function openLogoutDialog(): void {
  showLogoutDialog.value = true;
}

const isAdmin = computed(() => checkIsAdmin(currentUser.value));

const adminItems = getAdminSections().map((s) => ({ icon: s.icon, label: s.title, to: s.to }));
</script>

<template>
  <v-navigation-drawer :rail="isCollapsed" permanent @click="isCollapsed && expand()">
    <template #prepend>
      <v-list-item class="px-2 py-3" :active="false" @click="openProfile">
        <template #prepend>
          <v-avatar color="primary" size="36">
            <span class="text-body-2 font-weight-medium text-white">{{ avatarLetters }}</span>
          </v-avatar>
        </template>
        <v-list-item-title class="font-weight-medium text-body-2">
          {{ username }}
        </v-list-item-title>
        <v-list-item-subtitle class="text-caption"> @{{ userLogin }} </v-list-item-subtitle>
        <template #append>
          <v-btn
            v-if="auth.isAuthenticated && !auth.isGuest"
            icon
            variant="text"
            size="x-small"
            color="on-surface"
            type="button"
            aria-label="Выйти"
            @click.stop="openLogoutDialog"
          >
            <v-icon size="18">mdi-logout</v-icon>
          </v-btn>
          <v-btn
            v-else
            icon
            variant="text"
            size="x-small"
            color="primary"
            type="button"
            aria-label="Войти"
            @click.stop="router.push('/login')"
          >
            <v-icon size="18">mdi-login</v-icon>
          </v-btn>
        </template>
      </v-list-item>
    </template>

    <v-divider />

    <v-list density="compact" nav>
      <v-list-item
        v-for="item in navItems"
        :key="item.to"
        :prepend-icon="item.icon"
        :title="item.label"
        :to="item.to"
        :exact="item.exact"
        color="primary"
      />
    </v-list>

    <template #append>
      <v-divider />
      <v-list density="compact" nav>
        <v-list-group v-if="isAdmin" v-model="adminOpen">
          <template #activator="{ props }">
            <v-list-item v-bind="props" prepend-icon="mdi-shield-crown" title="Администрирование" />
          </template>
          <v-list-item
            v-for="item in adminItems"
            :key="item.to"
            :prepend-icon="item.icon"
            :title="item.label"
            :to="item.to"
            color="primary"
          />
        </v-list-group>
      </v-list>
    </template>
  </v-navigation-drawer>
  <LogoutConfirmDialog v-model="showLogoutDialog" />
</template>
