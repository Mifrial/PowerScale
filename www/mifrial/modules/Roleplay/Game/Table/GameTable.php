<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\LinkSetField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта строки игры `game`.
 */
final class GameTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return array_merge(
            $this->identityFields(),
            $this->textFields(),
            $this->limitFields(),
            $this->timestampFields(),
        );
    }

    /**
     * Владелец, мир и статус.
     *
     * @return array Поля.
     */
    private function identityFields(): array
    {
        return [
            new IdField(),
            ReferenceField::forTable(
                'owner_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                'user',
            ),
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new StringField('status', FieldSettings::fromOptions(['required' => true, 'indexed' => true]), 32),
            new StringField('visibility', FieldSettings::fromOptions(['required' => true]), 32),
            new StringField('join_policy', FieldSettings::fromOptions(['required' => true]), 32),
            LinkSetField::forTable(
                'whitelist',
                FieldSettings::fromOptions(['default' => []]),
                'user',
            ),
            ReferenceField::forTable(
                'space_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                'rule_space',
            ),
            new StringField('space_code', FieldSettings::fromOptions(['required' => true])),
            new IntField('rules_revision', FieldSettings::fromOptions(['required' => true]), 1, null),
        ];
    }

    /**
     * Описания.
     *
     * @return array Поля.
     */
    private function textFields(): array
    {
        return [
            new StringField('short_description', FieldSettings::fromOptions(['required' => true, 'default' => ''])),
            new TextField('description', FieldSettings::fromOptions(['required' => true, 'default' => ''])),
        ];
    }

    /**
     * Потолки. null — потолка нет.
     *
     * @return array Поля.
     */
    private function limitFields(): array
    {
        $optional = FieldSettings::fromOptions(['required' => false]);

        return [
            new IntField('os_points_limit', $optional, 0, null),
            new IntField('ol_points_limit', $optional, 0, null),
            new IntField('or_points_limit', $optional, 0, null),
            new IntField('money_limit', $optional, 0, null),
            new IntField('game_chat_id', $optional, 1, null),
            new IntField('discussion_chat_id', $optional, 1, null),
        ];
    }

    /**
     * Моменты записи.
     *
     * @return array Поля.
     */
    private function timestampFields(): array
    {
        $now = FieldSettings::fromOptions([
            'required' => true,
            'default' => DateTimeNow::instance(),
        ]);

        return [
            new DateTimeField('created_at', $now),
            new DateTimeField('updated_at', $now),
        ];
    }
}
