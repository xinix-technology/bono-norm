<?php
namespace ROH\FNorm\Middleware;

use F\App;
use ROH\Util\Options;
use ROH\Util\Thing;
use F\Http\Response;
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

        TheNorm::init(null, $this->options['collections']);

        foreach ($datasources as $name => $datasource) {
            $datasource['config']['name'] = $name;

            $meta = new Thing($datasource);

            TheNorm::registerConnection($name, $meta->getHandler());
        }
    }

    public function __invoke($request, $next)
    {
        $notification = $request->getAttribute('notification');
        // TODO set norm !include
        // TODO set norm !tz
        try {
            return $next($request);
        } catch (FilterException $e) {
            if ($request->getContentType() === 'text/html') {
                $response = Response::error(null, 400);

                foreach ($e->getChildren() as $ce) {
                    $context = $ce instanceof FilterException ? $ce->context() : '';
                    $notification->notify([
                        'level' => 'error',
                        'context' => $context,
                        'code' => $ce->getCode(),
                        'message' => $ce->getMessage(),
                    ]);
                }
                return $response;
            } else {
                return Response::error($e->getChildren()[0], 400);
            }
        }
    }
}
