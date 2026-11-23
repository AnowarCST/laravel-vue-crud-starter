<?php

namespace App\Support;

use RuntimeException;

/**
 * Thrown by ImageReencoder when a file cannot be decoded or re-encoded.
 */
class UnreadableImageException extends RuntimeException {}
