<?php
namespace ROH\FNorm\Middleware;

use Exception;
use FastRoute\Dispatcher;
use Bono\Http\Response;
use Bono\Exception\HttpException;
use Norm\Filter\FilterException;

class NormBundleMiddleware
{
    public function __invoke($request, $next)
    {
        $response = $next($request);

        if ($request['routeInfo'][0] === Dispatcher::FOUND) {
            $method = $request['routeInfo'][1][1];
            $template = $request['bundle']['name'].'/'.$method;
            $response['template'] = $template;
        }

        return $response;
    }
}
