<?php
namespace App;

use App\Routing\CallStack;
use Exedra\Exception\InvalidArgumentException;
use Exedra\Http\Response;
use Exedra\Http\Stream;
use Exedra\Runtime\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    protected $response;
    /**
     * @var \Exedra\Routing\Finding
     */
    private $finding;

    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context, Response $response, \Exedra\Routing\Finding $finding)
    {
        $this->response = $response;
        $this->finding = $finding;
        $this->context = $context;
    }

    /**
     * Handles a request and produces a response.
     *
     * May call other collaborating code to generate the response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var CallStack $callStack */
        $callStack = $this->finding->getCallStack();
        $next = $this->finding->getCallStack()->getNextCallable();

        if ($callStack->isLastCallable($next))
            $response = call_user_func_array($next, [$this->context]);
        else
            $response = call_user_func_array($next, [$request, $this]);

        if (is_string($response))
            $response = Response::createFromPsrResponse($this->response)->withBody(Stream::createFromContents($response));
        else if ((!is_object($response) && $response instanceof ResponseInterface))
            throw new InvalidArgumentException('Unknown response format');

        return $response;
    }

    public function getResponse()
    {
        return $this->response;
    }
}