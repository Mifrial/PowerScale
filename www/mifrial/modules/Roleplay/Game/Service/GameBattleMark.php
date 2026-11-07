<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Нормализованная метка боя: субъект, дельта и область защиты.
 */
final class GameBattleMark
{
    private const KIND_ADVANTAGE = 'advantage';

    private const CONSUMES_FIRST_DEFENSE = 'first_defense';

    private const EXPIRES_TURN = 'turn';

    /**
     * Создаёт метку.
     *
     * @param array{type: string, id: int} $subject Субъект метки.
     * @param int $value Знаковая дельта.
     * @param string $sourceCode Код источника.
     * @param string|null $sourceRuleCode Карточка источника.
     * @param array{type: string, id: int} $defenseAgainst Против кого защита.
     * @param array{type: string, id: int} $expiresOf Чей ход снимает метку.
     *
     * @return void
     */
    private function __construct(
        private readonly array $subject,
        private readonly int $value,
        private readonly string $sourceCode,
        private readonly ?string $sourceRuleCode,
        private readonly array $defenseAgainst,
        private readonly array $expiresOf,
    ) {
    }

    /**
     * Приводит метку к канонической форме.
     *
     * @param array<string, mixed> $mark Метка.
     *
     * @return self Метка.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    public static function fromArray(array $mark): self
    {
        self::assertShape($mark);
        self::assertKind($mark);
        $value = self::value($mark);
        $sourceCode = self::sourceCode($mark);
        $sourceRuleCode = self::sourceRuleCode($mark);
        $scope = self::scope($mark);
        $expires = self::expires($mark);
        self::assertConsumes($mark);

        return new self(
            self::actor($mark['subject'] ?? null),
            $value,
            $sourceCode,
            $sourceRuleCode,
            self::actor($scope['defense_against']),
            self::actor($expires['of'] ?? null),
        );
    }

    /**
     * Каноническая форма для строки боя.
     *
     * @return array<string, mixed> Метка.
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'kind' => self::KIND_ADVANTAGE,
            'value' => $this->value,
            'source_code' => $this->sourceCode,
            'source_rule_code' => $this->sourceRuleCode,
            'scope' => ['defense_against' => $this->defenseAgainst],
            'consumes' => self::CONSUMES_FIRST_DEFENSE,
            'expires' => ['kind' => self::EXPIRES_TURN, 'of' => $this->expiresOf],
        ];
    }

    /**
     * Ключ наложения: субъект, вид и область.
     *
     * @return string Ключ.
     */
    public function key(): string
    {
        return $this->subjectKey() . '|' . self::KIND_ADVANTAGE . '|' . $this->defenseKey();
    }

    /**
     * Ключ субъекта метки.
     *
     * @return string Ключ.
     */
    public function subjectKey(): string
    {
        return self::actorKey($this->subject);
    }

    /**
     * Ключ защищающегося.
     *
     * @return string Ключ.
     */
    public function defenseKey(): string
    {
        return self::actorKey($this->defenseAgainst);
    }

    /**
     * Ключ актора, чей ход снимает метку.
     *
     * @return string Ключ.
     */
    public function expiresKey(): string
    {
        return self::actorKey($this->expiresOf);
    }

    /**
     * Преимущество из метки.
     *
     * @return RollAdvantage Преимущество.
     */
    public function advantage(): RollAdvantage
    {
        return new RollAdvantage(
            $this->sourceCode,
            $this->value,
            AdvantageSourceLabels::getLabel($this->sourceCode),
        );
    }

    /**
     * Только известные ключи метки.
     *
     * @param array<string, mixed> $mark Метка.
     *
     * @return void
     *
     * @throws GameInvalidException Если ключ чужой.
     */
    private static function assertShape(array $mark): void
    {
        $keys = array_keys($mark);
        sort($keys);
        $expected = ['subject', 'kind', 'value', 'source_code', 'source_rule_code', 'scope', 'consumes', 'expires'];
        sort($expected);
        if ($keys !== $expected) {
            throw new GameInvalidException('Game battle mark is invalid');
        }
    }

    /**
     * Вид метки.
     *
     * @param array<string, mixed> $mark Метка.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид чужой.
     */
    private static function assertKind(array $mark): void
    {
        if (($mark['kind'] ?? null) !== self::KIND_ADVANTAGE) {
            throw new GameInvalidException('Game battle mark kind is invalid');
        }
    }

