<?php

namespace OnPage\Exceptions;

class FieldNotFound extends GenericException
{
    static function from($field) : self {
        return new self("Cannot find field $field");
    }
}
