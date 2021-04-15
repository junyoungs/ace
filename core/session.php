<?php namespace CORE;

/**
 * Start Session
 *
 * Not use session, only use cookie.
 */
//session_start();


/**
 * Session
 *
 * @author        Junyoung Park
 * @copyright    Copyright (c) 2016.
 * @license        LGPL
 * @version        1.0.0
 * @namespace    \CORE
 */

// ------------------------------------------------------------------------

/**
 * Session Class
 *
 */
class Session
{
    private $__name              = 'securecookie';
    private $__domain            = '';
    private $__path              = '/';

    private $__enData            = NULL;        // encrypt data
    private $__deData            = [];          // decrypt data (array) - json_decode($value, TRUE) 처리

    private $__defaultDataName   = ['agent', 'time', 'ip'];

    /**
     * Session Constructor
     *
     * The constructor runs the session routines automatically
     * whenever the class is instantiated.
     */
    public function __construct()
    {
        // Start Session
        $this->__start();

        \BOOT\Log::w('INFO', '\\CORE\\Session class initialized.');
    }
    // --------------------------------------------------------------------

    private function __getTime($format='GMT')
    {
        return time();
    }
    // --------------------------------------------------------------------

    private function __getIp()
    {
        return Core::Get('Input')->ip();
    }

    // --------------------------------------------------------------------
    private function __checkKey($k)
    {
        return strtolower( trim( (string)$k ) );
    }



    // --------------------------------------------------------------------
    
    // public function getAll()
    // {
    //     $tmp = [];
    //     foreach($this->__deData as $k => $v)
    //     {
    //         $tmp[$k] = $this->get($k);
    //     }
    //     return $tmp;
    // }

    // --------------------------------------------------------------------

    public function get($k, $e = 0)
    {
        $k = $this->__checkKey($k);

        if($e > 0 && array_key_exists( $k,  $this->__deData ) && ! in_array($k, $this->__defaultDataName))
        {
            if(! isset($this->__deData[$k]['time']))
            {
                $this->__deData[$k]['time'] = $this->__getTime();
                $this->__deData[$k]['ip'] = $this->__getIp();
            }

            if(time() > $this->__deData[$k]['time'] + $e)
            {
                unset($this->__deData[$k]);
            }
            else
            {
                $this->__deData[$k]['time'] = $this->__getTime();
                $this->__deData[$k]['ip'] = $this->__getIp();
            }

            $this->__fetchUpdate();
        }
        
        return ( ! array_key_exists( $k,  $this->__deData ) ) ? FALSE : $this->__deData[$k];
    }


    // --------------------------------------------------------------------

    public function set($k, $v)
    {
        $k = $this->__checkKey($k);

        if( in_array($k, $this->__defaultDataName) ) return FALSE;

        $this->__deData[$k] = [
                'time'   => $this->__getTime(),
                'ip'     => $this->__getIp(),
                'value'  => $v
        ];

        $this->__fetchUpdate();
    }

    // --------------------------------------------------------------------

    public function del($k)
    {
        unset( $this->__deData[$this->__checkKey($k)] );

        $this->__fetchUpdate();
    }

    // --------------------------------------------------------------------

    /**
     * start session
     *
     * 쿠키에 기본 값 설정
     *
     * @access    public
     * @return    void
     */
    private function __start()
    {
        $this->__fetch();
    }

    // --------------------------------------------------------------------

    private function __fetch()
    {
        // copy cookies
        $tmp = $_COOKIE;
        ksort($tmp);

        // Init Encrypt Data
        $this->__enData = '';

        // 4k로 데이터가 쪼개기지 때문에 다시 붙이는 작업
        foreach($tmp as $k => $v)
        {
            $k = strtolower( trim( (string)$k ) );

            if( strlen( $k ) == 15)
            {
                if( substr( $k, 0, 12) == strtolower( trim( $this->__name ) ) )
                {
                    $this->__enData .= $v;
                }
            }
        }

        // Encrypt Data > Decyrpt Data
        if(! empty($this->__enData))
        {
            $this->__deData = unserialize( Core::get('Crypt')->de( htmlspecialchars_decode( $this->__enData ) ) );
        }

        if(empty($this->__deData))
        {
            $this->__deData = [];
        }
    }



    // --------------------------------------------------------------------

    public function __fetchUpdate()
    {
        // Default Data
        $this->__deData['agent']   = Core::get('Input')->agent();
        $this->__deData['time']    = $this->__getTime();
        $this->__deData['ip']      = $this->__getIp();

        // Encyrpt Data
        $this->__enData = htmlspecialchars( Core::get('Crypt')->en( serialize( $this->__deData ) ) );

        $this->__set_cookie();
    }

    // --------------------------------------------------------------------


    /**
     * Destroy the current session
     *
     * @access    public
     * @return    void
     */
    public function destroy()
    {
        $this->__deData = [];
        $this->__fetchUpdate();
    }

    // --------------------------------------------------------------------

    /**
     * Write the session cookie
     *
     * @access    public
     * @return    void
     */
    private function __set_cookie()
    {
        header_remove('Set-Cookie');
        
        foreach($_COOKIE as $k => $v)
        {
            $k = strtolower( trim( (string)$k ) );
            
            if( strlen( $k ) == 15)
            {
                if( substr( $k, 0, 12) == strtolower( trim( $this->__name ) ) )
                {
                    setcookie(
                        $k,
                        '',
                        time() - 86400,
                        $this->__path,
                        $this->__domain
                    );
                }
            }
        }

        $split = str_split( $this->__enData , 2000 );     //4k pieces
        for ( $i=0, $c=count($split) ; $i < $c ; $i++ )
        {
            // Set the cookie
            setcookie(
                $this->__name.str_pad((string)$i, 3, '0', STR_PAD_LEFT),
                $split[$i],
                time() + 86400,
                $this->__path,
                $this->__domain
            );
        }
    }


    public function str_split_unicode($str, $length)
    {
        if ($length > 0)
        {
            $ret = [];
            $strLength = mb_strlen($str, "UTF-8");
            for ($i = 0; $i < $strLength; $i += $length)
            {
                $ret[] = mb_substr($str, $i, $length, "UTF-8");
            }
            return $ret;
        }
        return preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
    }
}

/* End of file session.php */
/* Location: ./core/session.php */