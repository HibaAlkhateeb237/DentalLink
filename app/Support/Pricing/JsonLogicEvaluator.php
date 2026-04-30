<?php

namespace App\Support\Pricing;

use InvalidArgumentException;

class JsonLogicEvaluator
{
    public function evaluate(mixed $expression, array $data): bool
    {
        if ($expression === null) {
            return true;
        }

        if (is_bool($expression)) {
            return $expression;
        }

        if (! is_array($expression)) {
            return (bool) $expression;
        }

        if (array_key_exists('and', $expression)) {
            $items = $expression['and'];
            if (! is_array($items)) {
                throw new InvalidArgumentException('Invalid and expression');
            }

            foreach ($items as $item) {
                if (! $this->evaluate($item, $data)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('or', $expression)) {
            $items = $expression['or'];
            if (! is_array($items)) {
                throw new InvalidArgumentException('Invalid or expression');
            }

            foreach ($items as $item) {
                if ($this->evaluate($item, $data)) {
                    return true;
                }
            }

            return false;
        }

        if (array_key_exists('not', $expression)) {
            return ! $this->evaluate($expression['not'], $data);
        }

        foreach (['==', '!=', '>', '>=', '<', '<='] as $operator) {
            if (array_key_exists($operator, $expression)) {
                $operands = $expression[$operator];
                if (! is_array($operands) || count($operands) !== 2) {
                    throw new InvalidArgumentException('Invalid comparison operands');
                }

                [$left, $right] = $operands;

                $leftValue = $this->resolveOperand($left, $data);
                $rightValue = $this->resolveOperand($right, $data);

                return $this->compare($operator, $leftValue, $rightValue);
            }
        }

        throw new InvalidArgumentException('Unsupported expression');
    }

    private function resolveOperand(mixed $operand, array $data): mixed
    {
        if (is_array($operand) && array_key_exists('var', $operand)) {
            $path = $operand['var'];
            if (! is_string($path)) {
                return null;
            }

            return data_get($data, $path);
        }

        return $operand;
    }

    private function compare(string $operator, mixed $left, mixed $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            $left = (float) $left;
            $right = (float) $right;
        }

        return match ($operator) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            default => throw new InvalidArgumentException('Unsupported operator'),
        };
    }
}
