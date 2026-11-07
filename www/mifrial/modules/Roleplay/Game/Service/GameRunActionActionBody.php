<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Контекст карточки действия и реакции.
 */
final class GameRunActionActionBody
{
    /**
     * Создаёт разбор карточки.
     *
     * @param GameRunActionFields $fields Общие проверки.
     *
     * @return void
     */
    public function __construct(private readonly GameRunActionFields $fields)
    {
    }

    /**
     * Контекст карточки действия.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(array $context): array
    {
        $this->fields->assertKeys($context, $this->keys($context), 'action context');
        $expected = $context['expectedSheetVersion'] ?? null;
        $followUp = $context['followUpTargetKey'] ?? null;
        $chosen = $context['chosenAmounts'] ?? [];
        $process = $this->fields->process($context);
        if (!is_int($expected)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action context');
        }

        if ($followUp !== null && !is_string($followUp)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action context');
        }

        if (!is_array($chosen)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action context');
        }

        return [
            'expectedSheetVersion' => $expected,
            'followUpTargetKey' => $followUp,
            'chosenAmounts' => $chosen,
            'processRuleCode' => $process['processRuleCode'],
            'processStepCode' => $process['processStepCode'],
        ];
    }

    /**
     * Контекст реакции. Общая часть проверяется здесь, поля эффекта — тем,
     * кто этот эффект исполняет: транспорт не знает конкретных карточек.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function reaction(array $context): array
    {
        $expected = $context['expectedSheetVersion'] ?? null;
        if (!is_int($expected)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: reaction context');
        }

        return $context;
    }

    /**
     * Ключи контекста карточки.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return list<string> Ключи.
     */
    private function keys(array $context): array
    {
        $allowed = ['expectedSheetVersion', 'followUpTargetKey'];
        foreach (['chosenAmounts', 'processRuleCode', 'processStepCode'] as $key) {
            if (array_key_exists($key, $context)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }
}
