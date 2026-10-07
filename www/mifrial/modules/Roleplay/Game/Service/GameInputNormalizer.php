<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Trim, enum и потолки строки игры.
 */
final class GameInputNormalizer
{
    /**
     * @var array<int, string>
     */
    private const STATUSES = ['draft', 'recruiting', 'in_process', 'paused', 'completed'];

    /**
     * @var array<int, string>
     */
    private const VISIBILITIES = ['all', 'friends', 'players', 'invited', 'whitelist'];

    /**
     * @var array<int, string>
     */
    private const JOIN_POLICIES = ['anyone', 'friends', 'invite_only', 'whitelist'];

    /**
     * @var array<int, string>
     */
    private const MEMBER_ROLES = ['gm', 'player'];

    /**
     * Имя после trim.
     *
     * @param string $name Сырое имя.
     *
     * @return string Имя.
     *
     * @throws GameInvalidException Если пусто.
     */
    public function name(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new GameInvalidException('Game name is empty');
        }

        return $trimmed;
    }

    /**
     * Текст. null и пробелы становятся пустой строкой.
     *
     * @param string|null $text Сырой текст.
     *
     * @return string Текст.
     */
    public function text(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        return trim($text);
    }

    /**
     * Статус кампании.
     *
     * @param string $status Код.
     *
     * @return string Код.
     *
     * @throws GameInvalidException Если код не из пяти.
     */
    public function status(string $status): string
    {
        return $this->token($status, self::STATUSES);
    }

    /**
     * Видимость. Не фильтр выборки.
     *
     * @param string $visibility Код.
     *
     * @return string Код.
     *
     * @throws GameInvalidException Если код неизвестен.
     */
    public function visibility(string $visibility): string
    {
        return $this->token($visibility, self::VISIBILITIES);
    }

    /**
     * Политика входа. Не фильтр выборки.
     *
     * @param string $joinPolicy Код.
     *
     * @return string Код.
     *
     * @throws GameInvalidException Если код неизвестен.
     */
    public function joinPolicy(string $joinPolicy): string
    {
        return $this->token($joinPolicy, self::JOIN_POLICIES);
    }

    /**
     * Потолок. null остаётся null.
     *
     * @param int|null $limit Число или нет потолка.
     *
     * @return int|null Потолок.
     *
     * @throws GameInvalidException Если меньше 0.
     */
    public function limit(?int $limit): ?int
    {
        if ($limit === null) {
            return null;
        }

        if ($limit < 0) {
            throw new GameInvalidException('Game limit is invalid');
        }

        return $limit;
    }

    /**
     * Роль участника. owner сюда не входит.
     *
     * @param string $role Код.
     *
     * @return string Код.
     *
     * @throws GameInvalidException Если код не gm и не player.
     */
    public function memberRole(string $role): string
    {
        return $this->token($role, self::MEMBER_ROLES);
    }

    /**
     * Номер ревизии.
     *
     * @param int $rulesRevision Номер.
     *
     * @return int Номер.
     *
     * @throws GameInvalidException Если меньше 1.
     */
    public function rulesRevision(int $rulesRevision): int
    {
        if ($rulesRevision < 1) {
            throw new GameInvalidException('Game revision is invalid');
        }

        return $rulesRevision;
    }

    /**
     * Код из списка.
     *
     * @param string $token Код.
     * @param array<int, string> $allowed Допустимые.
     *
     * @return string Код.
     *
     * @throws GameInvalidException Если нет в списке.
     */
    private function token(string $token, array $allowed): string
    {
        if (!in_array($token, $allowed, true)) {
            throw new GameInvalidException('Game field is invalid');
        }

        return $token;
    }
}
