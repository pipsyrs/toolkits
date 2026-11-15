<?php

namespace App\Exceptions;

use RuntimeException;

// Message is safe to show to the visitor.
class FileProcessingException extends RuntimeException {}
