<?php

namespace CihanSenturk\OfxParser\Exceptions;

/**
 * Exception thrown when an invalid date format is encountered
 */
class InvalidDateFormatException extends OfxException
{
    /**
     * @param string $dateString The invalid date string
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $dateString, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Invalid date format: "%s"', $dateString);

        parent::__construct($message, $code, $previous);
    }
}
