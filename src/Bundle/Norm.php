<?php
namespace ROH\BonoNorm\Bundle;

use LogicException;
use RuntimeException;
use InvalidArgumentException;
use Bono\Bundle\Rest;
use Bono\App;
use Bono\Http\Context;
use Norm\Exception\FilterException;
use ROH\Util\Inflector;

class Norm extends Rest
{
    protected $collection;

    public function __construct(App $app, array $options = [])
    {
        // detect collection if not specified
        if (!isset($options['collection'])) {
            $explodedUri = explode('/', $options['uri']);
            $lastSegment = end($explodedUri);
            $options['collection'] = Inflector::humanize($lastSegment);
        }

        $options['name'] = strtolower($options['collection']);

        $this->addMiddleware(function (Context $context, $next) {
            $context->depends('@norm');

            // initialize collection
            $this->getCollection($context);

            if ($context['route.info'][0] === 1) {
                $segments = explode('/', $context['route.info'][1]['handler'][1]);
                $context['response.template'] = $this['name'] . '/' . end($segments);
            }

            try {
                $next($context);
            } catch ( FilterException $e) {
                if (isset($context['@notification'])) {
                    $errors = $e->getChildren();
                    foreach ($errors as $error) {
                        if (!($error instanceof FilterException)) {
                            throw $error;
                        }
                        $context['@notification']->notify([
                            'level' => 'error',
                            'context' => $error->getContext(),
                            'message' => $error->getMessage(),
                        ]);
                    }
                }
            }
        });

        parent::__construct($app, $options);
    }

    public function search(Context $context)
    {
        $entries = $this->getCollection()->find();
        return [
            'entries' => $entries
        ];
    }

    public function create(Context $context)
    {
        if ('POST' === $context->getMethod()) {
            $entry = $this->getCollection()->newInstance();
            $entry->set($context->getParsedBody());
            $entry->save();

            $context['@notification']->notify([
                'level' => 'info',
                'message' => 'Data created.',
            ]);

            return [
                'entry' => $entry,
            ];
        }
    }

    public function update(Context $context)
    {

        $id = $context['id'];
        if (!empty($id)) {
            $entry = $entry = $this->getCollection()->findOne($id);
        }

        if (is_null($entry)) {
            return $context->throwError(404);
        }

        if ('PUT' === $context->getMethod()) {
            $entry->set($context->getParsedBody());
            $entry->save();

            $context['@notification']->notify([
                'level' => 'info',
                'message' => 'Data updated.',
            ]);
        }

        return [
            'entry' => $entry
        ];
    }

    public function read(Context $context)
    {
        $id = $context['id'];
        if (!empty($id)) {
            $entry = $this->getCollection()->findOne($id);
        }

        if (is_null($entry)) {
            return $context->throwError(404);
        }

        return [
            'entry' => $entry
        ];
    }

    public function delete(Context $context)
    {
        if ('DELETE' === $context->getMethod()) {
            if (isset($context['id'])) {
                $ids = [$context['id']];
            }

            foreach ($ids as $id) {
                $entry = $this->getCollection()->findOne($context['id']);
                if (isset($entry)) {
                    $entry->remove();
                }
            }

            $context['@notification']->notify([
                'level' => 'info',
                'message' => 'Data deleted.',
            ]);
        }
    }

    public function getCollection(Context $context = null)
    {
        if (is_null($this->collection)) {
            $this->collection = $context['@norm']->factory($this['collection']);
        }

        return $this->collection;
    }

    public function getSchema()
    {
        return $this->collection->getSchema();
    }
}
