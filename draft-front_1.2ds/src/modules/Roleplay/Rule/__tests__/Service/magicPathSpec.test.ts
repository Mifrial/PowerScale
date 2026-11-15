import { describe, expect, it } from 'vitest';
import { MagicPathSpecService } from '@/modules/Roleplay/Rule/Service/Spec/MagicPathSpecService';

const service = new MagicPathSpecService();

describe('MagicPathSpecService', () => {
  it('читает опубликованную спеку без type и сохраняет флаги пути', () => {
    const spec = service.resolve({
      check_code: 'check-spell-cast',
      cast_check_code: 'check-spell-cast',
      power_characteristic_code: 'magic-power',
      control_characteristic_code: null,
      includes_path_codes: [],
      no_control: true,
      recorded_casting: true,
    });

    expect(spec).toEqual({
      type: 'magic_path',
      check_code: 'check-spell-cast',
      cast_check_code: 'check-spell-cast',
      power_characteristic_code: 'magic-power',
      control_characteristic_code: null,
      study_cost: null,
      includes_path_codes: [],
      no_control: true,
      recorded_casting: true,
    });
  });
});
