<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Pager;

/**
 * @covers \Dynart\Micro\Pager
 */
final class PagerTest extends TestCase {

    public function testCalculateStartEndAndHiddenProperlyWith105ItemsAndDefaultNumberLimit7() {
        $pager = new Pager('/', ['page' => 10, 'page_size' => 5], 105);
        //  [hidden]                      [hidden]
        // 1   ...  [8] 9 10 11 12 13 [14]   ...   [21]
        //        [start]            [end]         [max]
        $this->assertEquals(10, $pager->page());
        $this->assertEquals(7, $pager->start()); // 8 - 1
        $this->assertEquals(13, $pager->end()); // 14 - 1
        $this->assertEquals(20, $pager->max()); // 21 - 1
        $this->assertTrue($pager->hasLeftHidden());
        $this->assertTrue($pager->hasRightHidden());
    }

    public function testCalculateStartEndAndHiddenProperlyWith105ItemsAndNumberLimit5() {
        $pager = new Pager('/', ['page' => 10, 'page_size' => 5], 105, 5);
        //  [hidden]                 [hidden]
        // 1   ...  [9] 10 11 12 [13]  ...   [21]
        //        [start]       [end]        [max]
        $this->assertEquals(8, $pager->start());
        $this->assertEquals(12, $pager->end());
        $this->assertTrue($pager->hasLeftHidden());
        $this->assertTrue($pager->hasRightHidden());
    }

    public function testCalculateStartEndAndHiddenProperlyWith105ItemsAndNumberLimit17() {
        $pager = new Pager('/', ['page' => 10, 'page_size' => 5], 105, 17);
        //   [hidden]                                             [hidden]
        // 1   ...  [3] 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 [19]  ...  [21]
        //        [start]                                     [end]       [max]
        $this->assertEquals(2, $pager->start());
        $this->assertEquals(18, $pager->end());
        $this->assertTrue($pager->hasLeftHidden());
        $this->assertTrue($pager->hasRightHidden());
    }

    public function testCalculateStartEndAndHiddenProperlyWith105ItemsAndNumberLimit19() {
        $pager = new Pager('/', ['page' => 10, 'page_size' => 5], 105, 19);
        //
        // 1 [2] 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 [20] [21]
        // [start]                                          [end] [max]
        $this->assertEquals(1, $pager->start());
        $this->assertEquals(19, $pager->end());
        $this->assertFalse($pager->hasLeftHidden());
        $this->assertFalse($pager->hasRightHidden());
    }

    public function testCalculateStartEndAndHiddenProperlyWith105ItemsAndNumberLimit21() {
        $pager = new Pager('/', ['page' => 10, 'page_size' => 5], 105, 21);
        //
        // [1] 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 [21]
        // [start]                                              [end]&[max]
        $this->assertEquals(0, $pager->start());
        $this->assertEquals(20, $pager->end());
        $this->assertFalse($pager->hasLeftHidden());
        $this->assertFalse($pager->hasRightHidden());
    }

    public function testCalculateNextPrevProperlyWhenNoPrevNext() {
        $pager = new Pager('/', ['page' => 0, 'page_size' => 5], 3);
        $this->assertFalse($pager->prev());
        $this->assertFalse($pager->next());
    }

    public function testCalculateNextPrevProperlyWhenJustNext() {
        $pager = new Pager('/', ['page' => 0, 'page_size' => 5], 6);
        $this->assertFalse($pager->prev());
        $this->assertTrue($pager->next());
    }

    public function testCalculateNextPrevProperlyWhenJustPrev() {
        $pager = new Pager('/', ['page' => 1, 'page_size' => 5], 6);
        $this->assertTrue($pager->prev());
        $this->assertFalse($pager->next());
    }

    public function testCalculateNextPrevProperlyWhenBothTrue() {
        $pager = new Pager('/', ['page' => 1, 'page_size' => 5], 11);
        $this->assertTrue($pager->prev());
        $this->assertTrue($pager->next());
    }

    public function testPageCantBeBiggerThanMax() {
        $pager = new Pager('/', ['page' => 100, 'page_size' => 5], 11);
        $this->assertEquals($pager->max(), $pager->page());
    }

    public function testPageCantBeLesserThanZero() {
        $pager = new Pager('/', ['page' => -1, 'page_size' => 5], 11);
        $this->assertEquals(0, $pager->page());
    }

    public function testRouteWasSet() {
        $pager = new Pager('/', ['page' => 0, 'page_size' => 5], 11);
        $this->assertEquals('/', $pager->route());
    }

    public function testParamsForPage() {
        $pager = new Pager('/', ['page' => 0, 'page_size' => 5, 'extra_param' => 1], 11);
        $this->assertEquals(['page' => 1, 'page_size' => 5, 'extra_param' => 1], $pager->paramsForPage(1));
    }

    public function testParams() {
        $pager = new Pager('/', ['page' => 0, 'page_size' => 5, 'extra_param' => 1], 11);
        $this->assertEquals(['page' => 0, 'page_size' => 5, 'extra_param' => 1], $pager->params());
    }
}