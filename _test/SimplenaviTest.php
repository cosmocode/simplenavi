<?php

namespace dokuwiki\plugin\simplenavi\test;

use dokuwiki\TreeBuilder\PageTreeBuilder;
use DokuWikiTest;

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
            'sort' => 'id',
            'usetitle' => false,
            'home' => false,
            'current' => 'simplenavi:page',
            'expect' => [
                '+simplenavi:foo',
                '+simplenavi:namespace1:start',
                '+simplenavi:namespace12:start',
                '+simplenavi:namespace123:namespace123',
                '+simplenavi:namespace2',
                '+simplenavi:namespace21:start',
            ]
        ];

        yield [
            'set' => 'by ID, branch open',
            'sort' => 'id',
            'usetitle' => false,
            'home' => false,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                '+simplenavi:foo',
                '+simplenavi:namespace1:start',
                '+simplenavi:namespace12:start',
                '+simplenavi:namespace123:namespace123',
                '++simplenavi:namespace123:deep:start',
                '+++simplenavi:namespace123:deep:foo',
                '++simplenavi:namespace123:foo',
                '+simplenavi:namespace2',
                '+simplenavi:namespace21:start',
            ]
        ];


        yield [
            'set' => 'by Title, Natural Search, all branches closed',
            'sort' => 'title',
            'usetitle' => true,
            'home' => false,
            'current' => 'simplenavi:page',
            'expect' => [
                '+simplenavi:namespace123:namespace123',
                '+simplenavi:foo',
                '+simplenavi:namespace2',
                '+simplenavi:namespace12:start',
                '+simplenavi:namespace21:start',
                '+simplenavi:namespace1:start',
            ]
        ];

        yield [
            'set' => 'by Title, Natural Sort, branch open',
            'sort' => 'title',
            'usetitle' => true,
            'home' => false,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                '+simplenavi:namespace123:namespace123',
                '++simplenavi:namespace123:deep:start',
                '+++simplenavi:namespace123:deep:foo',
                '++simplenavi:namespace123:foo',
                '+simplenavi:foo',
                '+simplenavi:namespace2',
                '+simplenavi:namespace12:start',
                '+simplenavi:namespace21:start',
                '+simplenavi:namespace1:start',
            ]
        ];

        yield [
            'set' => 'by Title, Natural Sort, NS first, branch open',
            'sort' => 'ns_title',
            'usetitle' => true,
            'home' => false,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                '+simplenavi:namespace123:namespace123',
                '++simplenavi:namespace123:deep:start',
                '+++simplenavi:namespace123:deep:foo',
                '++simplenavi:namespace123:foo',
                '+simplenavi:namespace2',
                '+simplenavi:namespace12:start',
                '+simplenavi:namespace21:start',
                '+simplenavi:namespace1:start',
                '+simplenavi:foo',
            ]
        ];

        yield [
            'set' => 'by ID, branch open with home level',
            'sort' => 'id',
            'usetitle' => false,
            'home' => true,
            'current' => 'simplenavi:namespace123:deep:foo',
            'expect' => [
                '+simplenavi:simplenavi',
                '++simplenavi:foo',
                '++simplenavi:namespace1:start',
                '++simplenavi:namespace12:start',
                '++simplenavi:namespace123:namespace123',
                '+++simplenavi:namespace123:deep:start',
                '++++simplenavi:namespace123:deep:foo',
                '+++simplenavi:namespace123:foo',
                '++simplenavi:namespace2',
                '++simplenavi:namespace21:start',
            ]
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSorting($set, $sort, $usetitle, $home, $current, $expect)
    {
        $simpleNavi = new \syntax_plugin_simplenavi();

        $simpleNavi->initState('simplenavi', $current, $usetitle, $sort, $home);

        /** @var PageTreeBuilder $tree */
        $tree = $this->callInaccessibleMethod($simpleNavi, 'getTree', []);


        $this->assertSame(join("\n", $expect), (string) $tree, $set);
    }

    /**
     * Test the isParent method
     */
    public function testIsParent(): void
    {
        $simpleNavi = new \syntax_plugin_simplenavi();
        
        // Test cases where parent is a parent of child
        $this->assertTrue($this->callInaccessibleMethod($simpleNavi, 'isParent', ['namespace1:namespace2:page', 'namespace1']));
        $this->assertTrue($this->callInaccessibleMethod($simpleNavi, 'isParent', ['namespace1:namespace2:page', 'namespace1:namespace2']));
        $this->assertTrue($this->callInaccessibleMethod($simpleNavi, 'isParent', ['namespace1:page', 'namespace1']));
        
        // Test cases where parent is not a parent of child
        $this->assertFalse($this->callInaccessibleMethod($simpleNavi, 'isParent', ['namespace1:page', 'namespace2']));
        $this->assertFalse($this->callInaccessibleMethod($simpleNavi, 'isParent', ['namespace1:namespace2:page', 'namespace1:namespace3']));
        
        // Test edge cases
        $this->assertTrue($this->callInaccessibleMethod($simpleNavi, 'isParent', ['page', ''])); // Empty parent is parent of all
        $this->assertTrue($this->callInaccessibleMethod($simpleNavi, 'isParent', ['page', 'page'])); // Page is parent of itself
    }

}



