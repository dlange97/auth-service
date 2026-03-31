<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DashboardLayoutNormalizer;
use PHPUnit\Framework\TestCase;

final class DashboardLayoutNormalizerTest extends TestCase
{
    private DashboardLayoutNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new DashboardLayoutNormalizer();
    }

    public function testNullPayloadReturnsNull(): void
    {
        $this->assertNull($this->normalizer->normalize(null));
    }

    public function testNonArrayPayloadReturnsFalse(): void
    {
        $this->assertFalse($this->normalizer->normalize('string'));
        $this->assertFalse($this->normalizer->normalize(42));
        $this->assertFalse($this->normalizer->normalize(true));
    }

    public function testNonArrayOrderReturnsFalse(): void
    {
        $this->assertFalse($this->normalizer->normalize(['order' => 'bad', 'scales' => []]));
    }

    public function testNonArrayScalesReturnsFalse(): void
    {
        $this->assertFalse($this->normalizer->normalize(['order' => [], 'scales' => 'bad']));
    }

    public function testEmptyPayloadReturnsEmptyArrays(): void
    {
        $result = $this->normalizer->normalize(['order' => [], 'scales' => []]);
        $this->assertSame(['order' => [], 'scales' => []], $result);
    }

    public function testOrderWithNonStringReturnsFalse(): void
    {
        $this->assertFalse($this->normalizer->normalize(['order' => [123], 'scales' => []]));
    }

    public function testOrderStripsEmptyStringsAndTrims(): void
    {
        $result = $this->normalizer->normalize([
            'order' => ['  tile-1 ', '', 'tile-2'],
            'scales' => [],
        ]);

        $this->assertSame(['tile-1', 'tile-2'], $result['order']);
    }

    public function testOrderDeduplicates(): void
    {
        $result = $this->normalizer->normalize([
            'order' => ['tile-1', 'tile-2', 'tile-1'],
            'scales' => [],
        ]);

        $this->assertSame(['tile-1', 'tile-2'], $result['order']);
    }

    public function testScaleWithNumericValueCreatesXY(): void
    {
        $result = $this->normalizer->normalize([
            'order' => [],
            'scales' => ['tile-1' => 1.0],
        ]);

        $this->assertSame(['x' => 1.0, 'y' => 1.0], $result['scales']['tile-1']);
    }

    public function testScaleBelowMinReturnsFalse(): void
    {
        $this->assertFalse($this->normalizer->normalize([
            'order' => [],
            'scales' => ['tile-1' => 0.4],
        ]));
    }

    public function testScaleAboveMaxReturnsFalse(): void
    {
        $this->assertFalse($this->normalizer->normalize([
            'order' => [],
            'scales' => ['tile-1' => 1.6],
        ]));
    }

    public function testScaleBoundaryValuesAreAccepted(): void
    {
        $result = $this->normalizer->normalize([
            'order' => [],
            'scales' => [
                'min' => 0.5,
                'max' => 1.5,
            ],
        ]);

        $this->assertSame(['x' => 0.5, 'y' => 0.5], $result['scales']['min']);
        $this->assertSame(['x' => 1.5, 'y' => 1.5], $result['scales']['max']);
    }

    public function testScaleWithXYArrayIsAccepted(): void
    {
        $result = $this->normalizer->normalize([
            'order' => [],
            'scales' => ['tile-1' => ['x' => 0.8, 'y' => 1.2]],
        ]);

        $this->assertSame(['x' => 0.8, 'y' => 1.2], $result['scales']['tile-1']);
    }

    public function testScaleWithInvalidXYReturnsFalse(): void
    {
        $this->assertFalse($this->normalizer->normalize([
            'order' => [],
            'scales' => ['tile-1' => ['x' => 'bad', 'y' => 1.0]],
        ]));
    }

    public function testScaleXYOutOfRangeReturnsFalse(): void
    {
        $this->assertFalse($this->normalizer->normalize([
            'order' => [],
            'scales' => ['tile-1' => ['x' => 0.3, 'y' => 1.0]],
        ]));
    }

    public function testScaleNonStringKeyReturnsFalse(): void
    {
        // PHP auto-casts numeric array keys to int, so [0 => ...] has int key
        $this->assertFalse($this->normalizer->normalize([
            'order' => [],
            'scales' => [0 => 1.0],
        ]));
    }

    public function testScaleEmptyKeyReturnsFalse(): void
    {
        $this->assertFalse($this->normalizer->normalize([
            'order' => [],
            'scales' => ['' => 1.0],
        ]));
    }

    public function testScaleWithNonNumericNonArrayReturnsFalse(): void
    {
        $this->assertFalse($this->normalizer->normalize([
            'order' => [],
            'scales' => ['tile-1' => true],
        ]));
    }

    public function testFullValidPayload(): void
    {
        $result = $this->normalizer->normalize([
            'order' => ['tile-a', 'tile-b'],
            'scales' => [
                'tile-a' => 1.0,
                'tile-b' => ['x' => 0.7, 'y' => 1.3],
            ],
        ]);

        $this->assertSame(['tile-a', 'tile-b'], $result['order']);
        $this->assertSame(['x' => 1.0, 'y' => 1.0], $result['scales']['tile-a']);
        $this->assertSame(['x' => 0.7, 'y' => 1.3], $result['scales']['tile-b']);
    }

    public function testDefaultsWhenKeysAreMissing(): void
    {
        $result = $this->normalizer->normalize([]);
        $this->assertSame(['order' => [], 'scales' => []], $result);
    }
}
