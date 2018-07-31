<?php
namespace BonoNorm;

use Bono\Http\Context;
use ROH\Util\Options;
use ROH\Util\Injector;
use Norm\Repository;
use Norm\Filter;
use Bono\App;

class Middleware
{
    protected $app;

    protected $connections;

    protected $default;

    protected $resolvers;

    protected $attributes;

    protected $repository;

    protected $renderer;

    public function __construct(
        App $app,
        array $connections,
        array $attributes = [],
        array $default = null,
        array $resolvers = []
    ) {
        $this->app = $app;
        $this->connections = $connections;
        $this->default = $default;
        $this->resolvers = $resolvers;
        $this->attributes = (new Options([
            'salt' => null,
            'nfile.uploadUrl' => '/norm/upload',
        ]))->merge($attributes)->toArray();
    }

    public function getRepository(Context $context)
    {
        $injector = $this->app->getInjector();
        if (null === $this->repository) {
            $this->repository = new Repository($this->attributes);

            foreach ($this->connections as $connectionDescriptor) {
                $this->repository->addConnection($injector->resolve($connectionDescriptor, [
                    'repository' => $this->repository,
                ]));
            }

            if (null !== $this->default) {
                $this->repository->setDefault($this->default);
            }

            if (null !== $this->resolvers) {
                foreach ($this->resolvers as $resolver) {
                    $this->repository->addResolver($injector->resolve($resolver));
                }
            }

            if (null !== $context['@renderer']) {
                $this->renderer = $context['@renderer'];
                $this->repository->setRenderer([$this, 'defaultRenderer']);
            }
        }
        return $this->repository;
    }

    public function defaultRenderer($template, $data)
    {
        return $this->renderer->resolve($template)
            ? $this->renderer->render($template, $data)
            : $this->repository->defaultRender($template, $data);
    }

    public function factory(Context $context, $collectionId, $connectionId = '')
    {
        return $this->getRepository($context)->factory($collectionId, $connectionId);
    }

    protected function routeUpload(Context $context)
    {
        $dataDir = $context->getHeaderLine('x-data-dir');
        @mkdir($dataDir, 0755, true);
        $result = [];
        foreach ($context->getUploadedFiles() as $files) {
            foreach ($files as $file) {
                $filename = $file->getClientFilename();
                $file->moveTo($dataDir . '/' . $filename);
                $result[] = $filename;
            }
        }
        $context->setStatus(200);
        $context->setContentType('application/json');
        $context->setState('files', $result);
    }

    public function __invoke(Context $context, callable $next)
    {
        if ($this->attributes['nfile.uploadUrl'] === $context->getUri()->getPath()) {
            $this->routeUpload($context);
        } else {
            // initiate repository
            $repository = $this->getRepository($context);
            if (isset($context['timezone'])) {
                $repository->setAttribute('timezone', $context['timezone']);
            }

            if (null !== $context['@renderer']) {
                $context['@renderer']->addTemplatePath(__DIR__ . '/../../templates');
            }

            $context['@norm'] = $this;
            $next($context);
        }
    }
}
