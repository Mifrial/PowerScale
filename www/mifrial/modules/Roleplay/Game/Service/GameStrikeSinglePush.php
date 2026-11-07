<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;

/**
 * Тексты толчка в чате одиночного удара.
 */
final class GameStrikeSinglePush
{
    /**
     * Создаёт тексты толчка.
     *
     * @param GameStrikeChat $chat Токены и подписи.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeChat $chat,
    ) {
    }

    /**
     * Строка исхода толчка.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $result Итог.
     * @param string $reaction Реакция.
     * @param list<string> $bits Урон и раны.
     *
     * @return string Текст.
     */
    public function resultText(array $header, array $open, array $result, string $reaction, array $bits): string
    {
        $push = $header['push'];
        if ($push === null) {
            return '';
        }

        if (($push['canceled'] ?? false) === true) {
            return $header['attacker'] . ' не толкает ' . $header['defender'] . '.';
        }

        $success = is_int($result['success'] ?? null) ? $result['success'] : 0;
        $text = $this->contact($header, $reaction, $this->implement($header['slice'], $open), $success) . '!';
        if ($bits !== []) {
            $text .= ' ' . $header['attacker'] . ' наносит ' . implode(' и ', $bits) . '.';
        }

        return $text . $this->unstableText($header, $push);
    }

    /**
     * Контакт толчка и отбрасывание.
     *
     * @param array<string, mixed> $header Подписи.
     * @param string $reaction Реакция.
     * @param string $implement Чем толкают.
     * @param int $success Множитель урона.
     *
     * @return string Текст.
     */
    private function contact(array $header, string $reaction, string $implement, int $success): string
    {
        $rating = $header['pushRating'] ?? 0;
        $text = $reaction === 'block' && $rating <= 0
            ? $header['attacker'] . ' толкает ' . $header['defender'] . ' ' . $implement
                . '; ' . $header['defender'] . ' блокирует — контакт ' . $success . ' РУ'
            : $header['attacker'] . ' толкает ' . $header['defender'] . ' ' . $implement . ' с ' . $rating . ' РУ';
        $knockback = $header['push']['knockback'] ?? null;
        if (
            is_array($knockback)
            && is_int($knockback['base'] ?? null)
            && is_int($knockback['size'] ?? null)
        ) {
            $text .= ' и отбрасывает на ' . $this->chat->getDimensionalText($knockback) . ' ипари';
        }

        return $text;
    }

    /**
     * Хвост неустойчивости.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $push Данные толчка.
     *
     * @return string Текст.
     */
    private function unstableText(array $header, array $push): string
    {
        $unstable = is_int($push['unstable'] ?? null) ? $push['unstable'] : 0;
        if ($unstable <= 0) {
            return '';
        }

        $code = is_string($push['unstableCode'] ?? null) ? $push['unstableCode'] : null;
        $name = $code === null ? null : ($header['slice']->findLive($code)?->getName() ?? $code);
        $shown = $name !== null && $name !== '' ? $name : 'состояние';

        return ' ' . $header['defender'] . ' получает ' . $shown . ' ' . $unstable . '.';
    }

    /**
     * Чем толкают: руками, щитом или оружием.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return string Подпись.
     */
    private function implement(CharacterRuleSlice $slice, array $open): string
    {
        $code = is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '';
        $rule = $code === '' ? null : $slice->findLive($code);
        $spec = $rule !== null && !$rule->isSpecBroken() ? $rule->getSpec() : null;
        if ($spec instanceof ItemSpec) {
            if ($spec->getShield() !== null) {
                return 'щитом «' . $rule->getName() . '»';
            }

            if ($spec->isInnate()) {
                return 'руками';
            }
        }

        $name = $rule?->getName() ?? '';

        return $name === '' ? 'руками' : 'оружием «' . $name . '»';
    }
}
