<?php
namespace ROH\FNorm\Middleware;

use Bono\App;
use Bono\Http\Response;
use ROH\Util\Options;
use ROH\Util\Thing;
use Norm\Norm as TheNorm;
use Norm\Filter\FilterException;

class Norm
{
    public function __construct($options = array())
    {
        $this->options = Options::create([
        ])->merge($options);


        if (!isset($this->options['datasources'])) {
            throw new \Exception('[Norm] No data source configuration');
        }

        $datasources = $this->options['datasources'];

        TheNorm::init($this->options);

        foreach ($datasources as $name => $datasource) {
            $datasource['config']['name'] = $name;

            $meta = new Thing($datasource);

            TheNorm::registerConnection($name, $meta->getHandler());
        }
    }

    public function __invoke($request, $next)
    {
        // TODO set norm !include
        // TODO set norm !tz

        $response = $next($request);
        if ($response->getError() instanceof FilterException) {
            $response = $response->withStatus(400);
            if (empty($request->getHeaderLine('response-content-type')) ||
                $request->getHeaderLine('response-content-type') === 'text/html') {
                foreach ($response->getError()->getChildren() as $ce) {
                    $context = $ce instanceof FilterException ? $ce->context() : '';
                    $request['$notification']->notify([
                        'level' => 'error',
                        'context' => $context,
                        'code' => $ce->getCode(),
                        'message' => $ce->getMessage(),
                    ]);
                }
            } else {
                $response = $response->withError($response->getError()->getChildren()[0]);
            }
        }
        return $response;
    }
}
