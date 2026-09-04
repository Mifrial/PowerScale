<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Dto;

use Mifrial\Core\Auth\Exception\AuthPolicyException;

/**
 * Срез правил пароля для JSON и проверки.
 */
final class PasswordPolicy
{
    private const SPECIAL_PATTERN = '/[!@#$%^&*()_+\-=[\]{};\':"\\\\|,.<>\/?]/';

    /**
     * Собирает срез.
     *
     * @param int $minLength Минимум символов.
     * @param bool $requireMixedCase Нужны строчная и заглавная латиница.
     * @param bool $requireDigit Нужна цифра.
     * @param bool $requireSpecialChar Нужен спецсимвол.
     *
     * @return void
     */
    public function __construct(
        private readonly int $minLength,
        private readonly bool $requireMixedCase,
        private readonly bool $requireDigit,
        private readonly bool $requireSpecialChar,
    ) {
    }

    /**
     * Политика по умолчанию, как в Auth 1.
     *
     * @return self Политика.
     */
    public static function defaults(): self
    {
        return new self(4, false, false, false);
    }

    /**
     * Строка ST.
     *
     * @param array<string, mixed> $policyRow Карта колонок.
     *
     * @return self Политика.
     */
    public static function fromRow(array $policyRow): self
    {
        return new self(
            (int) $policyRow['min_length'],
            (bool) $policyRow['require_mixed_case'],
            (bool) $policyRow['require_digit'],
            (bool) $policyRow['require_special_char'],
        );
    }

    /**
     * Наибольшая из набора; пустой набор → defaults.
     *
     * @param array<int, self> $policies Политики групп.
     *
     * @return self Срез.
     */
    public static function strictest(array $policies): self
    {
        if ($policies === []) {
            return self::defaults();
        }

        $minLength = 0;
        $requireMixedCase = false;
        $requireDigit = false;
        $requireSpecialChar = false;
        foreach ($policies as $policy) {
            if ($policy->minLength > $minLength) {
                $minLength = $policy->minLength;
            }

            $requireMixedCase = $requireMixedCase || $policy->requireMixedCase;
            $requireDigit = $requireDigit || $policy->requireDigit;
            $requireSpecialChar = $requireSpecialChar || $policy->requireSpecialChar;
        }

        return new self($minLength, $requireMixedCase, $requireDigit, $requireSpecialChar);
    }

    /**
     * Словарь JSON для action.
     *
     * @return array{minLength: int, requireMixedCase: bool, requireDigit: bool, requireSpecialChar: bool}
     */
    public function toJson(): array
    {
        return [
            'minLength' => $this->minLength,
            'requireMixedCase' => $this->requireMixedCase,
            'requireDigit' => $this->requireDigit,
            'requireSpecialChar' => $this->requireSpecialChar,
        ];
    }

    /**
     * Отклоняет слабый пароль.
     *
     * @param string $password Пароль.
     *
     * @return void
     *
     * @throws AuthPolicyException Если правило не выполнено.
     */
    public function assertPassword(string $password): void
    {
        if (strlen($password) < $this->minLength) {
            throw new AuthPolicyException();
        }

        $this->assertMixedCase($password);
        $this->assertDigit($password);
        $this->assertSpecialChar($password);
    }

    /**
     * Проверяет латиницу обоих регистров.
     *
     * @param string $password Пароль.
     *
     * @return void
     *
     * @throws AuthPolicyException Если нет строчной или заглавной.
     */
    private function assertMixedCase(string $password): void
    {
        if (!$this->requireMixedCase) {
            return;
        }

        $hasLower = preg_match('/[a-z]/', $password) === 1;
        $hasUpper = preg_match('/[A-Z]/', $password) === 1;
        if (!$hasLower || !$hasUpper) {
            throw new AuthPolicyException();
        }
    }

    /**
     * Проверяет цифру.
     *
     * @param string $password Пароль.
     *
     * @return void
     *
     * @throws AuthPolicyException Если цифры нет.
     */
    private function assertDigit(string $password): void
    {
        if ($this->requireDigit && preg_match('/\d/', $password) !== 1) {
            throw new AuthPolicyException();
        }
    }

    /**
     * Проверяет спецсимвол.
     *
     * @param string $password Пароль.
     *
     * @return void
     *
     * @throws AuthPolicyException Если спецсимвола нет.
     */
    private function assertSpecialChar(string $password): void
    {
        if ($this->requireSpecialChar && preg_match(self::SPECIAL_PATTERN, $password) !== 1) {
            throw new AuthPolicyException();
        }
    }
}
