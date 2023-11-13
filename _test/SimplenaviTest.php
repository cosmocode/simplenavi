<?php

namespace dokuwiki\plugin\simplenavi\test;

use DokuWikiTest;
use TestRequest;

/**
 * General tests for the simplenavi plugin
 *
 * @author  Michael Große <grosse@cosmocode.de>
 *
 * @group plugin_simplenavi
 * @group plugins
 */
class SimplenaviTest extends DokuWikiTest
{

    protected $pluginsEnabled = array('simplenavi');

    public function setUp(): void
    {
        parent::setUp();
        saveWikiText('sidebar', '{{simplenavi>}}', 'create test sidebar');

        $pages = [
            ['foo', 'Foo Page'],
            ['simplenavi', 'Self Start'],
            ['namespace1:start', 'ZZZ Namespace 1 Start'],
            ['namespace2:foo', 'Namespace 2 Foo'],
            ['namespace2', 'Namespace 2 Start'],
            ['namespace12:foo', 'Namespace 12 Foo'],
            ['namespace12:start', 'Namespace 12 Start'],
            ['namespace123:namespace123', 'AAA Namespace 123 Start'],
            ['namespace123:foo', 'Namespace 123 Foo'],
            ['namespace123:deep:start', 'Namespace 123 Deep Start'],
            ['namespace123:deep:foo', 'Namespace 123 Deep Foo'],
            ['namespace21:foo', 'Namespace 21 Foo'],
            ['namespace21:start', 'Namespace 21 Start'],
        ];

        foreach ($pages as $page) {
            saveWikiText('simplenavi:' . $page[0], '====== ' . $page[1] . ' ======', 'create test page');
        }

    }

    public function dataProvider()
    {

        yield [
            'set' => 'by ID, all branches closed',
            'titlesort' => false,
            'natsort' => false,
            'nsfirst' => false,
            'home' => false,
            'current' => 'simplenavi:page',
            'expect' => [
                'simplenavi:foo',
                'simplenavi:namespace1:start',
                'simplenavi:namespace12:start',
                'simplenavi:namespace123:namespace123',
                'simplenavi:namespace2',
                'simplenavi:namespace21:start',
            ]
        ];

        yield [
            'set' => 'by ID, Natural Sort, all branches closed',
            'titlesort' => false,
            'natsort' => true,
            'nsfirst' => false,
            'home' => false,
            'current' => 'simplenavi:page',
            'expect' => [
                'simplenavi:foo',
                'simplenavi:namespace1:start',
                'simplenavi:namespace2',
                'simplenavi:namespace12:start',
                'simplenavi:namespace21:start',
                'simplenavi:namespace123:namespace123',
            ]
        ];

        yield [
            'set' => 'by ID, branch open',
            'titlesort' => false,
            'natsort' => false,
            'nsfirst' => false,
            'home' => false,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                'simplenavi:foo',
                'simplenavi:namespace1:start',
                'simplenavi:namespace12:start',
                'simplenavi:namespace123:namespace123',
                'simplenavi:namespace123:deep:start',
                'simplenavi:namespace123:deep:foo',
                'simplenavi:namespace123:foo',
                'simplenavi:namespace2',
                'simplenavi:namespace21:start',
            ]
        ];

