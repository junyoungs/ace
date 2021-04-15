<?php namespace CORE;
/**
 * Output
 * Responsible for sending final output to browser
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\CORE
 *
 */


class Compression
{
    protected $compressCss     = TRUE;
    protected $compressJs      = FALSE;
    protected $removeComments  = TRUE;

    protected $html;

    public function __construct($html)
    {
        if (!empty($html))
        {
            $this->parseHTML($html);
        }
    }


    public function __toString()
    {
        return $this->html;
    }

    protected function minifyHTML($html)
    {
        $pattern = '/<(?<script>script).*?<\/script\s*>|<(?<style>style).*?<\/style\s*>|<!(?<comment>--).*?-->|<(?<tag>[\/\w.:-]*)(?:".*?"|\'.*?\'|[^\'">]+)*>|(?<text>((<[^!\/\w.:-])?[^<]*)+)|/si';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        $overriding = FALSE;
        $rawTag    = FALSE;
        $html       = '';

        foreach ($matches as $token) {
            $tag = (isset($token['tag'])) ? strtolower($token['tag']) : NULL;
            $content = $token[0];
            if (is_null($tag))
            {
                if ( !empty($token['script']) )
                {
                    $strip = $this->compressJs;
                }
                else if ( !empty($token['style']) )
                {
                    $strip = $this->compressCss;
                }
                else if ($content == '<!-- no compression -->')
                {
                    $overriding = !$overriding;
                    continue;
                }
                else if ($this->removeComments)
                {
                    if (!$overriding && $rawTag != 'textarea')
                    {
                        $content = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $content);
                    }
                }
            }
            else
            {
                if ($tag == 'pre' || $tag == 'textarea')
                {
                    $rawTag = $tag;
                }
                else if ($tag == '/pre' || $tag == '/textarea')
                {
                    $rawTag = FALSE;
                }
                else {
                    if ($rawTag || $overriding)
                    {
                        $strip = FALSE;
                    }
                    else
                    {
                        $strip   = TRUE;
                        $content = preg_replace('/(\s+)(\w++(?<!\baction|\balt|\bcontent|\bsrc)="")/', '$1', $content);
                        $content = str_replace(' />', '/>', $content);
                    }
                }
            }
            if ($strip)
            {
                $content = $this->removeWhiteSpace($content);
            }
            $html .= $content;
        }
        return $html;
    }


    public function parseHTML($html)
    {
        $this->html = $this->minifyHTML($html);
    }


    protected function removeWhiteSpace($str)
    {
        $str = str_replace("\t", ' ', $str);
        $str = str_replace("\n",  '', $str);
        $str = str_replace("\r",  '', $str);
        while (stristr($str, '  '))
        {
            $str = str_replace('  ', ' ', $str);
        }
        return $str;
    }
}
// ------------------------------------------------------------------------
class Output {

    protected $_headers         = [];           // Headers
    protected $_globalvars      = [];           // Global Variables
    protected $_vars            = [];           // Variables
    protected $_view            = '/';          // View Path
    protected $_layout          = '/';          // Layout Path
    protected $_output          = '';
    protected $_expire          = 0;            // Cache expiration time

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        //$this->_zlib = @ini_get('zlib.output_compression');

        $this->hasCache();
        $this->__setGlobalAssign();