    /**
     * Знаковая дельта метки.
     *
     * @param array<string, mixed> $mark Метка.
     *
     * @return int Дельта.
     *
     * @throws GameInvalidException Если значение чужое.
     */
    private static function value(array $mark): int
    {
        $value = $mark['value'] ?? null;
        if (!is_int($value) || $value === 0) {
            throw new GameInvalidException('Game battle mark value is invalid');
        }

        return $value;
    }

    /**
     * Источник метки.
     *
     * @param array<string, mixed> $mark Метка.
     *
     * @return string Код источника.
     *
     * @throws GameInvalidException Если источник чужой.
     */
    private static function sourceCode(array $mark): string
    {
        $sourceCode = $mark['source_code'] ?? null;
        if (!is_string($sourceCode) || $sourceCode === '') {
            throw new GameInvalidException('Game battle mark source is invalid');
        }

        return $sourceCode;
    }

    /**
     * Карточка источника или null.
     *
     * @param array<string, mixed> $mark Метка.
     *
     * @return string|null Код или null.
     *
     * @throws GameInvalidException Если карточка чужая.
     */
    private static function sourceRuleCode(array $mark): ?string
    {
        $sourceRuleCode = $mark['source_rule_code'] ?? null;
        if ($sourceRuleCode === null) {
            return null;
        }

        if (!is_string($sourceRuleCode) || $sourceRuleCode === '') {
            throw new GameInvalidException('Game battle mark source rule is invalid');
        }

        return $sourceRuleCode;
    }

    /**
     * Проверенная область действия.
     *
     * @param array<string, mixed> $mark Метка.
     *
     * @return array<string, mixed> Область.
     *
     * @throws GameInvalidException Если область чужая.
     */
    private static function scope(array $mark): array
    {
        $scope = $mark['scope'] ?? null;
        if (!is_array($scope) || !is_array($scope['defense_against'] ?? null)) {
            throw new GameInvalidException('Game battle mark scope is invalid');
        }

        $scopeKeys = array_keys($scope);
        sort($scopeKeys);
        if ($scopeKeys !== ['defense_against']) {
            throw new GameInvalidException('Game battle mark scope is invalid');
        }

        return $scope;
    }

    /**
     * Проверенный срок жизни.
     *
     * @param array<string, mixed> $mark Метка.
     *
     * @return array<string, mixed> Срок.
     *
     * @throws GameInvalidException Если срок чужой.
     */
    private static function expires(array $mark): array
    {
        $expires = $mark['expires'] ?? null;
        if (!is_array($expires)) {
            throw new GameInvalidException('Game battle mark expiry is invalid');
        }

        $expireKeys = array_keys($expires);
        sort($expireKeys);
        if ($expireKeys !== ['kind', 'of'] || ($expires['kind'] ?? null) !== self::EXPIRES_TURN) {
            throw new GameInvalidException('Game battle mark expiry is invalid');
        }

        return $expires;
    }

    /**
     * Расход метки.
     *
     * @param array<string, mixed> $mark Метка.
     *
     * @return void
     *
     * @throws GameInvalidException Если расход чужой.
     */
    private static function assertConsumes(array $mark): void
    {
        if (($mark['consumes'] ?? null) !== self::CONSUMES_FIRST_DEFENSE) {
            throw new GameInvalidException('Game battle mark consumption is invalid');
        }
    }

    /**
     * Пара актора.
     *
     * @param mixed $raw Значение.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private static function actor(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new GameInvalidException('Game battle mark actor is invalid');
        }

        $type = $raw['type'] ?? null;
        if (!is_string($type) || !in_array($type, ['character', 'npc'], true)) {
            throw new GameInvalidException('Game battle mark actor is invalid');
        }

        $id = $raw['id'] ?? null;
        if (!is_int($id) || $id < 1) {
            throw new GameInvalidException('Game battle mark actor is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Ключ актора.
     *
     * @param array{type: string, id: int} $actor Пара.
     *
     * @return string Ключ.
     */
    private static function actorKey(array $actor): string
    {
        return $actor['type'] . ':' . $actor['id'];
    }
}
