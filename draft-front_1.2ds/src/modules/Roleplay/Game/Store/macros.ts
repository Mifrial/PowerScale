import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { UserMacro } from '@/modules/Roleplay/Game/Dto/UserMacro';
import type { CreateMacroData } from '@/modules/Roleplay/Game/Dto/CreateMacroData';
import type { UpdateMacroData } from '@/modules/Roleplay/Game/Dto/UpdateMacroData';
import { getMacroApi } from '@/modules/Roleplay/Game/init';

export const useMacrosStore = defineStore('macros', () => {
  const macros = ref<UserMacro[]>([]);
  const error = ref<string | null>(null);

  async function fetchMacros(signal?: AbortSignal) {
    error.value = null;
    try {
      macros.value = await getMacroApi().getMyMacros(signal);
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      error.value = 'Не удалось загрузить макросы';
    }
  }

  async function createMacro(data: CreateMacroData, signal?: AbortSignal): Promise<UserMacro> {
    const macro = await getMacroApi().createMacro(data, signal);
    macros.value.push(macro);

    return macro;
  }

  async function updateMacro(id: number, data: UpdateMacroData, signal?: AbortSignal): Promise<UserMacro> {
    const macro = await getMacroApi().updateMacro(id, data, signal);
    const idx = macros.value.findIndex((m) => m.id === id);
    if (idx !== -1) macros.value[idx] = macro;

    return macro;
  }

  async function removeMacro(id: number, signal?: AbortSignal): Promise<void> {
    await getMacroApi().deleteMacro(id, signal);
    macros.value = macros.value.filter((m) => m.id !== id);
  }

  return { macros, error, fetchMacros, createMacro, updateMacro, removeMacro };
});
