<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use libs\_1ef0ff44a3bf42da\SOFe\AwaitGenerator\Traverser;
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