import type { MechanicPayload } from '@/modules/Roleplay/Mechanic/Dto/MechanicPayload';

/** Тип payload, который фронт уже различает. Не проверка полей и не гарантия runtime. */
export const KNOWN_MECHANIC_PAYLOAD_TYPES = {
  purchase_surcharge: true,
  roll: true,
  roll_score_adjust: true,
  injury_efficiency: true,
  exhaustion_wound: true,
} as const satisfies Record<MechanicPayload['type'], true>;
