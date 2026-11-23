<?php
/** An expected API/domain failure with a safe message and HTTP status. */
class ServiceException extends RuntimeException
{
    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->getCode();
    }
}
