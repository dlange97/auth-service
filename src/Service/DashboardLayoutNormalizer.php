<?php

declare(strict_types=1);

namespace App\Service;

final class DashboardLayoutNormalizer
{
    /**
     * @param mixed $payload
     * @return array<string, mixed>|null|false  null when payload is null, false on invalid input
     */
    public function normalize(mixed $payload): array|null|false
    {
        if ($payload === null) {
            return null;
        }

        if (!is_array($payload)) {
            return false;
        }

        $order = $payload['order'] ?? [];
        $scales = $payload['scales'] ?? [];

        if (!is_array($order) || !is_array($scales)) {
            return false;
        }

        $normalizedOrder = $this->normalizeOrder($order);
        if ($normalizedOrder === false) {
            return false;
        }

        $normalizedScales = $this->normalizeScales($scales);
        if ($normalizedScales === false) {
            return false;
        }

        return [
            'order'  => array_values(array_unique($normalizedOrder)),
            'scales' => $normalizedScales,
        ];
    }

    /** @return list<string>|false */
    private function normalizeOrder(array $order): array|false
    {
        $result = [];
        foreach ($order as $id) {
            if (!is_string($id)) {
                return false;
            }

            $trimmed = trim($id);
            if ($trimmed === '') {
                continue;
            }

            $result[] = $trimmed;
        }

        return $result;
    }

    /** @return array<string, array{x: float, y: float}>|false */
    private function normalizeScales(array $scales): array|false
    {
        $result = [];

        foreach ($scales as $tileId => $scale) {
            if (!is_string($tileId) || trim($tileId) === '') {
                return false;
            }

            if (is_numeric($scale)) {
                $value = (float) $scale;
                if ($value < 0.5 || $value > 1.5) {
                    return false;
                }

                $result[trim($tileId)] = [
                    'x' => round($value, 3),
                    'y' => round($value, 3),
                ];

                continue;
            }

            if (!is_array($scale)) {
                return false;
            }

            $x = $scale['x'] ?? null;
            $y = $scale['y'] ?? null;

            if (!is_numeric($x) || !is_numeric($y)) {
                return false;
            }

            $xValue = (float) $x;
            $yValue = (float) $y;
            if ($xValue < 0.5 || $xValue > 1.5 || $yValue < 0.5 || $yValue > 1.5) {
                return false;
            }

            $result[trim($tileId)] = [
                'x' => round($xValue, 3),
                'y' => round($yValue, 3),
            ];
        }

        return $result;
    }
}
