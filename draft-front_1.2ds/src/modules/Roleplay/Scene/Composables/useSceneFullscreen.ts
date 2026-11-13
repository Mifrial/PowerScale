import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';

/** Что разворачивается и как вписать карту в новый размер. */
export interface SceneFullscreenOptions {
  /** Элемент страницы для нативного fullscreen; null — только CSS-оверлей. */
  target: () => HTMLElement | null;
  /** Вписать карту в текущий размер контейнера. */
  fit: () => void;
}

/** Разворот просмотра на весь экран: нативный API плюс запасной оверлей. */
export interface SceneFullscreen {
  full: Ref<boolean>;
  toggle(): Promise<void>;
  open(): Promise<void>;
  close(): Promise<void>;
}

/**
 * Полноэкранный просмотр карты.
 *
 * Нативный fullscreen в части встроенных браузеров не завершается или не
 * поддерживается, поэтому состояние `full` сразу включает CSS-оверлей на весь
 * viewport. После входа, выхода и перестройки панелей карта вписывается в новый
 * размер.
 */
export function useSceneFullscreen(options: SceneFullscreenOptions): SceneFullscreen {
  const full = ref(false);

  async function open(): Promise<void> {
    full.value = true;
    await nextTick();
    refit();
    // Не ждём промис: оверлей на весь viewport уже работает.
    try {
      void options.target()?.requestFullscreen?.().catch(() => undefined);
    } catch {
      // Браузер запретил нативный fullscreen — остаётся CSS-оверлей.
    }
  }

  async function close(): Promise<void> {
    full.value = false;
    if (document.fullscreenElement !== null) {
      try {
        await document.exitFullscreen();
      } catch {
        // Уже вышли или браузер не дал — оверлей всё равно снимаем.
      }
    }
    refit();
  }

  async function toggle(): Promise<void> {
    if (full.value) {
      await close();

      return;
    }

    await open();
  }

  /** Вписывает карту: сразу и ещё дважды, пока идёт переход раскладки. */
  function refit(): void {
    const fit = (): void => options.fit();
    window.requestAnimationFrame(fit);
    window.setTimeout(fit, 120);
    window.setTimeout(fit, 400);
  }

  function onFullscreenChange(): void {
    if (document.fullscreenElement === null) {
      full.value = false;
    }
    refit();
  }

  onMounted(() => document.addEventListener('fullscreenchange', onFullscreenChange));
  onBeforeUnmount(() => document.removeEventListener('fullscreenchange', onFullscreenChange));

  return { full, toggle, open, close };
}
