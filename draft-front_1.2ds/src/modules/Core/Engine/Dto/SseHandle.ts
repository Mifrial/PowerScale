/** Хендл живого GET-потока: close обрывает fetch без onError. */
export interface SseHandle {
  close: () => void;
}
