/** Пиксели подложки для распознавания: RGBA, строка за строкой. */
export interface MapRecognitionImage {
  width: number;
  height: number;
  data: Uint8ClampedArray;
}
