<?php

require_once dirname(dirname(__FILE__)).'/src/ResettableMicro.php';

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
    public function testSubscribe() {
        $service = new TestEventService();
        $service->subscribe('event', 'callable');
        $this->assertArrayHasKey('event', $service->subscriptions);
    }

    public function testSubscribeWithRef() {
        $service = new TestEventService();
        $callableRef = [];
        $service->subscribeWithRef('event', $callableRef);
        $this->assertSame($service->subscriptions['event'][0], $callableRef);
    }

    public function testUnsubscribe() {
        $service = new TestEventService();
        $callableRef1 = 'a';
        $callableRef2 = 'b';
        $service->subscribeWithRef('event', $callableRef1);
        $service->subscribeWithRef('event', $callableRef2);

        $this->assertTrue($service->unsubscribe('event', $callableRef1));
        $this->assertCount(1, $service->subscriptions['event']);
        $this->assertFalse($service->unsubscribe('event', $callableRef1));

        $this->assertTrue($service->unsubscribe('event', $callableRef2));
        $this->assertArrayNotHasKey('event', $service->subscriptions);
        $this->assertFalse($service->unsubscribe('event', $callableRef1));
    }

    public function testEmit() {
        Micro::add(TestEventListener::class);
        $testEventListener = Micro::get(TestEventListener::class);
        $service = new TestEventService();
        $service->subscribe('event1', [TestEventListener::class, 'setA']);
        $service->subscribe('event1', [$testEventListener, 'setB']);
        $service->emit('event1', [true]);
        $this->assertTrue($testEventListener->a);
        $this->assertTrue($testEventListener->b);
    }
}