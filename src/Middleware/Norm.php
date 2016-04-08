<?php
namespace ROH\BonoNorm\Middleware;

use Bono\Http\Context;
use ROH\Util\Options;
use Norm\Repository;
use Norm\Filter;
use Bono\App;

class Norm
{
    protected $options;

    protected $repository;

    public function __construct(array $options = [])
    {
        $this->options = Options::create([])->merge($options)->toArray();
    }

    public function getRepository(Context $context = null)
    {
        if (is_null($this->repository)) {
            $this->repository = new Repository($this->options);

            if (isset($context['response.renderer'])) {
                $repository = $this->repository;
                $this->repository->setRenderer(function ($template, $data) use ($context, $repository) {
                    if ($context['response.renderer']->resolve($template)) {
                        return $context['response.renderer']->render($template, $data);
                    } else {
                        return $repository->defaultRender($template, $data);
                    }
                });
            }
            // $this->repository->setRenderer()
        }
        return $this->repository;
    }

    public function factory($collectionId, $connectionId = '')
    {
        return $this->repository->factory($collectionId, $connectionId);
    }

    public function __invoke(Context $context, $next)
    {
        // initiate repository
        $repository = $this->getRepository($context);
        if (isset($context['timezone'])) {
            $repository->setAttribute('timezone', $context['timezone']);
        }
        // TODO set norm !include

        $context['@norm'] = $this;

        $next($context);
    }
}