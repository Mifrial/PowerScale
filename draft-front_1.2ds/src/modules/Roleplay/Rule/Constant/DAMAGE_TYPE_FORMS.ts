import type { DamageTypeForms } from '@/modules/Roleplay/Rule/Dto/DamageTypeForms';

/** Склонение типов урона (по коду правила) для надписей атак и сопротивлений. */
export const DAMAGE_TYPE_FORMS: Record<string, DamageTypeForms> = {
  slashing: { genitive: 'рубящего урона', dative: 'рубящему урону' },
  piercing: { genitive: 'колющего урона', dative: 'колющему урону' },
  blunt: { genitive: 'дробящего урона', dative: 'дробящему урону' },
  cutting: { genitive: 'режущего урона', dative: 'режущему урону' },
  fire: { genitive: 'огня', dative: 'огню' },
  cold: { genitive: 'холода', dative: 'холоду' },
  electricity: { genitive: 'электричества', dative: 'электричеству' },
  light: { genitive: 'света', dative: 'свету' },
  'magic-damage': { genitive: 'магического урона', dative: 'магическому урону' },
  'poison-1': { genitive: 'яда 1 типа', dative: 'яду 1 типа' },
  'poison-2': { genitive: 'яда 2 типа', dative: 'яду 2 типа' },
  'poison-3': { genitive: 'яда 3 типа', dative: 'яду 3 типа' },
  'spirit-1': { genitive: 'духовного яда 1 типа', dative: 'духовному яду 1 типа' },
};
