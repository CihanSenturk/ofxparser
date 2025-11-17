<?php

namespace CihanSenturk\OfxParser\Exceptions;

/**
 * Exception thrown when a required OFX field is missing
 */
class MissingRequiredFieldException extends OfxException
{
    /**
     * @param string $fieldName The name of the missing field
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $fieldName, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Missing required OFX field: "%s"', $fieldName);

        parent::__construct($message, $code, $previous);
    }
}
