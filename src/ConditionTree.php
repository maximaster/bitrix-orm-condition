<?php

declare(strict_types=1);

namespace Maximaster\BitrixOrmCondition;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ORM\Query\Filter\Condition as BitrixCondition;
use Bitrix\Main\ORM\Query\Filter\ConditionTree as BitrixConditionTree;
use Maximaster\BitrixEnums\Main\Orm\Condition\Operator;
use Maximaster\BitrixEnums\Main\Orm\ConditionTree\Logic;
use Maximaster\BitrixOrmCondition\Contract\BitrixOrmConditionException;

/**
 * Дерево условий.
 */
class ConditionTree extends BitrixConditionTree
{
    /**
     * Создать экземпляр из объекта дерева условий Битрикс.
     *
     * @throws ArgumentException
     */
    public static function fromBitrix(BitrixConditionTree $tree): self
    {
        return new self($tree->conditions, new Logic($tree->logic));
    }

    /**
     * Создаёт дерево условий которое всегда выполняется успешно.
     */
    public static function true(): self
    {
        return Condition::true()->toTree();
    }

    /**
     * Создаёт дерево условий, которое всегда ложно.
     *
     * @throws ArgumentException
     */
    public static function false(): self
    {
        return Condition::false()->toTree();
    }

    /**
     * @return Condition|ConditionTree
     *
     * @throws ArgumentException
     * @throws BitrixOrmConditionException
     */
    private static function buildConditionOrTree(string $column, string $operator, $value)
    {
        // Особый случай, т.к. под это нет своего оператора
        if (str_starts_with($operator, '!%') || str_starts_with($operator, '!@')) {
            $tree = new self();
            $tree->where(new Condition($column, self::buildOperator(substr($operator, 1), $value), $value));

            return $tree->negative();
        }

        return new Condition($column, self::buildOperator($operator, $value), $value);
    }

    /**
     * @throws BitrixOrmConditionException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) TODO заменить switch на карту?
     */
    private static function buildOperator(string $operator, $value): Operator
    {
        switch ($operator) {
            case '':
                return is_iterable($value) ? Operator::IN() : Operator::EQUAL();
            case '=':
                return Operator::EQUAL();
            case '!':
            case '!=':
                return Operator::NOT_EQUAL();
            case '<':
                return Operator::LESS();
            case '<=':
                return Operator::LESS_OR_EQUAL();
            case '>':
                return Operator::GREATER();
            case '>=':
                return Operator::GREATER_OR_EQUAL();
            case '@':
                return Operator::IN();
            case '><':
                return Operator::BETWEEN();
            case '%':
                return Operator::LIKE();
        }

        throw new BitrixOrmConditionException(sprintf('Не удалось определить оператор для %s', $operator));
    }

    /**
     * @param BitrixConditionTree[]|BitrixCondition[] $conditions
     *
     * @throws ArgumentException
     */
    public function __construct(array $conditions = [], ?Logic $logic = null)
    {
        parent::__construct();

        foreach ($conditions as $condition) {
            $isExpectedType = $condition instanceof BitrixConditionTree || $condition instanceof BitrixCondition;
            if ($isExpectedType === false) {
                throw new BitrixOrmConditionException(
                    sprintf(
                        'Ожидалось, что элемент $condition будет типа %s или %s, получено: %s.',
                        BitrixConditionTree::class,
                        BitrixCondition::class,
                        is_object($condition) ? get_class($condition) : gettype($condition)
                    )
                );
            }
        }

        if ($logic !== null) {
            $this->logic($logic->getValue());
        }

        foreach ($conditions as $condition) {
            $this->where($condition);
        }
    }

    /**
     * Возвращает дерево условий, где требуется соблюдение хотя бы одного условия.
     *
     * @params BitrixConditionTree[]|BitrixCondition[] $conditions
     *
     * @throws ArgumentException
     */
    public static function forAny(array $conditions): self
    {
        return new self($conditions, Logic::OR());
    }

    /**
     * Возвращает дерево условий, где требуется соблюдение всех условий.
     *
     * @params BitrixConditionTree[]|BitrixCondition[] $conditions
     *
     * @throws ArgumentException
     */
    public static function forAll(array $conditions): self
    {
        return new self($conditions, Logic::AND());
    }

    /**
     * Добавляет условие.
     * TODO уточнить типизацию через psalm-param.
     *
     * @throws ArgumentException
     */
    public function where(...$filter): self
    {
        if (
            count($filter) === 1
            && is_array($filter[0])
            && ($filter[0][0] instanceof BitrixCondition || $filter[0][0] instanceof BitrixConditionTree)
        ) {
            // Битрикс некорректно работает когда передаётся 1 аргумент с
            // Condition[] либо ConditionTree[]
            // Поддерживается только Condition[][] либо ConditionTree[][]
            [$conditions] = $filter[0];

            foreach ($conditions as $condition) {
                parent::where($condition);
            }

            return $this;
        }

        parent::where(...$filter);

        return $this;
    }

    /**
     * Определяет, находится ли в дереве лишь одно простое условие.
     */
    public function hasSingleCondition(): bool
    {
        try {
            $this->toCondition();

            return true;
        } catch (BitrixOrmConditionException $e) {
            return false;
        }
    }

    /**
     * Преобразует дерево условий в одно условие.
     * TODO в режиме strict=false это уже не преобразование, а извлечение
     *      первого условия, возможно стоит выделить в отдельный метод,
     *      а параметр убрать.
     *
     * @param bool $strict падать ли, если в дереве более чем одно условие
     *
     * @throws BitrixOrmConditionException
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag) TODO
     */
    public function toCondition(bool $strict = true): Condition
    {
        return Condition::fromBitrix(self::treeToCondition($this, $strict));
    }

    /**
     * @throws BitrixOrmConditionException
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag) TODO
     */
    private static function treeToCondition(BitrixConditionTree $tree, bool $strict = true): BitrixCondition
    {
        switch (count($tree->conditions)) {
            case 0:
                throw new BitrixOrmConditionException('В дереве нет условий');
            case 1:
                $firstPart = reset($tree->conditions);
                break;
            default:
                if ($strict) {
                    throw new BitrixOrmConditionException(
                        'В дереве несколько условий, невозможно преобразовать в одно.'
                    );
                }
                $firstPart = reset($tree->conditions);
        }

        if ($firstPart instanceof BitrixCondition) {
            return $firstPart;
        }

        if ($firstPart instanceof BitrixConditionTree) {
            return self::treeToCondition($firstPart);
        }

        throw new BitrixOrmConditionException(
            sprintf('Ожидалось условие или дерево, получено %s', get_debug_type($firstPart))
        );
    }
}
