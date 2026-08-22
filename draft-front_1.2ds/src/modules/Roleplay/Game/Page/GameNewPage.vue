<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';
import GameForm from '@/modules/Roleplay/Game/Component/GameForm.vue';

const router = useRouter();

const submitting = ref(false);
const submitError = ref<string | null>(null);

async function handleSubmit(data: CreateGameData): Promise<void> {
  submitting.value = true;
  submitError.value = null;
  try {
    const detail = await getGameApi().createGame(data);
    void router.push(`/games/${detail.game.id}`);
  } catch (e) {
    submitError.value = e instanceof Error ? e.message : 'Не удалось создать игру';
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <v-container>
    <h1 class="text-h5 font-weight-bold mb-1">Новая игра</h1>
    <p class="text-body-1 text-medium-emphasis mb-6">Задайте правила, статус и лимиты игры.</p>

    <GameForm
      submit-label="Создать игру"
      :submit-loading="submitting"
      :submit-error="submitError"
      @submit="handleSubmit"
      @cancel="router.push('/games')"
    />
  </v-container>
</template>
