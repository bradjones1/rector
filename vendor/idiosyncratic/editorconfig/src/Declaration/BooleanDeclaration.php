<?php

declare (strict_types=1);
namespace RectorPrefix20210511\Idiosyncratic\EditorConfig\Declaration;

use RectorPrefix20210511\Idiosyncratic\EditorConfig\Exception\InvalidValue;
use function is_bool;
abstract class BooleanDeclaration extends \RectorPrefix20210511\Idiosyncratic\EditorConfig\Declaration\Declaration
{
    /**
     * @inheritdoc
     */
    public function validateValue($value) : void
    {
        if (\is_bool($value) === \false) {
            throw new \RectorPrefix20210511\Idiosyncratic\EditorConfig\Exception\InvalidValue($this->getStringValue(), $this->getName());
        }
    }
}