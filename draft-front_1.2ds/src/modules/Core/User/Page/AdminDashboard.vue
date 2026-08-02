<template>
  <v-container>
    <h1 class="text-h5 mb-4">Администрирование</h1>

    <v-row>
      <v-col cols="12" sm="6" md="4">
        <v-card to="/admin/groups" class="admin-card">
          <v-card-title class="d-flex align-center">
            <v-icon class="mr-2">mdi-account-group</v-icon>
            Группы пользователей
          </v-card-title>
          <v-card-text>
            Управление группами и правами доступа
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" color="primary">Перейти</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="4">
        <v-card to="/admin/keywords" class="admin-card">
          <v-card-title class="d-flex align-center">
            <v-icon class="mr-2">mdi-tag-multiple</v-icon>
            Признаки
          </v-card-title>
          <v-card-text>
            Справочник тегов для правил
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" color="primary">Перейти</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="4">
        <v-card to="/admin/notification-templates" class="admin-card">
          <v-card-title class="d-flex align-center">
            <v-icon class="mr-2">mdi-bell-cog</v-icon>
            Шаблоны уведомлений
          </v-card-title>
          <v-card-text>
            Настройка шаблонов уведомлений
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" color="primary">Перейти</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<style scoped>
.admin-card {
  height: 100%;
  transition: transform 0.2s, box-shadow 0.2s;
}
.admin-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-scrim), var(--v-shadow-sm-opacity));
}
</style>
