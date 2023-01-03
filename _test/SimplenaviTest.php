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
        saveWikiText('namespace1:foo', 'bar', 'foobar');
        saveWikiText('namespace2:foo', 'bar', 'foobar');
        saveWikiText('namespace12:foo', 'bar', 'foobar');
        saveWikiText('namespace123:foo', 'bar', 'foobar');
        saveWikiText('namespace21:foo', 'bar', 'foobar');

    }

    /**
     * @covers syntax_plugin_simplenavi
     */
    public function testOutputNatural()
    {
        global $ID, $conf;
        $conf['plugin']['simplenavi']['sort'] = 'natural';

        $ID = 'wiki:start';
        $request = new TestRequest();
        $input = [
            'id' => 'namespace1:foo',
        ];
        saveWikiText('wiki:start', 'some text', 'Test init');
        $response = $request->post($input);
        $naviBegin = strpos($response->getContent(), '<!-- ********** ASIDE ********** -->') + 36;
        $naviEnd = strpos($response->getContent(), '<!-- /aside -->');
        $navi = substr($response->getContent(), $naviBegin, $naviEnd - $naviBegin);
        $navilines = explode("\n", $navi);
        $listlines = [];
        foreach ($navilines as $line) {
            if (substr($line, 0, 4) != '<li ') continue;
            if (strpos($line, 'namespace') === false) continue;
            $listlines[] = $line;
        }

        $this->assertTrue(
            strpos($listlines[0], 'href="/./doku.php?id=namespace1:start"') !== false,
            'namespace1 should be before other namespaces and espacially before its subpages and namespaces'
        );
        $this->assertTrue(
            strpos($listlines[1], 'href="/./doku.php?id=namespace1:foo"') !== false,
            'level2 should follow open level1'
        );
        $this->assertTrue(
            strpos($listlines[2], 'href="/./doku.php?id=namespace2:start"') !== false,
            'namespace2 should be after namespace1 and its pages.'
        );
        $this->assertTrue(
            strpos($listlines[3], 'href="/./doku.php?id=namespace12:start"') !== false,
            'namespace12 should be after namespace2.'
        );
        $this->assertTrue(
            strpos($listlines[4], 'href="/./doku.php?id=namespace21:start"') !== false,
            'namespace21 should be after namespace12.'
        );
        $this->assertTrue(
            strpos($listlines[5], 'href="/./doku.php?id=namespace123:start"') !== false,
            'namespace123 should be after namespace21.'
        );
    }

    /**
     * @covers syntax_plugin_simplenavi
     */
    public function testOutputAscii()
    {
        global $ID, $conf;
        $conf['plugin']['simplenavi']['sort'] = 'ascii';

        $ID = 'wiki:start';
        $request = new TestRequest();
        $input = [
            'id' => 'namespace1:foo',
        ];
        saveWikiText('wiki:start', 'some text', 'Test init');
        $response = $request->post($input);
        $naviBegin = strpos($response->getContent(), '<!-- ********** ASIDE ********** -->') + 36;
        $naviEnd = strpos($response->getContent(), '<!-- /aside -->');
        $navi = substr($response->getContent(), $naviBegin, $naviEnd - $naviBegin);
        $navilines = explode("\n", $navi);
        $listlines = [];
        foreach ($navilines as $line) {
            if (substr($line, 0, 4) != '<li ') continue;
            if (strpos($line, 'namespace') === false) continue;
            $listlines[] = $line;
        }

        $this->assertTrue(
            strpos($listlines[0], 'href="/./doku.php?id=namespace1:start"') !== false,
            'namespace1 should be before other namespaces and espacially before its subpages and namespaces'
        );
        $this->assertTrue(
            strpos($listlines[1], 'href="/./doku.php?id=namespace1:foo"') !== false,
            'level2 should follow open level1.'
        );
        $this->assertTrue(
            strpos($listlines[2], 'href="/./doku.php?id=namespace12:start"') !== false,
            'namespace12 should be after namespace1 and its pages.'
        );
        $this->assertTrue(
            strpos($listlines[3], 'href="/./doku.php?id=namespace123:start"') !== false,
            'namespace123 should be after namespace12.'
        );
        $this->assertTrue(strpos(
                $listlines[4], 'href="/./doku.php?id=namespace2:start"') !== false,
            'namespace2 should be after namespace123.'
        );
        $this->assertTrue(
            strpos($listlines[5], 'href="/./doku.php?id=namespace21:start"') !== false,
            'namespace21 should be after namespace2.'
        );
    }

}



