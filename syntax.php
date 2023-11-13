<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\File\PageResolver;
use dokuwiki\Utf8\Sort;

/**
 * DokuWiki Plugin simplenavi (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class syntax_plugin_simplenavi extends SyntaxPlugin
{
    private $startpages = [];

    /** @inheritdoc */
    public function getType()
    {
        return 'substition';
    }

    /** @inheritdoc */
    public function getPType()
    {
        return 'block';
    }

    /** @inheritdoc */
    public function getSort()
    {
        return 155;
    }

    /** @inheritdoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('{{simplenavi>[^}]*}}', $mode, 'plugin_simplenavi');
    }

    /** @inheritdoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        return explode(' ', substr($match, 13, -2));
    }

    /** @inheritdoc */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format != 'xhtml') return false;

        global $INFO;
        $renderer->nocache();

        // first data is namespace, rest is options
        $ns = array_shift($data);
        if ($ns && $ns[0] === '.') {
            // resolve relative to current page
            $ns = getNS((new PageResolver($INFO['id']))->resolveId("$ns:xxx"));
        } else {
            $ns = cleanID($ns);
        }

        $items = $this->getSortedItems(
            $ns,
            $INFO['id'],
            $this->getConf('usetitle'),
            $this->getConf('natsort'),
            $this->getConf('nsfirst'),
            in_array('home', $data)
        );

        $class = 'plugin__simplenavi';
        if (in_array('filter', $data)) $class .= ' plugin__simplenavi_filter';

        $renderer->doc .= '<div class="' . $class . '">';
        $renderer->doc .= html_buildlist($items, 'idx', [$this, 'cbList'], [$this, 'cbListItem']);
        $renderer->doc .= '</div>';

        return true;
    }

    /**
     * Fetch the items to display
     *
     * This returns a flat list suitable for html_buildlist()
     *
     * @param string $ns the namespace to search in
     * @param string $current the current page, the tree will be expanded to this
     * @param bool $useTitle Sort by the title instead of the ID?
     * @param bool $useNatSort Use natural sorting or just sort by ASCII?
     * @param bool $nsFirst Sort namespaces before pages?
     * @param bool $home Add namespace's start page as top level item?
     * @return array
     */
    public function getSortedItems($ns, $current, $useTitle, $useNatSort, $nsFirst, $home)
    {
        global $conf;

        // convert to path
        $nspath = utf8_encodeFN(str_replace(':', '/', $ns));

        // get the start page of the main namespace, this adds it to the list of seen pages in $this->startpages
        // and will skip it by default in the search callback
        $startPage = $this->getMainStartPage($ns, $useTitle);

        $items = [];
        if ($home) {
            // when home is requested, add the start page as top level item
            $items[$startPage['id']] = $startPage;
            $minlevel = 0;
        } else {
            $minlevel = 1;
        }

        // execute search using our own callback
        search(
            $items,
            $conf['datadir'],
            [$this, 'cbSearch'],
            [
                'currentID' => $current,
                'usetitle' => $useTitle,
            ],
            $nspath,
            1,
            '' // no sorting, we do ourselves
        );
        if (!$items) return [];

        // split into separate levels
        $parents = [];
        $levels = [];
        $curLevel = $minlevel;
        foreach ($items as $idx => $item) {
            if ($curLevel < $item['level']) {
                // previous item was the parent
                $parents[] = array_key_last($levels[$curLevel]);
            }
            $curLevel = $item['level'];
            $levels[$item['level']][$idx] = $item;
        }

        // sort each level separately
        foreach ($levels as $level => $items) {
            uasort($items, fn($a, $b) => $this->itemComparator($a, $b, $useNatSort, $nsFirst));
            $levels[$level] = $items;
        }

        // merge levels into a flat list again
        $levels = array_reverse($levels, true);
        foreach (array_keys($levels) as $level) {
            if ($level == $minlevel) break;

            $parent = array_pop($parents);
            $pos = array_search($parent, array_keys($levels[$level - 1])) + 1;

            /** @noinspection PhpArrayAccessCanBeReplacedWithForeachValueInspection */
            $levels[$level - 1] = array_slice($levels[$level - 1], 0, $pos, true) +
                $levels[$level] +
                array_slice($levels[$level - 1], $pos, null, true);
        }

        return $levels[$minlevel];
    }

    /**
     * Compare two items
     *
     * @param array $a
     * @param array $b
     * @param bool $useNatSort
     * @param bool $nsFirst
     * @return int
     */
    public function itemComparator($a, $b, $useNatSort, $nsFirst)
    {
        if ($nsFirst && $a['type'] != $b['type']) {
            return $a['type'] == 'd' ? -1 : 1;
        }

        if ($useNatSort) {
            return Sort::strcmp($a['title'], $b['title']);
        } else {
            return strcmp($a['title'], $b['title']);
        }
    }


    /**
     * Create a list openening
     *
     * @param array $item
     * @return string
     * @see html_buildlist()
     */
    public function cbList($item)
    {
        global $INFO;

        if (($item['type'] == 'd' && $item['open']) || $INFO['id'] == $item['id']) {
            return '<strong>' . html_wikilink(':' . $item['id'], $item['title']) . '</strong>';
        } else {
            return html_wikilink(':' . $item['id'], $item['title']);
        }
    }

    /**
     * Create a list item
     *
     * @param array $item
     * @return string
     * @see html_buildlist()
     */
    public function cbListItem($item)
    {
        if ($item['type'] == "f") {
            return '<li class="level' . $item['level'] . '">';
        } elseif ($item['open']) {
            return '<li class="open">';
        } else {
            return '<li class="closed">';
        }
    }

    /**
     * Custom search callback
     *
     * @param $data
     * @param $base
     * @param $file
     * @param $type
     * @param $lvl
     * @param array $opts - currentID is the currently shown page
     * @return bool
     */
    public function cbSearch(&$data, $base, $file, $type, $lvl, $opts)
    {
        global $conf;
        $return = true;

        $id = pathID($file);

        if (
            $type == 'd' && !(
                preg_match('#^' . $id . '(:|$)#', $opts['currentID']) ||
                preg_match('#^' . $id . '(:|$)#', getNS($opts['currentID']))

            )
        ) {
            //add but don't recurse
            $return = false;
        } elseif ($type == 'f' && (!empty($opts['nofiles']) || substr($file, -4) != '.txt')) {
            //don't add
            return false;
        }

        // for sneaky index, check access to the namespace's start page
        if ($type == 'd' && $conf['sneaky_index']) {
            $sp = (new PageResolver(''))->resolveId($id . ':');
            if (auth_quickaclcheck($sp) < AUTH_READ) {
                return false;
            }
        }

        if ($type == 'd') {
            // link directories to their start pages
            $original = $id;
            $id = "$id:";
            $id = (new PageResolver(''))->resolveId($id);
            $this->startpages[$id] = 1;

            // if the resolve id is in the same namespace as the original it's a start page named like the dir
            if (getNS($original) === getNS($id)) {
                $useNS = $original;
            }
        } elseif (!empty($this->startpages[$id])) {
            // skip already shown start pages
            return false;
        }

        //check hidden
        if (isHiddenPage($id)) {
            return false;
        }

        //check ACL
        if ($type == 'f' && auth_quickaclcheck($id) < AUTH_READ) {
            return false;
        }

        $data[$id] = [
            'id' => $id,
            'type' => $type,
            'level' => $lvl,
            'open' => $return,
            'title' => $this->getTitle($id, $opts['usetitle']),
            'ns' => $useNS ?? (string)getNS($id),
        ];

        return $return;
    }

    /**
     * @param string $id
     * @param bool $useTitle
     * @return array
     */
    protected function getMainStartPage($ns, $useTitle)
    {
        $resolver = new PageResolver('');
        $id = $resolver->resolveId($ns . ':');

        $item = [
            'id' => $id,
            'type' => 'd',
            'level' => 0,
            'open' => true,
            'title' => $this->getTitle($id, $useTitle),
            'ns' => $ns,
        ];
        $this->startpages[$id] = 1;
        return $item;
    }

    /**
     * Get the title for the given page ID
     *
     * @param string $id
     * @param bool $usetitle - use the first heading as title
     * @return string
     */
    protected function getTitle($id, $usetitle)
    {
        global $conf;

        if ($usetitle) {
            $p = p_get_first_heading($id);
            if (!empty($p)) return $p;
        }

        $p = noNS($id);
        if ($p == $conf['start'] || !$p) {
            $p = noNS(getNS($id));
            if (!$p) {
                return $conf['start'];
            }
        }
        return $p;
    }
}