        \BOOT\Log::w('INFO', '\\CORE\\Output class initialized.');
    }
    // --------------------------------------------------------------------

    /**
     * html compress
     *
     * @param string $data
     * @return string
     */
    public function compress($html)
    {
        return trim($html);
        
        if( ! \BOOT\Config::get('compress') ) return $html;
         
        return new Compression($html);

        $html = (string)$html;

        // Replace multiple spaces with a single space
        //$html = preg_replace('/(\s+)/mu', ' ', $html);

        // Remove spaces that are followed by either > or <
        $html = preg_replace('/ (>)/', '$1', $html);

        // Remove spaces that are preceded by either > or <
        $html = preg_replace('/(<) /', '$1', $html);

        // Remove spaces that are between > and <
        $html = preg_replace('/(>) (<)/', '>$2', $html);


        // Replace newlines, returns and tabs with spaces
        $html = str_replace(["\t", "\r"], ' ', $html);

        $html = preg_replace('/(<script.*?<\/script>)/ms', '$1', $html);

        // Remove HTML comments...
        $html = preg_replace('/(<!--.*?-->)/ms', '', $html);
        $html = str_replace('<!>', '', $html);

        // Can break layouts that are dependent on whitespace between tags
        $html = str_replace('  ', ' ', $html);

        // Remove the trailing \n
        return trim($html);
    }


    // --------------------------------------------------------------------
    /**
    * set layout
    * @param string $layout
    * @return \CORE\Output
    */
    public function setLayout($layout=NULL)
    {
        $layout = is_null($layout) ? 'default' : $layout;
        $this->_layout = HOSTPATH.DIRECTORY_SEPARATOR.'layout'.DIRECTORY_SEPARATOR.$layout.'.layout.php';
        if( ! file_exists($this->_layout) )
        {
            \BOOT\Log::w('ERROR', 'Do not found layout file: '.$this->_layout);
        }
        return $this;
    }

    /**
     * [getLayout description]
     * @return [type] [description]
     */
    public function getLayout()
    {
        return $this->_layout;
    }

    /**
     * set view
     * @return \CORE\Output
     */
    public function setView($view = "")
    {
        // set view file
        if($view == '')
        {
            $this->_view = Core::get('Router')->getPath().DIRECTORY_SEPARATOR.Core::get('Router')->getMethod().'.view.php';
        }
        else
        {
            $this->_view = HOSTPATH.DIRECTORY_SEPARATOR.'control'.$view.".view.php";
        }
        if( ! file_exists($this->_view) )
        {
            \BOOT\Log::w('ERROR', 'Do not found view file: '.$this->_view);
        }
        return $this;
    }


    /**
     * Assign Global Vars
     *
     * @return \CORE\Output
     */
    private function __setGlobalAssign()
    {
        $this->_globalvars['path'] = Core::get('Router')->getPath();
    }

    /**
     * Assign Vars
     *
     * @param array $vars
     * @return \CORE\Output
     */
    public function setAssign($vars)
    {
        $vars = (array)$vars;
        if( count($vars) > 0)
        {
            // 			$this->_vars = $vars;
            $this->_vars = array_merge($this->_vars, $vars);
            // 			extract($this->_vars);
            extract($vars);
        }
        return $this;
    }


    /**
     * fetch file
     *
     * @param string $file
     * @return string
     */
    protected function _fetch($file)
    {
        if( file_exists($file) )
        {
            // vars
            $tmp = array_merge($this->_globalvars, $this->_vars);
            if( count($tmp) > 0)
            {
                foreach($tmp as $k => $v)
                {
                    $$k = $v;
                }
            }

            // Buffer the output
            ob_start();
            set_error_handler(function($errno, $errstr, $file, $errline){
                \BOOT\Log::w('ERROR', $errstr.' - '.$file. ' - '. $errline);
            });
                @include($file);
                restore_error_handler();

                \BOOT\Log::w('INFO', 'File loaded: '.$file);

                $tmp = ob_get_contents();
                @ob_end_clean();

                return $tmp;
        }
    }


    /**
     * draw
     *
     * @return void
     */
    public function draw()
    {
        $this->_vars['view'] = $this->_fetch($this->_view);
        $this->_output = $this->_fetch($this->_layout);

        //header
        // 나중에 추가

        // using cache
        if($this->_expire > 0)
        {
            $this->__writeCache();
        }

        echo $this->compress($this->_output);
    }

    /**
     * fetch
     *
     * @return void
     */
    public function fetch()
    {
        return $this->_fetch($this->_view);
    }

    // --------------------------------------------------------------------


    /**
     * set cache expire time
     *
     * @param integer $time
     * @return \CORE\Output
     */
    public function setCache($time)
    {
        $time = (int)$time;
        $this->_expire = $time > 0 ? time() + (int)$time * 60 : 0;

        // if( MODE == 'development' )
        // {
        //     $this->_expire = 0;
        // }

        return $this;
    }



    /**
     * write cache
     *
     * @return boolean
     */
    private function __writeCache()
    {
        $key	= $this->__cacheKey();
        $path	= $this->__cachePath($key);
        $file	= $this->__cacheFile($path, $key);

        if( is_null($file) )
        {
            makeDir($path);

            if ( is_dir($path) && ! is_writable($path))
            {
                \BOOT\Log::w('ERROR', '\\CORE\\Output - Unable to write cache file.');
                return FALSE;
            }

            $full = $path.DIRECTORY_SEPARATOR.$key.'.'.$this->_expire;

            if ( ! $fp = @fopen($full, 'wb'))
            {
                \BOOT\Log::w('ERROR', '\\CORE\\Output - Unable to write cache file: '.$full);
                return FALSE;
            }

            if (flock($fp, LOCK_EX))
            {
                fwrite($fp, $this->_output);
                flock($fp, LOCK_UN);
            }
            else
            {
                \BOOT\Log::w('ERROR', '\\CORE\\Output - Unable to secure a file lock for file at: '.$full);
                return FALSE;
            }
            fclose($fp);
            @chmod($full, 0666);

            return TRUE;
        }
    }



    /**
     * has cache
     *
     * @return void
     */
    public function hasCache()
    {
        $key	= $this->__cacheKey();
        $path	= $this->__cachePath($key);
        $file	= $this->__cacheFile($path, $key);

        if( ! is_null($file) )
        {
            $full = $path.DIRECTORY_SEPARATOR.$file;

            if( is_readable($full) )
            {
                echo @file_get_contents($full);
                \BOOT\Log::w('INFO', '\\CORE\\Output - Using Cache: '.$full);
                exit;
            }
        }
    }



    /**
     * cache key
     *
     * @return string
     */
    private function __cacheKey()
    {
        $request = Core::get('Input')->request();
        $params = [];
        foreach($request as $k => $v)
        {
            $k = strtolower(trim($k));
            if(is_array($v)) ksort($v);
            $params[$k] = $v;
        }
        //extract($params);
        ksort($params);
        return hash('sha256', Core::get('Router')->uri.'?'.serialize($params));
    }


    /**
     * cache path
     *
     * @param string $key
     * @return string
     */
    private function __cachePath($key)
    {
        $path = _CACHEPATH.DIRECTORY_SEPARATOR.'output';
        // if(defined("COUNTRY"))
        // {
        //     $path .= DIRECTORY_SEPARATOR.strtolower(COUNTRY);
        // }

        $cache_path = Core::get('Session')->get(\BOOT\Config::get('session')['cookie_name'].'_'.HOST.'_cache');
        if(! empty($cache_path))
        {
            $path .= DIRECTORY_SEPARATOR.strtolower(trim($cache_path['value'], DIRECTORY_SEPARATOR));
        }

        return $path.DIRECTORY_SEPARATOR.substr($key, 0, 2).DIRECTORY_SEPARATOR.substr($key, 2, 2);
    }

    /**
     * cache file
     *
     * @param string $path
     * @param string $key
     * @return NULL|string
     */
    private function __cacheFile($path, $key)
    {
        if ( ! is_dir($path) )
        {
            return NULL;
        }

        $files = scandir($path);
        foreach($files as $f)
        {
            $f = strtolower(trim($f));
            if(substr($f, 0, 64) == $key)	//as like
            {
                $ext = explode('.', $f);
                $expire = (int)array_pop($ext);
                if($expire > time())
                {
                    return $f;
                }
                else
                {
                    if (@is_writable($path.DIRECTORY_SEPARATOR.$f))
                    {
                        @unlink($path.DIRECTORY_SEPARATOR.$f);
                    }
                }
            }
        }
        return NULL;
    }






}
// END Output Class

/* End of file output.php */
/* Location: ./core/output.php */