        yield [
            'set' => 'by ID, Natural Sort, branch open',
            'titlesort' => false,
            'natsort' => true,
            'nsfirst' => false,
            'home' => false,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                'simplenavi:foo',
                'simplenavi:namespace1:start',
                'simplenavi:namespace2',
                'simplenavi:namespace12:start',
                'simplenavi:namespace21:start',
                'simplenavi:namespace123:namespace123',
                'simplenavi:namespace123:deep:start',
                'simplenavi:namespace123:deep:foo',
                'simplenavi:namespace123:foo',
            ]
        ];

        yield [
            'set' => 'by ID, Natural Sort, NS first, branch open',
            'titlesort' => false,
            'natsort' => true,
            'nsfirst' => true,
            'home' => false,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                'simplenavi:namespace1:start',
                'simplenavi:namespace2',
                'simplenavi:namespace12:start',
                'simplenavi:namespace21:start',
                'simplenavi:namespace123:namespace123',
                'simplenavi:namespace123:deep:start',
                'simplenavi:namespace123:deep:foo',
                'simplenavi:namespace123:foo',
                'simplenavi:foo',
            ]
        ];

        yield [
            'set' => 'by Title, all branches closed',
            'titlesort' => true,
            'natsort' => false,
            'nsfirst' => false,
            'home' => false,
            'current' => 'simplenavi:page',
            'expect' => [
                'simplenavi:namespace123:namespace123',
                'simplenavi:foo',
                'simplenavi:namespace12:start',
                'simplenavi:namespace2',
                'simplenavi:namespace21:start',
                'simplenavi:namespace1:start',
            ]
        ];

        yield [
            'set' => 'by Title, Natural Search, all branches closed',
            'titlesort' => true,
            'natsort' => true,
            'nsfirst' => false,
            'home' => false,
            'current' => 'simplenavi:page',
            'expect' => [
                'simplenavi:namespace123:namespace123',
                'simplenavi:foo',
                'simplenavi:namespace2',
                'simplenavi:namespace12:start',
                'simplenavi:namespace21:start',
                'simplenavi:namespace1:start',
            ]
        ];

        yield [
            'set' => 'by Title, branch open',
            'titlesort' => true,
            'natsort' => false,
            'nsfirst' => false,
            'home' => false,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                'simplenavi:namespace123:namespace123',
                'simplenavi:namespace123:deep:start',
                'simplenavi:namespace123:deep:foo',
                'simplenavi:namespace123:foo',
                'simplenavi:foo',
                'simplenavi:namespace12:start',
                'simplenavi:namespace2',
                'simplenavi:namespace21:start',
                'simplenavi:namespace1:start',
            ]
        ];

        yield [
            'set' => 'by Title, Natural Sort, branch open',
            'titlesort' => true,
            'natsort' => true,
            'nsfirst' => false,
            'home' => false,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                'simplenavi:namespace123:namespace123',
                'simplenavi:namespace123:deep:start',
                'simplenavi:namespace123:deep:foo',
                'simplenavi:namespace123:foo',
                'simplenavi:foo',
                'simplenavi:namespace2',
                'simplenavi:namespace12:start',
                'simplenavi:namespace21:start',
                'simplenavi:namespace1:start',
            ]
        ];

        yield [
            'set' => 'by Title, Natural Sort, NS first, branch open',
            'titlesort' => true,
            'natsort' => true,
            'nsfirst' => true,
            'home' => false,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                'simplenavi:namespace123:namespace123',
                'simplenavi:namespace123:deep:start',
                'simplenavi:namespace123:deep:foo',
                'simplenavi:namespace123:foo',
                'simplenavi:namespace2',
                'simplenavi:namespace12:start',
                'simplenavi:namespace21:start',
                'simplenavi:namespace1:start',
                'simplenavi:foo',
            ]
        ];

        yield [
            'set' => 'by ID, branch open with home level',
            'titlesort' => false,
            'natsort' => false,
            'nsfirst' => false,
            'home' => true,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                'simplenavi:simplenavi',
                'simplenavi:foo',
                'simplenavi:namespace1:start',
                'simplenavi:namespace12:start',
                'simplenavi:namespace123:namespace123',
                'simplenavi:namespace123:deep:start',
                'simplenavi:namespace123:deep:foo',
                'simplenavi:namespace123:foo',
                'simplenavi:namespace2',
                'simplenavi:namespace21:start',
            ]
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSorting($set, $titlesort, $natsort, $nsfirst, $home, $current, $expect)
    {
        $simpleNavi = new \syntax_plugin_simplenavi();
        $items = $simpleNavi->getSortedItems('simplenavi', $current, $titlesort, $natsort, $nsfirst, $home);
        $this->assertSame($expect, array_column($items, 'id'), $set);
    }

}



