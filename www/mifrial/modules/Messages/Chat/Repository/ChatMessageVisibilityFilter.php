<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Repository;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Messages\Chat\Dto\MessageAudience;

/**
 * Filter ST видимости и keyset SSE; без IOpenedRecords.
 */
final class ChatMessageVisibilityFilter
{
    /**
     * AND условий плюс OR видимости.
     *
     * @param array<int, array<string|int, mixed>> $conditions Соседи AND.
     * @param int $viewerId Зритель.
     *
     * @return array<string|int, mixed> Filter ST.
     */
    public function withVisibility(array $conditions, int $viewerId): array
    {
        $filter = ['LOGIC' => 'AND'];
        foreach ($conditions as $condition) {
            $filter[] = $condition;
        }

        $filter[] = $this->visibilityFilter($viewerId);

        return $filter;
    }

    /**
     * OR видимости: все, автор, список.
     *
     * @param int $viewerId Зритель.
     *
     * @return array<string|int, mixed> Filter ST.
     */
    public function visibilityFilter(int $viewerId): array
    {
        return [
            'LOGIC' => 'OR',
            ['audience' => MessageAudience::ALL],
            ['user_id' => $viewerId],
            ['@audience_user_ids' => $viewerId],
        ];
    }

    /**
     * Keyset: IN чатов и окно updated_at, без видимости.
     *
     * @param array<int, int> $chatIds Чаты.
     * @param int $sinceUnix Нижняя unix-секунда.
     * @param int $afterId Курсор id.
     * @param DateTime $horizon Верх.
     *
     * @return array<string|int, mixed> Filter ST.
     */
    public function sinceFilter(array $chatIds, int $sinceUnix, int $afterId, DateTime $horizon): array
    {
        $since = DateTime::fromUnix($sinceUnix);
        $window = [
            'LOGIC' => 'AND',
            ['chat_id' => $chatIds],
            ['<=updated_at' => $horizon],
        ];
        if ($afterId < 1) {
            $window[] = ['>=updated_at' => $since];

            return $window;
        }

        $window[] = [
            'LOGIC' => 'OR',
            ['>updated_at' => $since],
            [
                'LOGIC' => 'AND',
                ['=updated_at' => $since],
                ['>id' => $afterId],
            ],
        ];

        return $window;
    }
}
