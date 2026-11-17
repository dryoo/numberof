<?php
/**
 * DokuWiki Plugin numberof (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  S.C. Yoo <dryoo@live.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_numberof extends DokuWiki_Syntax_Plugin {
    public function getType() { return 'substition'; }
    public function getSort() { return 32; }

    public function connectTo($mode) {
      $this->Lexer->addSpecialPattern('\{\{NUMBEROF[^\}]*\}\}',$mode,'plugin_numberof');
    }

    /**
     * Only parse the tag here. Counting happens in render() so that a page
     * using ~~NOCACHE~~ always shows current numbers: render() is called again
     * on every request, while handle() only runs when the instructions are
     * rebuilt.
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){
        $match = substr($match, 10, -2);

        // accept both ">" (classic) and ":" (natural namespace separator)
        if (!preg_match('/^(PAGES|MEDIAS)\s*(?:[>:]\s*(.*))?$/us', $match, $m)) {
            return ['type' => '', 'ns' => ''];
        }

        $ns = isset($m[2]) ? trim($m[2], " \t\n\r\0\x0B:") : '';

        return ['type' => $m[1], 'ns' => $ns];
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode != 'xhtml') return false;

        // instructions cached by an older plugin version only carry the number
        if (!isset($data['type'])) {
            $renderer->doc .= (int) ($data['count'] ?? 0);
            return true;
        }

        $renderer->doc .= $this->count($data['type'], $data['ns'] ?? '');
        return true;
    }

    /**
     * Count the pages or media files, optionally limited to a namespace.
     */
    protected function count($type, $ns)
    {
        global $conf;

        static $memo = [];
        $key = $type . ':' . $ns;
        if (isset($memo[$key])) {
            return $memo[$key];
        }

        if ($type !== 'PAGES' && $type !== 'MEDIAS') {
            return $memo[$key] = 0;
        }

        $ttl = (int) $this->getConf('cache_time');
        $cache = null;
        if ($ttl > 0) {
            $cache = new \dokuwiki\Cache\Cache('numberof_' . md5($key), '.count');
            if ($cache->useCache(['age' => $ttl])) {
                return $memo[$key] = (int) $cache->retrieveCache();
            }
        }

        $list = [
            'file_count' => 0,
            'dir_count'  => 0,
            'dir_nest'   => 0,
        ];

        // normalise the namespace and encode it for the configured fnencode setting
        $rel = $ns === '' ? '' : utf8_encodeFN(str_replace(':', '/', cleanID($ns)));
        $base = ($type === 'PAGES') ? $conf['datadir'] : $conf['mediadir'];

        search($list, $base, [$this, '_search_count'], ['all' => $type === 'MEDIAS'], $rel);

        if ($cache) {
            $cache->storeCache($list['file_count']);
        }

        return $memo[$key] = $list['file_count'];
    }

    function _search_count(&$data,$base,$file,$type,$lvl,$opts){
        if($type == 'd'){
            if($data['dir_nest'] < $lvl) $data['dir_nest'] = $lvl;
            $data['dir_count']++;
            return true;
        }
        if($opts['all'] || substr($file,-4) == '.txt'){
            $data['file_count']++;
        }
        return false;
    }
}

// vim:ts=4:sw=4:et:
