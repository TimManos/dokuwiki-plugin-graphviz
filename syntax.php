<?php
/**
 * graphviz-Plugin: Parses graphviz-blocks
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Carl-Christian Salvesen <calle@ioslo.net>
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
require_once(DOKU_INC.'inc/init.php');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_graphviz extends DokuWiki_Syntax_Plugin {

    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'normal';
    }

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }

    /**
     * Where to sort in?
     */
    function getSort(){
        return 100;
    }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<graphviz.*?>\n.*?\n</graphviz>',$mode,'plugin_graphviz');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {
        $info = $this->getInfo();

        // prepare default data
        $return = array(
                        'data'      => '',
                        'width'     => 0,
                        'height'    => 0,
                        'layout'    => 'dot',
                        'align'     => '',
                        'version'   => $info['date'], //force rebuild of images on update
                       );

        // prepare input
        $lines = explode("\n",$match);
        $conf = array_shift($lines);
        array_pop($lines);

        // match config options
        if(preg_match('/\b(left|center|right)\b/i',$conf,$match)) $return['align'] = $match[1];
        if(preg_match('/\b(\d+)x(\d+)\b/',$conf,$match)){
            $return['width']  = $match[1];
            $return['height'] = $match[2];
        }
        if(preg_match('/\b(dot|neato|twopi|circo|fdp)\b/i',$conf,$match)){
            $return['layout'] = strtolower($match[1]);
        }
        if(preg_match('/\bwidth=([0-9]+)\b/i', $conf,$match)) $return['width'] = $match[1];
        if(preg_match('/\bheight=([0-9]+)\b/i', $conf,$match)) $return['height'] = $match[1];

        $return['data'] = join("\n",$lines);

        return $return;
    }

    /**
     * Create output
     *
     * @todo latex support?
     */
    function render($format, &$R, $data) {
      #$R->doc .= '<pre>'.print_r($data, true).'</pre>';
      if($format == 'xhtml'){
          if (preg_match('/url/i', $data['data'])) {
            $mapID="dokuwiki_image_map".rand();
            $temp = tempnam($conf['tmpdir'],'graphviz_');
            io_saveFile($temp,$data['data']);
            $cmd  =  $this->getConf('path');
            $cmd .= ' -Tcmapx '.escapeshellarg($temp);
            $map = shell_exec($cmd);
            preg_match('/<map[[:blank:]]id=["\']([[:alnum:]_-])["\']/', $map, $matches);
            #$R->doc .= '<pre>'.print_r($matches, true).'</pre>';
            $mapID=trim($matches[1]);
            $R->doc .="\n$map";
            @unlink($temp);
          }
          $img = $this->_imgurl($data);
          $R->doc .= '<img src="'.$img.'" class="media'.$data['align'].'" alt=""';
          if($data['width'])  $R->doc .= ' width="'.$data['width'].'"';
          if($data['height']) $R->doc .= ' height="'.$data['height'].'"';
          if($data['align'] == 'right') $ret .= ' align="right"';
          if($data['align'] == 'left')  $ret .= ' align="left"';
          if(isset($mapID)) $R->doc .= " usemap=\"#$mapID\"";
          $R->doc .= '/>';

          return true;
      }elseif($format == 'odt'){
          $src = $this->_imgfile($data);
          $R->_odtAddImage($src,$data['width'],$data['height'],$data['align']);
          $R->doc .print_r($data, true);
          return true;
      }
      return false;
    }

    /**
     * Build the image URL using either our own generator or
     * the Google Chart API
     */
    function _imgurl($data){
        if($this->getConf('path')){
            // run graphviz on our own server
            $img = DOKU_BASE.'lib/plugins/graphviz/img.php?'.buildURLparams($data,'&');
        }else{
            // go through google
            $pass = array(
                'cht' => 'gv:'.$data['layout'],
                'chl' => $data['data'],
            );
            if($data['width'] && $data['height']){
                 $pass['chs'] = $data['width'].'x'.$data['height'];
            }

            $img = 'http://chart.apis.google.com/chart?'.buildURLparams($pass,'&');
            $img = ml($img,array('w'=>$data['width'],'h'=>$data['height']));
        }
        return $img;
    }

    /**
     * Return path to created graphviz graph (local only)
     */
    function _imgfile($data){
        $w = (int) $data['width'];
        $h = (int) $data['height'];
        unset($data['width']);
        unset($data['height']);
        unset($data['align']);

        $cache = getcachename(join('x',array_values($data)),'graphviz.png');

        // create the file if needed
        if(!file_exists($cache)){
            $this->_run($data,$cache);
            clearstatcache();
        }

        // resized version
        if($w) $cache = media_resize_image($cache,'png',$w,$h);

        return $cache;
    }

    /**
     * Run the graphviz program
     */
    function _run($data,$cache) {
        global $conf;

        $temp = tempnam($conf['tmpdir'],'graphviz_');
        io_saveFile($temp,$data['data']);

        $cmd  = $this->getConf('path');
        $cmd .= ' -Tpng';
        $cmd .= ' -K'.$data['layout'];
        $cmd .= ' -o'.escapeshellarg($cache); //output
        $cmd .= ' '.escapeshellarg($temp); //input

        exec($cmd, $output, $error);
        @unlink($temp);

        if ($error != 0){
            if($conf['debug']){
                dbglog(join("\n",$output),'graphviz command failed: '.$cmd);
            }
            return false;
        }
        return true;
    }

}



