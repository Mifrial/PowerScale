<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта actual-строки персонажа `character`.
 */
final class CharacterTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'character';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return array_merge($this->identityFields(), $this->payloadFields(), $this->timestampFields());
    }

    /**
     * Ссылки, имя, флаги.
     *
     * @return array Поля.
     */
    private function identityFields(): array
    {
        return [
            new IdField(),
            ReferenceField::forTable(
                'owner_id',
                FieldSettings::fromOptions(['required' => true]),
                'user',
            ),
            ReferenceField::forTable(
                'space_id',
                FieldSettings::fromOptions(['required' => true]),
                'rule_space',
            ),
            new IntField(
                'rules_revision',
                FieldSettings::fromOptions(['required' => true]),
                1,
                null,
            ),
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new BoolField('active', FieldSettings::fromOptions(['required' => true, 'default' => true])),
            new IntField(
                'actual_version',
                FieldSettings::fromOptions(['required' => true, 'default' => 1]),
                1,
                null,
            ),
        ];
    }

    /**
     * JSON и заметки.
     *
     * @return array Поля.
     */
    private function payloadFields(): array
    {
        return [
            new JsonField('choices', FieldSettings::fromOptions(['required' => true, 'default' => []])),
            new JsonField('sheet', FieldSettings::fromOptions(['required' => true, 'default' => []])),
            new JsonField(
                'visibility_fields',
                FieldSettings::fromOptions(['required' => true, 'default' => []]),
            ),
            new BoolField(
                'is_public',
                FieldSettings::fromOptions(['required' => true, 'default' => false, 'indexed' => true]),
            ),
            new TextField('owner_notes', FieldSettings::fromOptions(['required' => true, 'default' => ''])),
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
