import type { IMechanicApi } from '@/modules/Roleplay/Mechanic/Interface/IMechanicApi';
import {
  createMechanic,
  fetchMechanic,
  fetchMechanics,
  updateMechanic,
} from '@/modules/Roleplay/Mechanic/Mock/mockMechanics';

export const mockMechanicApi: IMechanicApi = {
  getMechanics: fetchMechanics,
  getMechanic: fetchMechanic,
  createMechanic,
  updateMechanic,
};
