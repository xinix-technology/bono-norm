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

        $this->addMiddleware([$this, 'innerMiddleware']);

        parent::__construct($app, $options);
    }

    public function innerMiddleware(Context $context, callable $next)
    {
        $context->depends('@norm');

        // initialize collection
        $this->getCollection($context);

        if ($context->isRouted()) {
            $segments = explode('/', $context['route.info'][1]['handler'][1]);
            $context['@renderer.template'] = $this['name'] . '/' . end($segments);
        }

        try {
            $next($context);
        } catch ( FilterException $e) {
            $context->setStatus(400);

            $errors = $e->getChildren();
            $context->setState('error', $errors[0]);

            // $errors[0]
            foreach ($errors as $error) {
                if ($error instanceof FilterException) {
                    $context->call('@notification', 'notify', [
                        'level' => 'error',
                        'context' => $error->getContext(),
                        'message' => $error->getMessage(),
                    ]);
                } else {
                    $context->call('@notification', 'notify', [
                        'level' => 'error',
                        'message' => $error->getMessage(),
                    ]);
                }
            }
        }
    }

    public function search(Context $context)
    {
        $criteria = [];

        $q = $context->getQueryParams();

        foreach ($q as $key => $value) {
            if ('!' !== substr($key, 0, 1)) {
                $criteria[$key] = $value;
            }
        }
        $entries = $this->getCollection($context)->find($criteria);

        if (isset($q['!sort'])) {
            $entries->sort($q['!sort']);
        }

        if (isset($q['!skip'])) {
            $entries->skip($q['!skip']);
        }

        if (isset($q['!limit'])) {
            $entries->limit($q['!limit']);
        }

        return [
            'entries' => $entries
        ];
    }

    public function create(Context $context)
    {
        $entry = $this->getCollection($context)->newInstance();

        if ('POST' === $context->getMethod()) {
            $entry->set($context->getParsedBody());

            // save state before throw
            $context->setState('entry', $entry);

            $entry->save();

            $context->call('@notification', 'notify', [
                'level' => 'info',
                'message' => 'Data created.',
            ]);
        }

        return [
            'entry' => $entry,
        ];
    }

    public function update(Context $context)
    {

        $entry = null === $context['id'] ? null : $this->getCollection($context)->findOne($context['id']);

        if (null === $entry) {
            return $context->throwError(404);
        }

        if ('PUT' === $context->getMethod()) {
            $entry->set($context->getParsedBody());
            $entry->save();

            $context->call('@notification', 'notify', [
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
        $entry = null === $context['id'] ? null : $this->getCollection($context)->findOne($context['id']);

        if (null === $entry) {
            return $context->throwError(404);
        }

        return [
            'entry' => $entry
        ];
    }

    public function delete(Context $context)
    {
        $entry = null === $context['id'] ? null : $this->getCollection($context)->findOne($context['id']);

        if ('DELETE' === $context->getMethod()) {
            if (null === $entry) {
                return $context->throwError(404);
            }

            $entry->remove();

            $context->call('@notification', 'notify', [
                'level' => 'info',
                'message' => 'Data deleted.',
            ]);
        }

        return [
            'entry' => $entry
        ];
    }

    public function getCollection(Context $context)
    {
        $context->depends('@norm');

        if (null === $this->collection) {
            $this->collection = $context['@norm']->factory($context, $this['collection']);
        }

        return $this->collection;
    }

    public function getSchema(Context $context)
    {
        return $this->getCollection($context);
    }
}
