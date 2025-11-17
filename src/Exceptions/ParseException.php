<?php

namespace CihanSenturk\OfxParser\Exceptions;

/**
 * Exception thrown when OFX parsing fails
 */
class ParseException extends OfxException
{
    /**
     * @param string $message Error message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = 'Failed to parse OFX', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
