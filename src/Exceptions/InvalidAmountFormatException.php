<?php

namespace CihanSenturk\OfxParser\Exceptions;

/**
 * Exception thrown when an invalid amount format is encountered
 */
class InvalidAmountFormatException extends OfxException
{
    /**
     * @param string $amountString The invalid amount string
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $amountString, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Invalid amount format: "%s"', $amountString);

        parent::__construct($message, $code, $previous);
    }
}
