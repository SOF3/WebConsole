<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\Traverser;
use function sprintf;





































































































/**
 * @template I
 */
final class AddObjectEvent {
    /**
     * @param I $item
     */
    public function __construct(public $item) {
    }
}