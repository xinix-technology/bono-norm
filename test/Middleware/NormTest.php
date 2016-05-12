<?php

namespace ROH\BonoNorm\Test\Middleware;

use PHPUnit_Framework_TestCase;
use ROH\BonoNorm\Middleware\Norm;
use Bono\App;
use Bono\Http\Context;
use Norm\Repository;
use Norm\Collection;
use Norm\Adapter\Memory;
use ROH\Util\Injector;

class NormTest extends PHPUnit_Framework_TestCase {
    public function setUp()
    {
        $this->injector = new Injector();
        $app = new App([], $this->injector);
    }

    public function testConstruct()
    {
        $middleware = $this->injector->resolve(Norm::class, [
            'connections' => [
                [Memory::class],
            ]
        ]);
        $this->assertInstanceOf(Norm::class, $middleware);

        $repository = $middleware->getRepository($this->injector->resolve(Context::class));
        $this->assertInstanceOf(Repository::class, $repository);
        $this->assertInstanceOf(\Norm\Resolver\DefaultResolver::class, $repository->getResolvers()[0]);
        $this->assertEquals(1, count($repository->getResolvers()));
    }

    public function testConstructWithAttributesAndDefault()
    {
        $middleware = $this->injector->resolve(Norm::class, [
            'connections' => [
                [Memory::class],
            ],
            'attributes' => [],
            'default' => [],
        ]);
        $this->assertInstanceOf(Norm::class, $middleware);

        $repository = $middleware->getRepository($this->injector->resolve(Context::class));
        $this->assertInstanceOf(Repository::class, $repository);
        $this->assertInstanceOf(\Norm\Resolver\DefaultResolver::class, $repository->getResolvers()[0]);
        $this->assertEquals(1, count($repository->getResolvers()));
    }

    public function testConstructWithResolvers()
    {
        $resolver1 = function() {};
        $resolver2 = function() {};
        $middleware = $this->injector->resolve(Norm::class, [
            'connections' => [
                [Memory::class],
            ],
            'attributes' => [],
            'default' => [],
            'resolvers' => [
                $resolver1,
                $resolver2,
            ],
        ]);
        $this->assertInstanceOf(Norm::class, $middleware);

        $repository = $middleware->getRepository($this->injector->resolve(Context::class));
        $this->assertInstanceOf(Repository::class, $repository);
        $this->assertInstanceOf(\Closure::class, $repository->getResolvers()[0]);
        $this->assertEquals(2, count($repository->getResolvers()));
        $this->assertEquals($resolver1, $repository->getResolvers()[0]);
        $this->assertEquals($resolver2, $repository->getResolvers()[1]);
    }

    public function testGetRepositoryWithRenderer()
    {
        $middleware = $this->injector->resolve(Norm::class, [
            'connections' => [
                [Memory::class],
            ],
        ]);
        $this->assertInstanceOf(Norm::class, $middleware);

        $context = $this->injector->resolve(Context::class);
        $renderer = $context['@renderer'] = $this->getMock(\stdClass::class, ['resolve', 'render']);
        $renderer->expects($this->once())->method('resolve')->will($this->returnValue(true));
        $renderer->expects($this->once())->method('render');

        $repository = $middleware->getRepository($context);
        $this->assertInstanceOf(Repository::class, $repository);
        $repository->render('foo');
    }

    public function testFactory()
    {
        $middleware = $this->injector->resolve(Norm::class, [
            'connections' => [
                [Memory::class],
            ],
        ]);

        $context = $this->injector->resolve(Context::class);
        $collection = $middleware->factory($context, 'Foo');
        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function testInvoke()
    {
        $middleware = $this->injector->resolve(Norm::class, [
            'connections' => [
                [Memory::class],
            ],
        ]);

        $context = $this->injector->resolve(Context::class);
        $context['timezone'] = 'Foo/Bar';

        $renderer = $context['@renderer'] = $this->getMock(\stdClass::class, ['resolve', 'render', 'addTemplatePath']);
        $renderer->expects($this->once())->method('addTemplatePath');

        $hit = false;
        $middleware->__invoke($context, function() use (&$hit) {
            $hit = true;
        });

        $this->assertEquals('Foo/Bar', $middleware->getRepository($context)->getAttribute('timezone'));

        $context->depends('@norm');
        $this->assertEquals(true, $hit);
    }
}