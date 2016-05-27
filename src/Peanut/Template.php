<?php
namespace Peanut;

class template
{
    /**
     * @var mixed
     */
    public $compile_check = true;
    /**
     * @var mixed
     */
    public $tpl_;

// = [];
    /**
     * @var mixed
     */
    public $var_;

// = [];
    /**
     * @var mixed
     */
    public $skin;
    /**
     * @var mixed
     */
    public $tplPath;
    /**
     * @var int
     */
    public $permission = 0777;
    /**
     * @var mixed
     */
    public $phpengine = true;
    /**
     * @var array
     */
    public $relativePath = [];

    public function __construct()
    {
        $this->tpl_ = [];
        $this->var_ = [];
    }

    /**
     * @param $key
     * @param $value
     */
    public function assign($key, $value = false)
    {
        if (true === is_array($key)) {
            $this->var_ = array_merge($this->var_, $key);
        } else {
            $this->var_[$key] = $value;
        }
    }

    /**
     * @param $fid
     * @param $path
     */
    public function define($fid, $path = false)
    {
        if (true === is_array($fid)) {
            foreach ($fid as $subFid => $subPath) {
                $this->_define($subFid, $subPath);
            }
        } else {
            $this->_define($fid, $path);
        }
    }

    /**
     * @param $fid
     * @param $path
     */
    private function _define($fid, $path)
    {
        $this->tpl_[$fid] = $path;
    }

    /**
     * @param  $fid
     * @param  $print
     * @return mixed
     */
    public function show($fid, $print = false)
    {
        if (true === $print) {
            $this->render($fid);
        } else {
            return $this->fetch($fid);
        }
    }

    /**
     * @param  $fid
     * @return mixed
     */
    public function fetch($fid)
    {
        ob_start();
        $this->render($fid);
        $fetched = ob_get_contents();
        ob_end_clean();

        return $fetched;
    }

    /**
     * @param  $fid
     * @return null
     */
    public function render($fid)
    {
// define 되어있으나 값이 없을때
        if (true === isset($this->tpl_[$fid]) && !$this->tpl_[$fid]) {
            return;
        }

        $this->requireFile($this->getCompilePath($fid));

        return;
    }

    /**
     * @param  $fid
     * @return mixed
     */
    private function getCompilePath($fid)
    {
        $tplPath = $this->tplPath($fid);
        $cplPath = $this->cplPath($fid);

        if (false === $this->compile_check) {
            return $cplPath;
        }

        if (@!is_file($tplPath)) {
            trigger_error('cannot find defined template "'.$tplPath.'"', E_USER_ERROR);
        }

        $cpl_head = '<?php /* vendor\view\template '.date('Y/m/d H:i:s', filemtime($tplPath)).' '.$tplPath.' ';

        if ('dev' !== $this->compile_check && @is_file($cplPath)) {
            $fp   = fopen($cplPath, 'rb');
            $head = fread($fp, strlen($cpl_head) + 9);
            fclose($fp);

            if (strlen($head) > 9
                && substr($head, 0, -9) == $cpl_head && filesize($cplPath) == (int) substr($head, -9)) {
                return $cplPath;
            }
        }

        $compiler = new \Peanut\Template\Compiler();
        $compiler->execute($this, $fid, $tplPath, $cplPath, $cpl_head);

        return $cplPath;
    }

    /**
     * @param $tplPath
     */
    private function requireFile($tplPath)
    {
        extract($this->var_);
        require $tplPath;
    }

    /**
     * @param  $fid
     * @return mixed
     */
    public function cplPath($fid)
    {
        return $this->compile_root.DIRECTORY_SEPARATOR.ltrim($this->relativePath[$fid], '/');
    }

    /**
     * @param  $fid
     * @return mixed
     */
    public function tplPath($fid)
    {
        $path = $addFolder = '';

        if (true === isset($this->tpl_[$fid])) {
            $path = $this->tpl_[$fid];
        } else {
            trigger_error('template id "'.$fid.'" is not defined', E_USER_ERROR);
        }

        if (false === isset($this->relativePath[$fid])) {
            $skinFolder = trim($this->skin, '/');

            if ($skinFolder) {
                $addFolder = $skinFolder.'/';
            }

            $this->relativePath[$fid] = $addFolder.$path;
            $tplPath                  = stream_resolve_include_path($addFolder.$path);
        } else {
            $tplPath = $path;
        }

        if (false === is_file($tplPath)) {
            trigger_error('cannot find defined template "'.$path.'"', E_USER_ERROR);
        }

        return $this->tpl_[$fid] = $tplPath;
    }
}
