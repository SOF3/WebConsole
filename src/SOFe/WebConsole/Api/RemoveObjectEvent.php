<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use libs\_734bfd15e9991e6b\SOFe\AwaitGenerator\Traverser;
use function sprintf;
















































































































/**
 * @template I
 */
final class RemoveObjectEvent {
    /**
     * @param I $item
     */
    public function __construct(public $item) {
    }
}