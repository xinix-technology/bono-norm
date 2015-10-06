<?php
namespace ROH\FNorm\Bundle;

use Bono\Bundle;
use Norm\Norm;
use Bono\Http\Response;
use ROH\FNorm\Middleware\NormBundleMiddleware;

class NormBundle extends Bundle
{
    public function __construct($options = array())
    {
        if (!isset($options['collection'])) {
            throw new \InvalidArgumentException('Norm bundle need collection name');
        }

        $options['name'] = strtolower($options['collection']);
        $options['controllerO'] = Norm::factory($options['collection']);
        $options['schema'] = $options['controllerO']->schema();

        $this->addMiddleware([
            'class' => NormBundleMiddleware::class
        ]);

        parent::__construct($options);

        $this->routeMap(['GET'], '/', [$this, 'search']);
        $this->routeMap(['POST'], '/', [$this, 'create']);
        $this->routeMap(['GET'], '/{id}', [$this, 'read']);
        $this->routeMap(['PUT'], '/{id}', [$this, 'update']);
        $this->routeMap(['DELETE'], '/{id}', [$this, 'delete']);

        $this->routeMap(['GET', 'POST'], '/null/create', [$this, 'create']);
        $this->routeMap(['GET'], '/{id}/read', [$this, 'read']);
        $this->routeMap(['GET', 'PUT'], '/{id}/update', [$this, 'update']);
        $this->routeMap(['GET', 'DELETE'], '/{id}/delete', [$this, 'delete']);
    }

    public function search($request)
    {
        $entries = $this['controllerO']->find();
        return [
            'entries' => $entries
        ];
    }

    public function create($request)
    {
        if ($request->isPost()) {
            $body = $request->getParsedBody();
            if (is_null($body)) {
                throw new \RuntimeException('Unparsed body, please use body parser middleware');
            }

            $entry = $this['controllerO']->newInstance();
            $entry->set($body);
            $entry->save();

            $request['$notification']->notify([
                'level' => 'info',
                'message' => 'Data created.',
            ]);

            return [
                'entry' => $entry,
            ];
        }
    }

    public function update($request)
    {

        $id = $request['id'];
        if (!empty($id)) {
            $entry = $entry = $this['controllerO']->findOne($id);
        }

        if (is_null($entry)) {
            return Response::notFound();
        }

        if ($request->isPut()) {
            $body = $request->getParsedBody();
            if (is_null($body)) {
                throw new \RuntimeException('Unparsed body, please use body parser middleware');
            }

            $entry->set($body);
            $entry->save();

            $request['$notification']->notify([
                'level' => 'info',
                'message' => 'Data updated.',
            ]);
        }

        return [
            'entry' => $entry
        ];
    }

    public function read($request)
    {
        $id = $request['id'];
        if (!empty($id)) {
            $entry = $entry = $this['controllerO']->findOne($id);
        }

        if (is_null($entry)) {
            return Response::notFound();
        }

        return [
            'entry' => $entry
        ];
    }

    public function delete($request)
    {
        if ($request->isDelete()) {
            if (isset($request['id'])) {
                $ids = [$request['id']];
            }

            foreach ($ids as $id) {
                $entry = $this['controllerO']->findOne($request['id']);
                if (isset($entry)) {
                    $entry->remove();
                }
            }

            $request['$notification']->notify([
                'level' => 'info',
                'message' => 'Data deleted.',
            ]);
        }
    }
}
