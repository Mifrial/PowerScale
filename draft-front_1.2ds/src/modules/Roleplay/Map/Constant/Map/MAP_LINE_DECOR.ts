import type { MapLineFlow } from '@/modules/Roleplay/Map/Dto/MapLineDecor';

/** Ширина перехода между цветами по умолчанию, px подложки. */
export const MAP_LINE_DEFAULT_COLOR_BLEND = 16;

/** Стартовые значения спокойного течения. */
export const MAP_LINE_FLOW_DEFAULTS: MapLineFlow = {
  enabled: true,
  speed: 30,
  density: 45,
  opacity: 16,
  color: '#ffffff',
  direction: 1,
  seed: 7,
};

/** Течение выключено. */
export const MAP_LINE_FLOW_OFF: MapLineFlow = {
  ...MAP_LINE_FLOW_DEFAULTS,
  enabled: false,
};
