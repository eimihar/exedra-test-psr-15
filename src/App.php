<?php
namespace App;

use Exedra\Application;
use Exedra\Http\Stream;
use Exedra\Routing\Finding;
use Exedra\Runtime\Context;
use Psr\Http\Message\ResponseInterface;

class App extends Application
{
    /**
     * Execute the call stack
     * @param Finding $finding
     * @return Context
     */
    public function run(Finding $finding)
    {
        /** @var Context $context */
        $context = $this->create('runtime.context', array($this, $finding, $this->create('runtime.response')));

        // The first call
        $response = $context->next($finding->getRequest(), new RequestHandler($context, $context->response, $finding));

        // mutate the context and update the response
        if ($response instanceof \Exedra\Http\Response)
            $context->services['response'] = $response;
        else if ($response instanceof ResponseInterface)
            $context->services['response'] = \Exedra\Http\Response::createFromPsrResponse($response);
        else if (get_class($context->response) == \Exedra\Http\Response::class)
            $context->response->setBody(Stream::createFromContents($response));
        else
            $context->response->setBody($response);

        return $context;
    }
}