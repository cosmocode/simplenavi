<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\File\PageResolver;
use dokuwiki\TreeBuilder\Node\AbstractNode;
use dokuwiki\TreeBuilder\Node\WikiNamespace;
use dokuwiki\TreeBuilder\Node\WikiStartpage;
use dokuwiki\TreeBuilder\PageTreeBuilder;
use dokuwiki\TreeBuilder\TreeSort;

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

        $tree = $this->getTree($ns);
        $this->renderTree($renderer, $tree->getTop());

        return true;
    }

    /**
     * Create the tree
     *
     * @param string $ns
     * @return PageTreeBuilder
     */
    protected function getTree(string $ns): PageTreeBuilder
    {
        $tree = new PageTreeBuilder($ns);
        $tree->addFlag(PageTreeBuilder::FLAG_NS_AS_STARTPAGE);
        $tree->addFlag(PageTreeBuilder::FLAG_SELF_TOP);
        $tree->setRecursionDecision(\Closure::fromCallable([$this, 'treeRecursionDecision']));
        $tree->setNodeProcessor(\Closure::fromCallable([$this, 'treeNodeProcessor']));
        $tree->generate();
        $tree->sort(TreeSort::SORT_BY_NS_FIRST_THEN_TITLE);
        return $tree;
    }


    /**
     * Callback for the PageTreeBuilder to decide if we want to recurse into a node
     *
     * @param AbstractNode $node
     * @param int $depth
     * @return bool
     */
    protected function treeRecursionDecision(AbstractNode $node, int $depth): bool
    {
        global $INFO;

        if ($node instanceof WikiStartpage) {
            $id = $node->getNs(); // use the namespace for startpages
        } else {
            $id = $node->getId();
        }

        $is_current = $this->isParent($INFO['id'], $id);
        $node->setProperty('is_current', $is_current);

        // always recurse into the current page path
        if ($is_current) return true;

        // FIXME for deep peek, we want to recurse until level is reached

        return false;
    }

    /**
     * Callback for the PageTreeBuilder to process a node
     *
     * @param AbstractNode $node
     * @return AbstractNode|null
     */
    protected function treeNodeProcessor(AbstractNode $node): ?AbstractNode
    {
        $usetitle = $this->getConf('usetitle');

        $perm = auth_quickaclcheck($node->getId());
        $node->setProperty('permission', $perm);
        $node->setTitle($this->getTitle($node->getId(), $usetitle));


        if ($node->hasChildren()) {
            // this node has children, we add it to the tree regardless of the permission
            // permissions are checked again when rendering
            return $node;
        }

        if ($perm < AUTH_READ) {
            // no children, no permission. No need to add it to the tree
            return null;
        }

        return $node;
    }


    /**
     * Example on how to render a TreeBuilder tree
     *
     * @param Doku_Renderer $R The current renderer
     * @param AbstractNode $top The top node of the tree (use getTop() to get it)
     * @param int $level current nesting level, starting at 1
     * @return void
     */
    protected function renderTree(Doku_Renderer $R, AbstractNode $top, $level = 1)
    {
        $R->listu_open();
        foreach ($top->getChildren() as $node) {
            $isfolder = $node instanceof WikiNamespace;
            $incurrent = $node->getProperty('is_current', false);

            $R->listitem_open(1, $isfolder);
            $R->listcontent_open();
            if ($incurrent) $R->strong_open();

            if (((int)$node->getProperty('permission', 0)) < AUTH_READ) {
                $R->cdata($node->getTitle());
            } else {
                $R->internallink($node->getId(), $node->getTitle(), null, false, 'navigation');
            }

            if ($incurrent) $R->strong_close();
            $R->listcontent_close();
            if ($node->hasChildren()) {
                $this->renderTree($R, $node, $level + 1);
            }
            $R->listitem_close();
        }
        $R->listu_close();
    }

    /**
     * Check if the given parent ID is a parent of the child ID
     *
     * @param string $child
     * @param string $parent
     * @return bool
     */
    protected function isParent(string $child, string $parent)
    {
        $child = explode(':', $child);
        $parent = explode(':', $parent);
        return array_slice($child, 0, count($parent)) === $parent;
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
