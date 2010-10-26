<?php
/**
 * graphviz-Plugin: Parses graphviz-blocks
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Carl-Christian Salvesen <calle@ioslo.net>
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Tim Manos <tim.manos@web.de>
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
    global $conf;
    $imageType = 'png'; $imageExt = '.'.$imageType;
    $mapID=md5($data['data']);
    # DILEMA: what's better to use as a path to store the generated graphviz images:
    # the "chachedir" folder or the "mediadir"
    # $file_base_name = getcachename(join('x',array_values($data)),'_graphviz');
    $file_base_name = $conf['mediadir'].'/graphviz_'.$mapID;

    if (!(file_exists($fname.$imageExt) or file_exists($file_base_name.'.map'))) {
      # TODO: use http://chart.apis.google.com/chart if dot is not installed locally
//         $pass = array(
//             'cht' => 'gv:'.$data['layout'],
//             'chl' => $data['data'],
//         );
//         if($data['width'] && $data['height']){
//              $pass['chs'] = $data['width'].'x'.$data['height'];
//         }
//
//         $img = 'http://chart.apis.google.com/chart?'.buildURLparams($pass,'&');
//         $img = ml($img,array('w'=>$data['width'],'h'=>$data['height']));

      io_saveFile($file_base_name.'.dot',$data['data']);
      $dotExe=$this->getConf('path');
      $cmdDoMap = $dotExe.' -Tcmapx '.escapeshellarg($file_base_name.'.dot').' -o'.escapeshellarg($file_base_name.'.map');
      $cmdDoImg = $dotExe.' -T'.$imageType.' '.escapeshellarg($file_base_name.'.dot').' -o'.escapeshellarg($file_base_name.$imageExt);
      $ret = `{$cmdDoMap}`;
      $ret = `{$cmdDoImg}`;
    }
    #$this->dump_array($conf);
    if ($format == 'xhtml') {
      // display the image tag
      $src=dirname($_SERVER['PHP_SELF']).substr($file_base_name.$imageExt, strpos($file_base_name.$imageExt, '/data'));
      $R->doc .= '<img src="'.$src.'" class="media'.$data['align'].'" alt=""';
      if($data['width'])  $R->doc .= ' width="'.$data['width'].'"';
      if($data['height']) $R->doc .= ' height="'.$data['height'].'"';
      if($data['align'] == 'right') $ret .= ' align="right"';
      if($data['align'] == 'left')  $ret .= ' align="left"';
      $R->doc .= " usemap=\"#$mapID\"";
      $R->doc .= '/>';

      // display the map tag
      @$map = file_get_contents($file_base_name.'.map');
      $map=preg_replace("#<ma(.*)>#"," ",$map);
      $map=str_replace("</map>","",$map);
      $R->doc .= "<map name=\"$mapID\">{$map}</map>";
      return true;
    } elseif ($format == 'odt') {
      $R->_odtAddImage($file_base_name.$imageExt,$data['width'],$data['height'],$data['align']);
      return true;
    }
    return false;
  }

  function echox($msg) {
    $logFile="/tmp/dokuwiki_graphviz";
    error_log(strftime('%Y-%m-%d %H:%M%S')." ".$msg, 3, $logFile);
  }

  function dump_array($a) { print '<pre>'.print_r($a, true).'</pre>'; }
}



