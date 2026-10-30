<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

/**
 * Ссылки состояния на проверку и проверки на состояние исхода.
 *
 * Публикация ревизии с битой ссылкой не проходит: связка живёт в спеке, а не
 * в догадках рантайма.
 */
final class RuleStateReferences
{
    /**
     * Проблемы ссылок состава ревизии.
     *
     * @param array<int, array{code: string, type: string, name: string, spec: array}> $rules Карточки состава.
     *
     * @return array<int, array{ruleCode: string, ruleName: string, messages: array<int, string>}> Проблемы.
     */
    public function problems(array $rules): array
    {
        $types = [];
        $specs = [];
        foreach ($rules as $rule) {
            $types[$rule['code']] = $rule['type'];
            $specs[$rule['code']] = $rule['spec'];
        }

        $problems = [];
        foreach ($rules as $rule) {
            $messages = $this->messages($rule, $types, $specs);
            if ($messages === []) {
                continue;
            }

            $problems[] = [
                'ruleCode' => $rule['code'],
                'ruleName' => $rule['name'],
                'messages' => $messages,
            ];
        }

        return $problems;
    }

    /**
     * Проблемы одной карточки.
     *
     * @param array{code: string, type: string, name: string, spec: array} $rule Карточка.
     * @param array<string, string> $types Типы по кодам.
     * @param array<string, array> $specs Спеки по кодам.
     *
     * @return array<int, string> Сообщения.
     */
    private function messages(array $rule, array $types, array $specs): array
    {
        if ($rule['type'] === 'state') {
            return $this->stateMessages($rule, $types, $specs);
        }

        if ($rule['type'] === 'check') {
            return $this->checkMessages($rule, $types);
        }

        return [];
    }

    /**
     * Состояние: проверка прироста и её сложность.
     *
     * @param array{code: string, type: string, name: string, spec: array} $rule Карточка.
     * @param array<string, string> $types Типы по кодам.
     * @param array<string, array> $specs Спеки по кодам.
     *
     * @return array<int, string> Сообщения.
     */
    private function stateMessages(array $rule, array $types, array $specs): array
    {
        $checkCode = $rule['spec']['increase_check_code'] ?? null;
        if (!is_string($checkCode) || $checkCode === '') {
            return [];
        }

        if (($types[$checkCode] ?? null) !== 'check') {
            return ['state.increase_check_code: проверка «' . $checkCode . '» не найдена'];
        }

        $messages = [];
        $difficulty = $specs[$checkCode]['difficulty_input'] ?? null;
        $kind = is_array($difficulty) ? ($difficulty['kind'] ?? null) : null;
        $stateCode = is_array($difficulty) ? ($difficulty['state_code'] ?? null) : null;
        if ($kind !== 'from_state' || $stateCode !== $rule['code']) {
            $messages[] = 'state.increase_check_code: проверка «' . $checkCode
                . '» должна брать сложность из состояния «' . $rule['code'] . '»';
        }

        if (($rule['spec']['value_type'] ?? null) !== 'number') {
            $messages[] = 'state.increase_check_code: состояние «' . $rule['code'] . '» должно быть числом';
        }

        if (($rule['spec']['aggregation'] ?? null) !== 'sum') {
            $messages[] = 'state.increase_check_code: состояние «' . $rule['code'] . '» должно суммироваться';
        }

        return $messages;
    }

    /**
     * Проверка: состояния исходов живые.
     *
     * @param array{code: string, type: string, name: string, spec: array} $rule Карточка.
     * @param array<string, string> $types Типы по кодам.
     *
     * @return array<int, string> Сообщения.
     */
    private function checkMessages(array $rule, array $types): array
    {
        $outcomes = $rule['spec']['outcomes'] ?? null;
        if (!is_array($outcomes)) {
            return [];
        }

        $messages = [];
        foreach ($outcomes as $outcome) {
            $code = is_array($outcome) ? ($outcome['state_rule_code'] ?? null) : null;
            if (!is_string($code) || $code === '') {
                continue;
            }

            if (($types[$code] ?? null) !== 'state') {
                $messages[] = 'check.outcomes: состояние «' . $code . '» не найдено';
            }
        }

        return $messages;
    }
}
