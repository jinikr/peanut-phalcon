<?php
namespace Peanut;

class template
{
    /**
     * @var bool|string
     */
    public $compileCheck = true;
    /**
     * @var string
     */
    public $compileRoot = '.';

    /**
     * @var string
     */
    public $templateRoot = '.';
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
     * @var string
     */
    public $skin;
    /**
     * @var string
     */
    public $tplPath;
    /**
     * @var int
     */
    public $permission = 0777;
    /**
     * @var bool
     */
    public $phpengine = true;
    /**
     * @var array
     */
    public $relativePath = [];

    /**
     * @var string
     */
    public $ext = '.php';

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

        if (false === $this->compileCheck) {
            return $cplPath;
        }

        if (@!is_file($tplPath)) {
            trigger_error('cannot find defined template "'.$tplPath.'"', E_USER_ERROR);
        }

        $cplHead = '<?php /* Peanut\Template '.date('Y/m/d H:i:s', filemtime($tplPath)).' '.$tplPath.' ';

        if ('dev' !== $this->compileCheck && @is_file($cplPath)) {
            $fp   = fopen($cplPath, 'rb');
            $head = fread($fp, strlen($cplHead) + 9);
            fclose($fp);

            if (strlen($head) > 9
                && substr($head, 0, -9) == $cplHead && filesize($cplPath) == (int) substr($head, -9)) {
                return $cplPath;
            }
        }

        $compiler = new \Peanut\Template\Compiler();
        $compiler->execute($this, $fid, $tplPath, $cplPath, $cplHead);

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
        return $this->compileRoot.DIRECTORY_SEPARATOR.ltrim($this->relativePath[$fid], '/').$this->ext;
    }

    /**
     * @param  $fid
     * @return mixed
     */
    public function tplPath($fid)
    {
        $path      = '';
        $addFolder = rtrim($this->templateRoot, '/').'/';

        if (true === isset($this->tpl_[$fid])) {
            $path = $this->tpl_[$fid];
        } else {
            trigger_error('template id "'.$fid.'" is not defined', E_USER_ERROR);
        }

        if (false === isset($this->relativePath[$fid])) {
            $skinFolder = trim($this->skin, '/');

            if ($skinFolder) {
                $addFolder .= '/'.$skinFolder.'/';
            }

            $this->relativePath[$fid] = $addFolder.ltrim($path, './');
            $tplPath                  = $this->relativePath[$fid];
        } else {
            $tplPath = $path;
        }

        if (false === is_file($tplPath)) {
            trigger_error('cannot find defined template "'.$path.'"', E_USER_ERROR);
        }

        return $this->tpl_[$fid] = $tplPath;
    }
}
