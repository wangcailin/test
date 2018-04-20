<?php
/**
 * Created by PhpStorm.
 * User: wangcailin
 * Date: 2018/4/2
 * Time: 下午5:08
 */

namespace app\admin\controller;

use app\common\controller\Backend;
use ZipArchive;
use think\Exception;
use think\Config;

class Importpagefile extends Backend
{
    public function _initialize()
    {
        parent::_initialize();
    }

    public function index()
    {
        return $this->view->fetch();
    }

    /**
     * 文件上传
     */
    public function importFile()
    {
        Config::set('default_return_type', 'json');

        $file = $this->request->file('file');
        $fileTmpDir = RUNTIME_PATH . 'uploadfile' . DS;
        if (!is_dir($fileTmpDir))
        {
            @mkdir($fileTmpDir, 0755, true);
        }
        $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'zip'])->move($fileTmpDir);
        if ($info)
        {
            $tmpName = substr($info->getFilename(), 0, stripos($info->getFilename(), '.'));
            $tmpFile = $fileTmpDir . $info->getSaveName();
            try
            {
                $unzipTmpDir = $this->unzip($tmpName);
                if (!is_dir($unzipTmpDir))
                {
                    throw new Exception(__('解压文件失败'));
                }
                $filesnames = scandir($unzipTmpDir);
                $filesnames = $this->checkDirFile($filesnames);
                $filesnames = $unzipTmpDir . $filesnames . DS;
                if (!is_dir($filesnames))
                {
                    throw new Exception(__('寻找文件失败'));
                }
                session('filedir', $filesnames);
                @unlink($tmpFile);
                $this->success(__('文件解析成功'));
            }
            catch (Exception $e)
            {
                @unlink($tmpFile);
                $this->error($e->getMessage());
            }
        }
        else
        {
            // 上传失败获取错误信息
            $this->error($file->getError());
        }
    }

    public function submit()
    {
        $fileDir = session('filedir');
        $formData = input('row/a');

        if (!$formData['js'] && !$formData['css'] && !$formData['img'] && !$formData['fonts'] || !$formData['project'] || !$formData['module']){$this->error('数据错误！');}
        if (!session('filedir')){$this->error('文件错误！');}

        $module      = model('ProjectModule')->where('id', $formData['module'])->find();
        $projectName = model('ProjectList')->where('id', $formData['project'])->value('name');

        $jsDir = ROOT_PATH . 'public/assets/js/index/' . $projectName . DS;
        $cssDir = ROOT_PATH . 'public/assets/css/index/' . $projectName . DS;
        $imgDir = ROOT_PATH . 'public/assets/img/index/' . $projectName . DS;
        $fontsDir = ROOT_PATH . 'public/assets/fonts/index/' . $projectName . DS;
        $mediaDir = ROOT_PATH . 'public/assets/media/index/' . $projectName . DS;
        $controllerFunction = [];
        $pageUrl = '';
        try{
            if($handle = opendir($fileDir)){
                while (false !== ($file = readdir($handle))){
                    $str = substr($file,0,1);
                    if ($str != '.' && $str != '_'){
                        if (strpos($file, '.html')){
                            $controllerFunction[] = str_replace('.html', '', $file);
                            $pageUrl .= $file . '页面链接：<br />' . $_SERVER['HTTP_HOST'] . '/index/' . $projectName .'/'. $module['name'] .'/'. $file . '<br />';
                            try
                            {
                                $filename   = $fileDir . $file;
                                $moduleDir  = APP_PATH . 'index/view/' . $projectName . DS . $module['name'] . DS;
                                $newFileDir = $moduleDir . $file;
                                if (!is_dir($moduleDir)){
                                    $projectDir = APP_PATH . 'index/view/' . $projectName . DS;
                                    if (!is_dir($projectDir)){
                                        if (!mkdir($projectDir, 0755, true)){
                                            throw new Exception(__('创建项目文件夹失败'));
                                        }
                                    }
                                    if (!mkdir($moduleDir, 0755, true)){
                                        throw new Exception(__('创建模块文件夹失败'));
                                    }
                                }
                                if (!rename($filename, $newFileDir)){
                                    throw new Exception(__('添加.html文件失败'));
                                }
                                $this->replaceHtml($newFileDir, $projectName, $formData['js'], $formData['css'], $formData['img'], $module['monitorlist']);
                            }
                            catch (Exception $e)
                            {
                                @unlink($projectDir);
                                $this->error($e->getMessage());
                            }
                        }elseif (is_dir($fileDir.$file)){
                            try
                            {
                                if($handleold = opendir($fileDir.$file)){
                                    while (false !== ($fileold = readdir($handleold))){
                                        $strold = substr($fileold,0,1);
                                        if ($strold != '.' && $strold != '_'){
                                            if ($file == $formData['js']){
                                                if (!is_dir($jsDir)) mkdir($jsDir, 0755, true);
                                                @rename($fileDir.$formData['js'].DS.$fileold, $jsDir.$fileold);
                                            }elseif ($file == $formData['css']){
                                                if (!is_dir($cssDir)) mkdir($cssDir, 0755, true);
                                                @rename($fileDir.$formData['css'].DS.$fileold, $cssDir.$fileold);
                                                $this->replaceCss($cssDir.$fileold, $projectName, $formData['img'], $formData['fonts']);
                                            }elseif ($file == $formData['img']){
                                                if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);
                                                @rename($fileDir.$formData['img'].DS.$fileold, $imgDir.$fileold);
                                            }elseif ($file == $formData['fonts']){
                                                if (!is_dir($fontsDir)) mkdir($fontsDir, 0755, true);
                                                @rename($fileDir.$formData['fonts'].DS.$fileold, $fontsDir.$fileold);
                                            }elseif ($file == $formData['media']){
                                                if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);
                                                @rename($fileDir.$formData['media'].DS.$fileold, $mediaDir.$fileold);
                                            }
                                        }
                                    }
                                    closedir($handleold);
                                }
                            }
                            catch (Exception $e)
                            {
                                $this->error($e->getMessage());
                            }

                        }
                    }

                }
                closedir($handle);
            }
            $this->delDirAndFile($fileDir);
            $data = [
                'controllerNamespace'      => 'app\index\controller\\'.$projectName.';',
                'controllerName'            => ucfirst(strtolower($module['name'])),
                'projectName'               => $projectName,
                'controllerFunction'        => $controllerFunction,
            ];
            
            if ($module['monitorlist'] != '0'){
                $controllerFile = 'controllerWechat';
            }else {
                $controllerFile = 'controller';
            }

            $controllerFunctionFile    = 'controllerFunction';
            $this->writeToFile($data, $controllerFile, $controllerFunctionFile);
            $this->success('页面部署成功！', '', $pageUrl);
        }
        catch (Exception $e)
        {
            $this->delDirAndFile($fileDir);
            $this->error($e->getMessage());
        }
    }

    /**
     * 解压文件
     * @param $name     文件路径
     * @return string
     * @throws Exception
     */
    public static function unzip($name)
    {
        $file = RUNTIME_PATH . 'uploadfile' . DS . $name . '.zip';
        $dir = RUNTIME_PATH . 'uploadfile/' . $name . DS;
        if (class_exists('ZipArchive'))
        {
            $zip = new ZipArchive;
            if ($zip->open($file) !== TRUE)
            {
                throw new Exception('Unable to open the zip file');
            }
            if (!$zip->extractTo($dir))
            {
                $zip->close();
                throw new Exception('Unable to extract the file');
            }
            $zip->close();
            return $dir;
        }
        throw new Exception("无法执行解压操作，请确保ZipArchive安装正确");
    }

    /**
     * 排除文件
     * @param $filesnames
     * @return mixed
     */
    private function checkDirFile($filesnames)
    {
        if ($filesnames){
            foreach ($filesnames as $k=>$v){
                $str = substr($v,0,1);
                if ($str != '.' && $str != '_'){
                    return $v;
                }
            }
        }
    }

    /**
     * 写入到文件
     * @param $data
     * @param $controllerFile
     * @param $controllerFunctionFile
     * @return bool|int
     */
    protected function writeToFile($data, $controllerFile, $controllerFunctionFile)
    {
        $pathname = APP_PATH . 'index' . DS . 'controller' . DS . $data['projectName'] . DS . $data['controllerName'] . '.php';
        if (!is_dir(dirname($pathname))) {
            mkdir(strtolower(dirname($pathname)), 0755, true);
        }

        $function = '';
        foreach ($data['controllerFunction'] as $k=>$v){
            $datas['controllerFunction'] = $v;
            $function .= $this->getReplacedStub($controllerFunctionFile, $datas);
        }
        unset($data['controllerFunction']);
        $data['controllerFunction'] = $function;
        $content  = $this->getReplacedStub($controllerFile, $data);
        return file_put_contents($pathname, $content);
    }

    /**
     * 获取替换后的数据
     * @param string $name
     * @param array $data
     * @return string
     */
    private function getReplacedStub($name, $data)
    {
        $search = $replace = [];
        foreach ($data as $k => $v) {
            $search[] = "{%{$k}%}";
            $replace[] = $v;
        }
        $stubname = $this->getStub($name);
        $stub = file_get_contents($stubname);
        $content = str_replace($search, $replace, $stub);
        return $content;
    }

    /**
     * 获取stub文件内容
     * @param $name
     * @return string
     */
    protected function getStub($name)
    {
        return APP_PATH . 'index' . DS . 'controller' .  DS . 'stubs' . DS . $name . '.stub';
    }

    /**
     * 删除目录及其目录下所有文件
     * @param $dirName 目录路径
     */
    protected function delDirAndFile($dirName)
    {
        if ( $handle = opendir($dirName) ) {
            while ( false !== ($item = readdir($handle))) {
                if ( $item != "." && $item != ".." ) {
                    if ( is_dir($dirName . DS . $item) ) {
                        $this->delDirAndFile($dirName . DS . $item);
                    } else {
                        unlink($dirName . DS . $item);
                    }
                }
            }
            closedir($handle);
            rmdir($dirName);
        }
    }

    /**
     * 替换HTML文件引入资源路径
     * @param $filename HTML文件路径
     * @param $object   项目名称
     * @param $js       js目录
     * @param $css      css目录
     * @param $img      img目录
     * @return bool|int
     */
    protected function replaceHtml($filename, $object, $js, $css, $img, $monitorlist)
    {
        if (is_file($filename)){
            $html   = file_get_contents($filename);
            $jsDir  = 'src="__CDN__/assets/js/index/'.$object;
            $jsStr  = 'src="'.$js;
            $cssDir = 'href="__CDN__/assets/css/index/'.$object;
            $cssStr = 'href="'.$css;
            $imgDir = 'src="__CDN__/assets/img/index/'.$object;
            $imgStr = 'src="'.$img;
            $cssImgDir = 'url(\'__CDN__/assets/img/index/'.$object;
            $cssImgStr = 'url(\'./'.$img;
            $html   = str_replace($jsStr, $jsDir, $html);
            $html   = str_replace($cssStr, $cssDir, $html);
            $html   = str_replace($imgStr, $imgDir, $html);
            $html   = str_replace($cssImgStr, $cssImgDir, $html);

            $monitorlist = explode(',', $monitorlist);
            if ($monitorlist){
                foreach ($monitorlist as $v){
                    if ($v == 1){
                        $html = str_replace('<google></google>', '{include file="common/google" /}', $html);
                    }elseif ($v == 2){
                        $html = str_replace('<jssdk></jssdk>', '{include file="common/jssdk" /}', $html);
                        $html = str_replace('<share></share>', '{include file="common/share" /}', $html);
                    }
                }
            }

            return file_put_contents($filename, $html);
        }
    }

    /**
     * 替换CSS文件引入资源路径
     * @param $filename     CSS文件路径
     * @param $object       项目名称
     * @param $img          img目录
     * @param $fonts        font目录
     * @return bool|int
     */
    protected function replaceCss($filename, $object, $img, $fonts)
    {
        $PHP_SELF =$_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $rootPath = substr($PHP_SELF,0,strrpos($PHP_SELF, '/')+1);
        if (is_file($filename)){
            $css     = file_get_contents($filename);
            $fontDir = 'url(\''.$rootPath.'assets/img/index/'.$object;
            $fontStr = 'url(\'../'.$img;
            $imgDir  = 'url(\''.$rootPath.'assets/fonts/index/'.$object;
            $imgStr  = 'url(\'../'.$fonts;
            $css     = str_replace($imgStr, $imgDir, $css);
            $css     = str_replace($fontStr, $fontDir, $css);
            return file_put_contents($filename, $css);
        }
    }
}