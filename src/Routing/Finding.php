<?php

namespace App\Routing;

use App\RequestHandler;
use Exedra\Contracts\Routing\ExecuteHandler;
use Exedra\Exception\Exception;
use Exedra\Exception\InvalidArgumentException;
use Exedra\Http\ServerRequest;
use Exedra\Routing\ExecuteHandlers\DynamicHandler;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Finding extends \Exedra\Routing\Finding
{
    /**
     * @param $middleware
     * @return \Closure
     * @throws InvalidArgumentException
     */
    protected function resolveMiddleware($middleware)
    {
        if (!(is_object($middleware) && $middleware instanceof MiddlewareInterface))
            return parent::resolveMiddleware($middleware);

        return function(ServerRequestInterface $request, RequestHandlerInterface $handler) use ($middleware) {
            return $middleware->process($request, $handler);
        };
    }

    /**
     * Resolve finding informations and returns a CallStack
     * resolve middlewares, config, attributes
     * @return CallStack|null
     * @throws Exception
     * @throws InvalidArgumentException
     */
    protected function resolve()
    {
        $callStack = new CallStack(); // use own CallStack

        $executePattern = $this->route->getProperty('execute');

        /** @var ExecuteHandler[] $handlers */
        $handlers = array();

        foreach ($this->route->getFullRoutes() as $route) {
            $group = $route->getGroup();

            foreach ($group->factory->getExecuteHandlers() as $handler)
                $handlers[get_class($handler)] = $handler;

            // stack all the handlers
            foreach ($group->getExecuteHandlers() as $name => $handler)
                $handlers[$name] = $handler;

            foreach ($group->getMiddlewares() as $key => $middleware)
                $callStack->addCallable($this->resolveMiddleware($middleware[0]), $middleware[1]);

            // append all route middlewares
            foreach ($route->getProperty('middleware') as $key => $middleware)
                $callStack->addCallable($this->resolveMiddleware($middleware[0]), $middleware[1]);

            foreach ($route->getAttributes() as $key => $value) {
                if (is_array($value)) {
                    if (isset($this->attributes[$key]) && !is_array($this->attributes[$key]))
                        throw new Exception('Unable to push value into attribute [' . $key . '] on route ' . $route->getAbsoluteName() . '. The attribute type is not an array.');

                    foreach ($value as $val) {
                        $this->attributes[$key][] = $val;
                    }
                } else {
                    $this->attributes[$key] = $value;
                }
            }

            // pass config.
            if ($config = $route->getProperty('config'))
                $this->config = array_merge($this->config, $config);
        }

        foreach ($handlers as $name => $class) {
            $handler = null;

            if (is_string($class)) {
                $handler = new $class;
            } else if (is_object($class)) {
                if ($class instanceof \Closure) {
                    $class($handler = new DynamicHandler());
                } else if ($class instanceof ExecuteHandler) {
                    $handler = $class;
                }
            }

            if (!$handler || !is_object($handler) || !($handler instanceof ExecuteHandler))
                throw new InvalidArgumentException('Handler must be either class name, ' . ExecuteHandler::class . ' or \Closure ');

            if ($handler->validateHandle($executePattern)) {
                $resolve = $handler->resolveHandle($executePattern);

                if (!is_callable($resolve))
                    throw new \Exedra\Exception\InvalidArgumentException('The resolveHandle() method for handler [' . get_class($handler) . '] must return \Closure or callable');

                $properties = array();

                if ($this->route->hasDependencies())
                    $properties['dependencies'] = $this->route->getProperty('dependencies');

                if (!$resolve)
                    throw new InvalidArgumentException('The route [' . $this->route->getAbsoluteName() . '] execute handle was not properly resolved. ' . (is_string($executePattern) ? ' [' . $executePattern . ']' : ''));

                $callStack->addCallable($resolve, $properties);

                return $callStack;
            }
        }

        return null;
    }
}