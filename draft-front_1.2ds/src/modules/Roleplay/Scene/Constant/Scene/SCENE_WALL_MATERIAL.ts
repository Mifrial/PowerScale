/**
 * Материалы стены-ленты: тон полосы, ребро, фаска и швы.
 *
 * Стена видна сверху, поэтому материал — это верхняя грань: база, тёмное ребро
 * по краю и светлая фаска по осевой. Швы задают материал: у досок поперечные
 * стыки, у кирпича частая перевязка, у штукатурки швов нет.
 */
export const SCENE_WALL_MATERIAL = {
  /** Материал новой стены и стен без материала в старых сценах. */
  default: 'plaster',

  /** Список материала для карточки стены. */
  options: [
    { value: 'plaster', title: 'Штукатурка' },
    { value: 'wood', title: 'Доски' },
    { value: 'log', title: 'Бревно' },
    { value: 'brick', title: 'Кирпич' },
    { value: 'stone', title: 'Камень' },
  ],

  /** Палитра по материалу: база, ребро, фаска, шов и шаг швов. */
  materials: {
    plaster: { base: 0xd8cebb, edge: 0x8b7e6b, top: 0xf0e8d8, seam: 0xb3a691, seamStepIpari: 0 },
    wood: { base: 0x9a6b45, edge: 0x4c3221, top: 0xc08c60, seam: 0x6a4529, seamStepIpari: 0.7 },
    log: { base: 0x7b5232, edge: 0x3c2717, top: 0xa8774c, seam: 0x543722, seamStepIpari: 1.2 },
    brick: { base: 0xa0563f, edge: 0x57301f, top: 0xc0705a, seam: 0x7d3f2d, seamStepIpari: 0.35 },
    stone: { base: 0x8e8a80, edge: 0x4a4741, top: 0xb0aba0, seam: 0x6e6a61, seamStepIpari: 0.9 },
  },

  /**
   * Тень стены на полу.
   *
   * Три сдвинутые копии полосы с падающей прозрачностью дают мягкий край без
   * размытия: сплошная тень читалась бы второй стеной. Направление общее для
   * сцены, а не для стены, иначе у каждой стены был бы свой свет.
   */
  shadow: {
    direction: [0.65, 0.75] as [number, number],
    layers: [
      { shift: 0.12, alpha: 0.1 },
      { shift: 0.26, alpha: 0.07 },
      { shift: 0.44, alpha: 0.05 },
    ],
  },

  /** Доля толщины, которую занимает тёмное ребро. */
  edgeShare: 0.24,

  /** Ширина светлой фаски по осевой, доля толщины. */
  topShare: 0.28,
} as const;
