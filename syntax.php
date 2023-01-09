<?php

use dokuwiki\File\PageResolver;

/**
 * DokuWiki Plugin simplenavi (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class syntax_plugin_simplenavi extends DokuWiki_Syntax_Plugin
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

        global $conf;
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
        // convert to path
        $ns = utf8_encodeFN(str_replace(':', '/', $ns));

        $items = [];
        search($items, $conf['datadir'], [$this, 'cbSearch'], ['ns' => $INFO['id']], $ns, 1, 'natural');
        if ($this->getConf('sortByTitle')) {
            $this->sortByTitle($items, "id");
        } else {
            if ($this->getConf('sort') == 'ascii') {
                uksort($items, [$this, 'pathCompare']);
            }
        }

        $class = 'plugin__simplenavi';
        if (in_array('filter', $data)) $class .= ' plugin__simplenavi_filter';

        $renderer->doc .= '<div class="' . $class . '">';
        $renderer->doc .= html_buildlist($items, 'idx', [$this, 'cbList'], [$this, 'cbListItem']);
        $renderer->doc .= '</div>';

        return true;
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
            return '<strong>' . html_wikilink(':' . $item['id'], $this->getTitle($item['id'])) . '</strong>';
        } else {
            return html_wikilink(':' . $item['id'], $this->getTitle($item['id']));
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
     * @param $opts
     * @return bool
     */
    public function cbSearch(&$data, $base, $file, $type, $lvl, $opts)
    {
        global $conf;
        $return = true;

        $id = pathID($file);

        if ($type == 'd' && !(
                preg_match('#^' . $id . '(:|$)#', $opts['ns']) ||
                preg_match('#^' . $id . '(:|$)#', getNS($opts['ns']))

            )) {
            //add but don't recurse
            $return = false;
        } elseif ($type == 'f' && (!empty($opts['nofiles']) || substr($file, -4) != '.txt')) {
            //don't add
            return false;
        }

        if ($type == 'd' && $conf['sneaky_index'] && auth_quickaclcheck($id . ':') < AUTH_READ) {
            return false;
        }

        if ($type == 'd') {
            // link directories to their start pages
            $id = "$id:";
            $id = (new PageResolver(''))->resolveId($id);
            $this->startpages[$id] = 1;
        } elseif (!empty($this->startpages[$id])) {
            // skip already shown start pages
            return false;
        } elseif (noNS($id) == $conf['start']) {
            // skip the main start page
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

        $data[$id] = array(
            'id' => $id,
            'type' => $type,
            'level' => $lvl,
            'open' => $return,
        );
        return $return;
    }

    /**
     * Get the title for the given page ID
     *
     * @param string $id
     * @return string
     */
    protected function getTitle($id)
    {
        global $conf;

        if (useHeading('navigation')) {
            $p = p_get_first_heading($id);
        }
        if (!empty($p)) return $p;

        $p = noNS($id);
        if ($p == $conf['start'] || !$p) {
            $p = noNS(getNS($id));
            if (!$p) {
                return $conf['start'];
            }
        }
        return $p;
    }

    /**
     * Custom comparator to compare IDs
     *
     * @param string $a
     * @param string $b
     * @return int
     */
    public function pathCompare($a, $b)
    {
        global $conf;
        $a = preg_replace('/' . preg_quote($conf['start'], '/') . '$/', '', $a);
        $b = preg_replace('/' . preg_quote($conf['start'], '/') . '$/', '', $b);
        $a = str_replace(':', '/', $a);
        $b = str_replace(':', '/', $b);

        return strcmp($a, $b);
    }

    /**
     * Sort items by title
     *
     * @param array[] $array a list of items
     * @param string $key the key that contains the page ID in each item
     * @return void
     */
    protected function sortByTitle(&$array, $key)
    {
        $sorter = [];
        $ret = [];
        reset($array);
        foreach ($array as $ii => $va) {
            $sorter[$ii] = $this->getTitle($va[$key]);
        }
        if ($this->getConf('sort') == 'ascii') {
            uksort($sorter, [$this, 'pathCompare']);
        } else {
            natcasesort($sorter);
        }
        foreach ($sorter as $ii => $va) {
            $ret[$ii] = $array[$ii];
        }
        $array = $ret;
    }

}
