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

    public function handle($match, $state, $pos, Doku_Handler $handler){
	global $conf;
        $list = array(
            'file_count' => 0,
            'dir_count' => 0,
            'dir_nest' => 0,
        );
        $match=substr($match,10,-2);

        // accept both ">" (classic) and ":" (natural namespace separator)
        if (!preg_match('/^(PAGES|MEDIAS)\s*(?:[>:]\s*(.*))?$/us', $match, $m)) {
            return ['count' => 0];
        }

        $type = $m[1];
        $ns   = isset($m[2]) ? trim($m[2], " \t\n\r\0\x0B:") : '';

        // normalise the namespace and encode it for the configured fnencode setting
        $rel = $ns === '' ? '' : utf8_encodeFN(str_replace(':', '/', cleanID($ns)));

        switch ($type) {
            case "PAGES":
                search($list,$conf['datadir'],array($this,'_search_count'),array('all'=>false),$rel);
                break;

            case "MEDIAS":
                search($list,$conf['mediadir'],array($this,'_search_count'),array('all'=>true),$rel);
                break;
          }
        return ['count' => $list['file_count']];
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode != 'xhtml') return false;
        $renderer->doc .= $data['count'];
        return true;
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
