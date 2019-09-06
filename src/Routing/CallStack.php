<?php

namespace App\Routing;

class CallStack extends \Exedra\Routing\CallStack
{
    public function isLastCallable(callable $callable)
    {
        return $callable === $this->callables[count($this->callables) - 1];
    }
}