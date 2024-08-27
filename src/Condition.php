<?php

declare(strict_types=1);

namespace Maximaster\BitrixOrmCondition;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Query\Filter\Condition as BitrixCondition;
use Maximaster\BitrixEnums\Main\Orm\Condition\Operator;
use Maximaster\BitrixEnums\Main\Orm\ConditionTree\Logic;

/**
 * Единичное условие.
 */
class Condition extends BitrixCondition
{
    /**
     * Возвращает экземпляр из условия Битрикс.
     */
    public static function fromBitrix(BitrixCondition $source): self
    {
        return new self($source->column, new Operator($source->operator), $source->value);
    }

    /**
     * Создаёт условие которое всегда выполняется.
     */
    public static function true(): self
    {
        return new self(new ExpressionField('TRUE_' . bin2hex(random_bytes(10)), '1'), Operator::EQUAL(), 1);
    }

    /**
     * Создаёт условие, которое всегда ложно.
     */
    public static function false(): self
    {
        return new self(new ExpressionField('TRUE_' . bin2hex(random_bytes(10)), '1'), Operator::EQUAL(), 0);
    }

    public function __construct($column, Operator $operator, $value)
    {
        parent::__construct($column, $operator->getValue(), $value);
    }

    /**
     * Преобразует в дерево условий с данным условием в качестве вложенного и указанной логикой.
     *
     * @throws ArgumentException
     */
    public function toTree(?Logic $logic = null): ConditionTree
    {
        return new ConditionTree([$this], $logic);
    }

    /**
     * Создаёт дерево условий работающих через "ИЛИ" и состоящее из этого и указанного условий.
     *
     * @throws ArgumentException
     */
    public function or(self $otherCondition): ConditionTree
    {
        return new ConditionTree([$this, $otherCondition], Logic::OR());
    }

    /**
     * Создаёт дерево условий работающих через "И" и состоящее из этого и указанного условий.
     *
     * @throws ArgumentException
     */
    public function and(self $otherCondition): ConditionTree
    {
        return new ConditionTree([$this, $otherCondition], Logic::AND());
    }
}
