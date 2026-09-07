import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { CreateMechanicData } from '@/modules/Roleplay/Mechanic/Dto/CreateMechanicData';
import type { UpdateMechanicData } from '@/modules/Roleplay/Mechanic/Dto/UpdateMechanicData';
import { getMechanicApi } from '@/modules/Roleplay/Mechanic/init';

export const useMechanicStore = defineStore('mechanics', () => {
  const mechanics = ref<Mechanic[]>([]);
  const currentMechanic = ref<Mechanic | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function fetchMechanics(signal?: AbortSignal) {
    loading.value = true;
    error.value = null;
    try {
      mechanics.value = await getMechanicApi().getMechanics(signal);
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      error.value = 'Не удалось загрузить механики';
    } finally {
      loading.value = false;
    }
  }

  async function fetchMechanic(id: number, signal?: AbortSignal): Promise<Mechanic> {
    const mechanic = await getMechanicApi().getMechanic(id, signal);
    currentMechanic.value = mechanic;

    return mechanic;
  }

  async function createMechanic(data: CreateMechanicData, signal?: AbortSignal): Promise<Mechanic> {
    const mechanic = await getMechanicApi().createMechanic(data, signal);
    mechanics.value.push(mechanic);

    return mechanic;
  }

  async function updateMechanic(id: number, data: UpdateMechanicData, signal?: AbortSignal): Promise<Mechanic> {
    const mechanic = await getMechanicApi().updateMechanic(id, data, signal);
    const idx = mechanics.value.findIndex((row) => row.id === id);
    if (idx !== -1) mechanics.value[idx] = mechanic;
    if (currentMechanic.value?.id === id) currentMechanic.value = mechanic;

    return mechanic;
  }

  function clearCurrent() {
    currentMechanic.value = null;
  }

  return {
    mechanics,
    currentMechanic,
    loading,
    error,
    fetchMechanics,
    fetchMechanic,
    createMechanic,
    updateMechanic,
    clearCurrent,
  };
});
