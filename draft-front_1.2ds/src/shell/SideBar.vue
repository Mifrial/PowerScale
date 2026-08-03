<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/modules/Core/Auth/Store/auth'
import { useUserStore } from '@/modules/Core/User/Store/users'
import { isAdmin as checkIsAdmin, getAdminSections } from '@/modules/Core/User/init'
import { navItems } from '@/shell/navItems'

const props = defineProps<{ collapsed: boolean }>()
const emit = defineEmits<{ 'update:collapsed': [value: boolean] }>()

const router = useRouter()
const auth = useAuthStore()
const userStore = useUserStore()

const isCollapsed = computed(() => props.collapsed)
const showLogoutDialog = ref(false)
const adminOpen = ref(false)

function expand() {
  emit('update:collapsed', false)
}

const isAdmin = computed(() => checkIsAdmin(userStore.currentUser))

function confirmLogout() {
  showLogoutDialog.value = false
  auth.logout()
}

const adminItems = getAdminSections().map(s => ({ icon: s.icon, label: s.title, to: s.to }))
</script>

<template>
  <v-navigation-drawer
    :rail="isCollapsed"
    permanent
    @click="isCollapsed && expand()"
  >
    <template #prepend>
      <v-list-item
        class="px-2 py-3"
        :to="userStore.currentUser?.id ? '/users/' + userStore.currentUser.id : undefined"
        :active="false"
      >
        <template #prepend>
          <v-avatar color="primary" size="36">
            <span class="text-body-2 font-weight-medium text-white">{{ userStore.avatarLetters }}</span>
          </v-avatar>
        </template>
        <v-list-item-title class="font-weight-medium text-body-2">
          {{ userStore.username }}
        </v-list-item-title>
        <v-list-item-subtitle class="text-caption">
          @{{ userStore.userLogin }}
        </v-list-item-subtitle>
        <template #append>
          <v-btn
            v-if="auth.isAuthenticated && !auth.isGuest"
            icon
            variant="text"
            size="x-small"
            color="on-surface"
            aria-label="Выйти"
            @click.stop="showLogoutDialog = true"
          >
            <v-icon size="18">mdi-logout</v-icon>
          </v-btn>
          <v-btn
            v-else
            icon
            variant="text"
            size="x-small"
            color="primary"
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
            <v-list-item
              v-bind="props"
              prepend-icon="mdi-shield-crown"
              title="Администрирование"
            />
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

    <v-dialog v-model="showLogoutDialog" max-width="360">
      <v-card>
        <v-card-title class="text-body-1 font-weight-medium">Выйти</v-card-title>
        <v-card-text class="text-body-2">Вы уверены, что хотите выйти?</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showLogoutDialog = false">Отмена</v-btn>
          <v-btn color="error" variant="tonal" @click="confirmLogout">Выйти</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-navigation-drawer>
</template>
