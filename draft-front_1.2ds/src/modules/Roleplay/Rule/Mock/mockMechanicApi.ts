import type { IMechanicApi } from '@/modules/Roleplay/Rule/Interface/IMechanicApi';
import {
  createMechanic,
  fetchMechanic,
  fetchMechanics,
  updateMechanic,
} from '@/modules/Roleplay/Rule/Mock/mockMechanics';

export const mockMechanicApi: IMechanicApi = {
  getMechanics: fetchMechanics,
  getMechanic: fetchMechanic,
  createMechanic,
  updateMechanic,
};
