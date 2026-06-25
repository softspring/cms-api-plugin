<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Api;

use Symfony\Component\HttpFoundation\Request;

use function filter_var;
use function max;
use function min;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOL;

class RequestQuery
{
    public function bool(Request $request, string $name, bool $default = false): bool
    {
        $value = $request->query->get($name);
        if (null === $value || '' === $value) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function int(Request $request, string $name, int $default, int $min, int $max): int
    {
        $value = $request->query->getInt($name, $default);

        return min($max, max($min, $value));
    }

    public function nullableInt(Request $request, string $name): ?int
    {
        $value = $request->query->get($name);
        if (null === $value || '' === $value) {
            return null;
        }

        return (int) $value;
    }

    public function nullableString(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);
        if (null === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' !== $value ? $value : null;
    }

    public function string(Request $request, string $name, string $default = ''): string
    {
        $value = $request->query->get($name, $default);

        return trim((string) $value);
    }
}
