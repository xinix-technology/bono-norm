<?php

namespace BonoNorm\Test;

use PHPUnit\Framework\TestCase;
use BonoNorm\Bundle;
use BonoNorm\Middleware;
use Bono\App;
use Bono\Exception\ContextException;
use Bono\Http\Context;
use Bono\Http\Request;
use Bono\Http\Response;
use Norm\Repository;
use Norm\Collection;
use Norm\Adapter\Memory;
use ROH\Util\Injector;
use Norm\Exception\FilterException;
use Norm\Model;

class BundleTest extends TestCase
{
    public function setUp()
    {
        $this->injector = new Injector();
        $app = new App([], $this->injector);
        $this->injector->delegate(Context::class, function () {
            $app = $this->injector->resolve(App::class);
            $middleware = $this->injector->resolve(Middleware::class, [
                'connections' => [
                    [ Memory::class ],
                ],
            ]);
            $app->addMiddleware($middleware);
            $context = new Context(
                $app,
                $this->injector->resolve(Request::class),
                $this->injector->resolve(Response::class)
            );
            $context['@norm'] = $middleware;
            return $context;
        });
    }

    public function testConstruct()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);
        $this->assertEquals($bundle['collection'], 'Foo');
    }

    public function testGetCollectionWithoutMiddleware()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);
        $context = new Context(new App(), new Request(), new Response());
        try {
            $bundle->getCollection($context);
            $this->fail('Must not here');
        } catch (\Bono\Exception\BonoException $e) {
            if ($e->getMessage() !== 'Unregistered dependency @norm middleware!') {
                throw $e;
            }
        }
    }

    public function testGetCollectionWithMiddleware()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);
        $context = $this->injector->resolve(Context::class);
        $this->assertInstanceOf(Middleware::class, $context['@norm']);
        $bundle->getCollection($context);
    }

    public function testSearch()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);
        $context = $this->injector->resolve(Context::class);

        $this->assertEquals($bundle->search($context)['entries']->getCriteria(), []);
        $context->setRequest($context->getRequest()->withQueryParams([
            'foo!like' => 'bar'
        ]));
        $this->assertEquals($bundle->search($context)['entries']->getCriteria(), ['foo!like' => 'bar']);

        $context->setRequest($context->getRequest()->withQueryParams([
            '!sort' => [
                'foo' => '1'
            ],
            '!skip' => '10',
            '!limit' => '10',
        ]));
        $this->assertEquals($bundle->search($context)['entries']->getSkip(), 10);
        $this->assertEquals($bundle->search($context)['entries']->getLimit(), 10);
        $this->assertEquals($bundle->search($context)['entries']->getSort(), ['foo' => 1]);
    }

    public function testInnerMiddleware()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);
        $context = $this->injector->resolve(Context::class);
        $context['route.info'] = [
            1,
            [
                'handler' => ['Foox', 'bar']
            ]
        ];
        $hit = false;
        $bundle->innerMiddleware($context, function ($context) use (&$hit) {
            $hit = true;
        });

        $this->assertEquals(true, $hit);
        $this->assertEquals($context['@renderer.template'], 'foo/bar');

        $children = [
            new FilterException('foo'),
            new \Exception('bar'),
        ];
        $context['@notification'] = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['notify'])
            ->getMock();
        $context['@notification']->expects($this->exactly(2))->method('notify');
        try {
            $bundle->innerMiddleware($context, function ($context) use ($children) {
                $e = new FilterException();
                $e->setChildren($children);
                throw $e;
            });
        } catch (FilterException $e) {
        }


        $this->assertEquals($context->getStatusCode(), 400);
        $this->assertEquals($context->getState(), ['error' => $children[0]]);
    }

    public function testCreate()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);
        $context = $this->injector->resolve(Context::class);
        $this->assertInstanceOf(Model::class, $bundle->create($context)['entry']);
        $this->assertEquals(true, $bundle->create($context)['entry']->isNew());

        $context = $this->injector->resolve(Context::class);
        $context->setRequest(
            $context->getRequest()
            ->withParsedBody([
                'foo' => 'foo',
                'bar' => 'bar',
            ])
            ->withMethod('POST')
        );
        $this->assertInstanceOf(Model::class, $bundle->create($context)['entry']);
        $this->assertEquals('foo', $bundle->create($context)['entry']['foo']);
        $this->assertEquals('foo', $bundle->create($context)['entry']['foo']);
        $this->assertEquals(false, $bundle->create($context)['entry']->isNew());
    }

    public function testRead()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);
        $context = $this->injector->resolve(Context::class);
        $context['id'] = '1';

        $collection = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['findOne'])
            ->getMock();
        $collection->expects($this->once())->method('findOne')->will($this->returnValue('foo'));

        $middleware = $context['@norm'] = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['factory'])
            ->getMock();
        $middleware->method('factory')->will($this->returnValue($collection));

        $this->assertEquals('foo', $bundle->read($context)['entry']);
    }

    public function testReadNotFound()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);
        $context = $this->injector->resolve(Context::class);
        $context['id'] = '1';

        $collection = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['findOne'])
            ->getMock();
        $collection->expects($this->once())->method('findOne');

        $middleware = $context['@norm'] = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['factory'])
            ->getMock();
        $middleware->method('factory')->will($this->returnValue($collection));

        try {
            $bundle->read($context);
        } catch (ContextException $e) {
            $this->assertEquals($e->getStatusCode(), 404);
        }
    }

    public function testUpdate()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);

        $context = $this->injector->resolve(Context::class);
        $context['id'] = '1';

        $model = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['set', 'save'])
            ->getMock();
        $model->expects($this->once())->method('set');
        $model->expects($this->once())->method('save');

        $collection = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['findOne'])
            ->getMock();
        $collection->expects($this->exactly(2))->method('findOne')->will($this->returnValue($model));

        $middleware = $context['@norm'] = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['factory'])
            ->getMock();
        $middleware->method('factory')->will($this->returnValue($collection));

        $this->assertEquals($bundle->update($context)['entry'], $model);

        $context->setRequest($context->getRequest()->withMethod('PUT'));
        $this->assertEquals($bundle->update($context)['entry'], $model);
    }

    public function testUpdateNotFound()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);
        $context = $this->injector->resolve(Context::class);
        $context['id'] = '1';

        $collection = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['findOne'])
            ->getMock();
        $collection->expects($this->once())->method('findOne');

        $middleware = $context['@norm'] = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['factory'])
            ->getMock();
        $middleware->method('factory')->will($this->returnValue($collection));

        try {
            $bundle->update($context);
        } catch (ContextException $e) {
            $this->assertEquals($e->getStatusCode(), 404);
        }
    }

    public function testDeleteNotFound()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);
        $context = $this->injector->resolve(Context::class);
        $context->setRequest($context->getRequest()->withMethod('DELETE'));
        $context['id'] = '1';

        $collection = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['findOne'])
            ->getMock();
        $collection->expects($this->once())->method('findOne');

        $middleware = $context['@norm'] = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['factory'])
            ->getMock();
        $middleware->method('factory')->will($this->returnValue($collection));

        try {
            $bundle->delete($context);
        } catch (ContextException $e) {
            $this->assertEquals($e->getStatusCode(), 404);
        }
    }

    public function testDelete()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);

        $context = $this->injector->resolve(Context::class);
        $context['id'] = '1';

        $model = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['remove'])
            ->getMock();
        $model->expects($this->once())->method('remove');

        $collection = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['findOne'])
            ->getMock();
        $collection->expects($this->exactly(2))->method('findOne')->will($this->returnValue($model));

        $middleware = $context['@norm'] = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['factory'])
            ->getMock();
        $middleware->method('factory')->will($this->returnValue($collection));

        $this->assertEquals($bundle->delete($context)['entry'], $model);

        $context->setRequest($context->getRequest()->withMethod('DELETE'));
        $this->assertEquals($bundle->delete($context)['entry'], $model);
    }

    public function testGetSchema()
    {
        $bundle = $this->injector->resolve(Bundle::class, [
            'options' => [
                'uri' => '/foo',
            ]
        ]);

        $context = $this->injector->resolve(Context::class);
        $this->assertEquals($bundle->getSchema($context), $bundle->getCollection($context));
    }
}
