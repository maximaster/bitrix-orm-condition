<?php

declare(strict_types=1);

namespace Maximaster\BitrixOrmCondition;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Query\Query;
use Maximaster\BitrixEnums\Main\Orm\Condition\Operator;
use Maximaster\BitrixEnums\Main\Orm\OrderDirection;
use Maximaster\BitrixOrmCondition\Contract\BitrixOrmConditionException;

/**
 * Колонка условия.
 *
 * @psalm-immutable
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) why:intended
 * @SuppressWarnings(PHPMD.TooManyMethods) why:intended
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) why:intended
 */
class Column
{
    /**
     * Ссылка на колонку.
     *
     * @var string|ExpressionField имя, либо поле-выражение
     */
    private $reference;

    public static function of(string $name): self
    {
        return new self($name);
    }

    public static function expressedAs(ExpressionField $field): self
    {
        return new self($field);
    }

    /**
     * @param string|ExpressionField $reference
     */
    private function __construct($reference)
    {
        $this->reference = $reference;
    }

    /**
     * @throws ArgumentException
     */
    public function equals($value): ConditionTree
    {
        if ($value === null) {
            return $this->null()->toTree();
        }

        return ConditionTree::forAll([
            $this->condition(is_iterable($value) ? Operator::IN() : Operator::EQUAL(), $value),
            $this->notNull(),
        ]);
    }

    /**
     * @throws ArgumentException
     */
    public function notEquals($value): ConditionTree
    {
        if ($value === null) {
            return $this->notNull()->toTree();
        }

        return ConditionTree::forAny([
            $this->condition(Operator::NOT_EQUAL(), $value),
            $this->null(),
        ]);
    }

    /**
     * @throws ArgumentException
     */
    public function less($value): ConditionTree
    {
        return $value === null
            ? ConditionTree::false()
            : ConditionTree::forAny([
                $this->condition(Operator::LESS(), $value),
                $this->null(),
            ]);
    }

    /**
     * @throws ArgumentException
     */
    public function lessOrEqual($value): ConditionTree
    {
        return $value === null
            ? $this->null()->toTree()
            : ConditionTree::forAny([
                $this->condition(Operator::LESS_OR_EQUAL(), $value),
                $this->null(),
            ]);
    }

    /**
     * @throws ArgumentException
     */
    public function greater($value): ConditionTree
    {
        return $value === null
            ? $this->notNull()->toTree()
            : ConditionTree::forAll([
                $this->condition(Operator::GREATER(), $value),
                $this->notNull(),
            ]);
    }

    /**
     * @throws ArgumentException
     */
    public function greaterOrEqual($value): ConditionTree
    {
        return $value === null
            ? ConditionTree::true()
            : ConditionTree::forAll([
                $this->condition(Operator::GREATER_OR_EQUAL(), $value),
                $this->notNull(),
            ]);
    }

    public function directedBy(OrderDirection $direction, $value): ConditionTree
    {
        switch ($direction->getValue()) {
            case OrderDirection::ASC:
                return $this->greater($value);
            case OrderDirection::DESC:
                return $this->less($value);
            default:
                throw new BitrixOrmConditionException(
                    sprintf(
                        'Направление сортировки %s не поддерживается',
                        $direction->getValue()
                    )
                );
        }
    }

    public function directedOrEqualBy(OrderDirection $direction, $value): ConditionTree
    {
        switch ($direction->getValue()) {
            case OrderDirection::ASC:
                return $this->greaterOrEqual($value);
            case OrderDirection::DESC:
                return $this->lessOrEqual($value);
            default:
                throw new BitrixOrmConditionException(
                    sprintf(
                        'Направление сортировки %s не поддерживается',
                        $direction->getValue()
                    )
                );
        }
    }

    public function anyOf(array $values): Condition
    {
        return $this->condition(Operator::IN(), $values);
    }

    public function noneOf(array $values): ConditionTree
    {
        return ConditionTree::forAll(
            array_map(
                fn ($value) => $this->notEquals($value),
                $values
            )
        );
    }

    public function null(): Condition
    {
        return $this->condition(Operator::EQUAL(), null);
    }

    public function notNull(): Condition
    {
        return $this->condition(Operator::NOT_EQUAL(), null);
    }

    /**
     * @throws ArgumentException
     */
    public function foundIn(Query $query): ConditionTree
    {
        return ConditionTree::forAll([
            $this->condition(Operator::IN(), $query),
            $this->notNull(),
        ]);
    }

    /**
     * @throws ArgumentException
     */
    public function notFoundIn(Query $query): ConditionTree
    {
        return ConditionTree::forAny([
            $this->condition(Operator::IN(), $query)->toTree()->negative(),
            $this->null(),
        ]);
    }

    /**
     * @param int|float $from
     * @param int|float $to
     *
     * @throws BitrixOrmConditionException
     */
    public function betweenNumbers($from, $to): Condition
    {
        if (
            (is_int($from) === false && is_float($from) === false)
            || (is_int($to) === false && is_float($to) === false)
        ) {
            throw new BitrixOrmConditionException(
                'Переданы аргументы с невалидным типом, разрешено использовать только числовые типы (int, float).'
            );
        }

        return $this->condition(Operator::BETWEEN(), [$from, $to]);
    }

    public function like(string $pattern): Condition
    {
        return $this->condition(Operator::LIKE(), $pattern);
    }

    /**
     * @throws ArgumentException
     */
    public function contains(string $text): ConditionTree
    {
        return ConditionTree::forAll([
            $this->condition(Operator::LIKE(), '%' . $text . '%'),
            $this->notNull(),
        ]);
    }

    /**
     * @throws ArgumentException
     */
    public function notContains(string $text): ConditionTree
    {
        return ConditionTree::forAny([
            $this->condition(Operator::LIKE(), '%' . $text . '%')->toTree()->negative(),
            $this->null(),
        ]);
    }

    public function startsWith(string $text): Condition
    {
        return $this->condition(Operator::LIKE(), $text . '%');
    }

    public function endsWith(string $text): Condition
    {
        return $this->condition(Operator::LIKE(), '%' . $text);
    }

    public function exists(Query $query): Condition
    {
        return $this->condition(Operator::EXISTS(), $query);
    }

    public function match(string $pattern): Condition
    {
        return $this->condition(Operator::MATCH(), $pattern);
    }

    private function condition(Operator $operator, $value): Condition
    {
        return new Condition($this->reference, $operator, $value);
    }

    /**
     * Содержит любую из переданных фраз.
     *
     * @param string[] $values
     *
     * @throws ArgumentException
     *
     * @psalm-param list<string> $values
     */
    public function containsAny(array $values): ConditionTree
    {
        return ConditionTree::forAny(
            array_map(
                fn ($value) => $this->contains($value),
                $values
            )
        );
    }

    /**
     * Не содержит ни одну из указанных фраз.
     *
     * @param string[] $values
     *
     * @throws ArgumentException
     *
     * @psalm-param list<string> $values
     */
    public function containsNone(array $values): ConditionTree
    {
        return ConditionTree::forAll(
            array_map(
                fn ($value) => $this->notContains($value),
                $values
            )
        );
    }

    /**
     * Все значения, исключая переданные.
     *
     * @param string[] $values
     *
     * @psalm-param list<string|null> $values
     */
    public function anyExcept(array $values): ConditionTree
    {
        return $this->noneOf($values)->negative();
    }
}
