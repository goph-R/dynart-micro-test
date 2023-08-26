<?php

require_once dirname(dirname(__FILE__)).'/src/ResettableMicro.php';
use Dynart\Micro\Test\ResettableMicro;

use PHPUnit\Framework\TestCase;

use Dynart\Micro\EventService;
use Dynart\Micro\Micro;


class TestEventService extends EventService {
    public $subscriptions = [];
}

class TestEventListener {
    public $a = false;
    public $b = false;
    public function setA() {
        $this->a = true;
    }
    public function setB() {
        $this->b = true;
    }
}

/**
 * @covers \Dynart\Micro\EventService
 */
final class EventServiceTest extends TestCase
{
    const EVENT = 'test:event';

    protected function setUp(): void {
        ResettableMicro::reset();
    }

    public function testSubscribe() {
        $service = new TestEventService();
        $service->subscribe(self::EVENT, 'callable');
        $this->assertArrayHasKey(self::EVENT, $service->subscriptions);
    }

    public function testSubscribeWithRef() {
        $service = new TestEventService();
        $callableRef = 'Something::method';
        $service->subscribeWithRef(self::EVENT, $callableRef);
        $this->assertSame($service->subscriptions[self::EVENT][0], $callableRef);
    }

    public function testUnsubscribe() {
        $service = new TestEventService();
        $callableRef1 = 'a';
        $callableRef2 = 'b';
        $service->subscribeWithRef(self::EVENT, $callableRef1);
        $service->subscribeWithRef(self::EVENT, $callableRef2);

        $this->assertTrue($service->unsubscribe(self::EVENT, $callableRef1));
        $this->assertCount(1, $service->subscriptions[self::EVENT]);
        $this->assertFalse($service->unsubscribe(self::EVENT, $callableRef1));

        $this->assertTrue($service->unsubscribe(self::EVENT, $callableRef2));
        $this->assertArrayNotHasKey(self::EVENT, $service->subscriptions);

        $this->assertFalse($service->unsubscribe(self::EVENT, $callableRef1));
    }

    public function testEmit() {
        Micro::add(TestEventListener::class);
        $testEventListener = Micro::get(TestEventListener::class);
        $service = new TestEventService();
        $service->subscribe(self::EVENT, [TestEventListener::class, 'setA']);
        $service->subscribe(self::EVENT, [$testEventListener, 'setB']);
        $service->emit(self::EVENT, [true]);
        $this->assertTrue($testEventListener->a);
        $this->assertTrue($testEventListener->b);
    }
}