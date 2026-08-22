import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const useGameStore = defineStore('games', () => {
  const games = ref<Game[]>([]);
  const currentGame = ref<GameDetail | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const detailLoading = ref(false);
  const detailError = ref<string | null>(null);

  async function fetchGames(signal?: AbortSignal) {
    loading.value = true;
    error.value = null;
    try {
      games.value = await getGameApi().getGames(signal);
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      error.value = 'Не удалось загрузить игры';
    } finally {
      loading.value = false;
    }
  }

  async function fetchGame(id: number, signal?: AbortSignal): Promise<GameDetail | null> {
    detailLoading.value = true;
    detailError.value = null;
    try {
      const detail = await getGameApi().getGame(id, signal);
      // Снимок, а не живая ссылка: мок-«бэк» мутирует общий массив gameDetails, и присвоение
      // уже записанного им объекта в members (store.updateMember) не триггерило бы реактивность
      // (hasChanged = false при той же ссылке) — UI не перерисовывался. Настоящий API отдаёт JSON-копию.
      const snapshot = structuredClone(detail);
      currentGame.value = snapshot;

      return snapshot;
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return null;
      detailError.value = 'Не удалось загрузить игру';

      return null;
    } finally {
      detailLoading.value = false;
    }
  }

  function clearCurrent() {
    currentGame.value = null;
  }

  // Управление участниками: мутируем открытую карточку локально (роль/права/состав),
  // чтобы не перезагружать деталь (сброс вкладок) на каждое действие.
  function updateMember(member: GameMember) {
    if (!currentGame.value) return;
    const idx = currentGame.value.members.findIndex((m) => m.userId === member.userId);
    if (idx !== -1) currentGame.value.members[idx] = member;
  }

  function addMember(member: GameMember) {
    if (!currentGame.value) return;
    if (currentGame.value.members.some((m) => m.userId === member.userId)) return;
    currentGame.value.members.push(member);
    currentGame.value.game.memberCount = currentGame.value.members.length;
  }

  function removeMember(userId: number) {
    if (!currentGame.value) return;
    currentGame.value.members = currentGame.value.members.filter((m) => m.userId !== userId);
    currentGame.value.game.memberCount = currentGame.value.members.length;
  }

  // Локальная мутация открытой карточки после updateGame из вкладки «Чат игры»
  // (снимок, как fetchGame — стор независим от мок-бэка, D25).
  function applyGameUpdate(detail: GameDetail) {
    currentGame.value = structuredClone(detail);
  }

  return {
    games,
    currentGame,
    loading,
    error,
    detailLoading,
    detailError,
    fetchGames,
    fetchGame,
    clearCurrent,
    updateMember,
    addMember,
    removeMember,
    applyGameUpdate,
  };
});
