<?php
/**
 * UEditor Widget扩展
 * @author xbzbing<xbzbing@gmail.com>
 * @link www.crazydb.com
 *
 * UEditor版本v1.4.3.1
 * Yii 版本 2.0+
 */
namespace crazydb\ueditor;

use yii;
use yii\imagine\Image;
use yii\web\Controller;

/**
 * Class UEditorController
 * 负责UEditor后台响应
 * @package crazydb\ueditor
 */
class UEditorController extends Controller
{
    /**
     * UEditor的配置
     * @see http://fex-team.github.io/ueditor/#start-config
     * @var array
     */
    public $config;

    /**
     * 列出文件/图片时需要忽略的文件夹
     * 主要用于处理缩略图管理，兼容比如elFinder之类的程序
     * @var array
     */
    public $ignoreDir = [
        '.thumbnails'
    ];

    /**
     * 缩略图设置
     * 默认为200*200，如果设置为空数组则不生成缩略图
     * @var array
     */
    public $thumbnail = ['height' => 200, 'width' => 200];

    /**
     * 图片缩放设置
     * 默认不缩放。
     * 配置如 ['height'=>200,'width'=>200]
     * @var array
     */
    public $zoom = [];

    /**
     * 水印设置
     * 参考配置如下：
     * ['path'=>'水印图片位置','start'=>[0, 0]]
     * 默认位置为[0, 0]，可不配置
     * @var array
     */
    public $watermark = [];

    /**
     * 默认 action
     * @var string
     */
    public $defaultAction = 'index';
    
    /**
     * 存储根url地址别名
     * @var string
     */
    public $rootUrl;
    
    /**
     * 存储根目录别名
     * @var string
     */
    public $rootPath = '@webroot';
    
    /**
     * Web根目录
     * @var string
     */
    protected $webroot;

    public function init()
    {
        parent::init();
        //CSRF 基于 POST 验证，UEditor 无法添加自定义 POST 数据，同时由于这里不会产生安全问题，故简单粗暴地取消 CSRF 验证。
        //如需 CSRF 防御，可以使用 server_param 方法，然后在这里将 Get 的 CSRF 添加到 POST 的数组中。。。
        Yii::$app->request->enableCsrfValidation = false;

        //当客户使用低版本IE时，会使用swf上传插件，维持认证状态可以参考文档UEditor「自定义请求参数」部分。
        //http://fex.baidu.com/ueditor/#server-server_param

        //保留UE默认的配置引入方式
        if (file_exists(__DIR__ . '/config.json'))
            $CONFIG = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", '', file_get_contents(__DIR__ . '/config.json')), true);
        else
            $CONFIG = [];

        if (!is_array($this->config))
            $this->config = [];

        if (!is_array($CONFIG))
            $CONFIG = [];

        $default = [
            'imagePathFormat' => '/upload/image/{yyyy}{mm}{dd}/{time}{rand:8}',
            'scrawlPathFormat' => '/upload/image/{yyyy}{mm}{dd}/{time}{rand:8}',
            'snapscreenPathFormat' => '/upload/image/{yyyy}{mm}{dd}/{time}{rand:8}',
            'catcherPathFormat' => '/upload/image/{yyyy}{mm}{dd}/{time}{rand:8}',
            'videoPathFormat' => '/upload/video/{yyyy}{mm}{dd}/{time}{rand:8}',
            'filePathFormat' => '/upload/file/{yyyy}{mm}{dd}/{rand:8}_{filename}',
            'imageManagerListPath' => '/upload/image/',
            'fileManagerListPath' => '/upload/file/',
        ];
        $this->config = $this->config + $default + $CONFIG;
        $this->webroot = Yii::getAlias($this->rootPath);
        $this->rootUrl = Yii::getAlias($this->rootUrl);
        if(!is_array($this->thumbnail))
            $this->thumbnail = false;
    }

    /**
     * 蛋疼的统一后台入口
     */
    public function actionIndex()
    {
        $action = strtolower(Yii::$app->request->get('action', 'config'));
        $actions = [
            'uploadimage' => 'upload-image',
            'uploadscrawl' => 'upload-scrawl',
            'uploadvideo' => 'upload-video',
            'uploadfile' => 'upload-file',
            'listimage' => 'list-image',
            'listfile' => 'list-file',
            'catchimage' => 'catch-image',
            'config' => 'config',
            'listinfo' => 'list-info'
        ];

        if (isset($actions[$action]))
            return $this->run($actions[$action]);
        else
            return $this->show(['state' => 'Unknown action.']);
    }

    /**
     * 显示配置信息
     */
    public function actionConfig()
    {
        return $this->show($this->config);
    }

    /**
     * 上传图片
     */
    public function actionUploadImage()
    {
        $config = [
            'pathFormat' => $this->config['imagePathFormat'],
            'maxSize' => $this->config['imageMaxSize'],
            'allowFiles' => $this->config['imageAllowFiles']
        ];
        $fieldName = $this->config['imageFieldName'];
        $result = $this->upload($fieldName, $config);
        return $this->show($result);
    }

    /**
     * 上传涂鸦
     */
    public function actionUploadScrawl()
    {
        $config = [
            'pathFormat' => $this->config['scrawlPathFormat'],
            'maxSize' => $this->config['scrawlMaxSize'],
            'allowFiles' => $this->config['scrawlAllowFiles'],
            'oriName' => 'scrawl.png'
        ];
        $fieldName = $this->config['scrawlFieldName'];
        $result = $this->upload($fieldName, $config, 'base64');
        return $this->show($result);
    }

    /**
     * 上传视频
     */
    public function actionUploadVideo()
    {
        $config = [
            'pathFormat' => $this->config['videoPathFormat'],
            'maxSize' => $this->config['videoMaxSize'],
            'allowFiles' => $this->config['videoAllowFiles']
        ];
        $fieldName = $this->config['videoFieldName'];
        $result = $this->upload($fieldName, $config);
        return $this->show($result);
    }

    /**
     * 上传文件
     */
    public function actionUploadFile()
    {
        $config = [
            'pathFormat' => $this->config['filePathFormat'],
            'maxSize' => $this->config['fileMaxSize'],
            'allowFiles' => $this->config['fileAllowFiles']
        ];
        $fieldName = $this->config['fileFieldName'];
        $result = $this->upload($fieldName, $config);
        return $this->show($result);
    }

    /**
     * 文件列表
     */
    public function actionListFile()
    {
        $allowFiles = $this->config['fileManagerAllowFiles'];
        $listSize = $this->config['fileManagerListSize'];
        $path = $this->config['fileManagerListPath'];
        $result = $this->manage($allowFiles, $listSize, $path);
        return $this->show($result);
    }

    /**
     *  图片列表
     */
    public function actionListImage()
    {
        $allowFiles = $this->config['imageManagerAllowFiles'];
        $listSize = $this->config['imageManagerListSize'];
        $path = $this->config['imageManagerListPath'];
        $result = $this->manage($allowFiles, $listSize, $path);
        return $this->show($result);
    }

    /**
     * 获取远程图片
     */
    public function actionCatchImage()
    {
        /* 上传配置 */
        $config = [
            'pathFormat' => $this->config['catcherPathFormat'],
            'maxSize' => $this->config['catcherMaxSize'],
            'allowFiles' => $this->config['catcherAllowFiles'],
            'oriName' => 'remote.png'
        ];
        $fieldName = $this->config['catcherFieldName'];
        /* 抓取远程图片 */
        $list = [];
        if (isset($_POST[$fieldName])) {
            $source = $_POST[$fieldName];
        } else {
            $source = $_GET[$fieldName];
        }
        foreach ($source as $imgUrl) {
            $item = new Uploader($imgUrl, $config, 'remote', $this->rootPath);
            $info = $item->getFileInfo();
            $info['thumbnail'] = $this->imageHandle($info['url']);
            $list[] = [
                'state' => $info['state'],
                'url' => $info['url'],
                'source' => $imgUrl
            ];
        }
        /* 返回抓取数据 */
        return [
            'state' => count($list) ? 'SUCCESS' : 'ERROR',
            'list' => $list
        ];
    }

    /**
     * 各种上传
     * @param $fieldName
     * @param $config
     * @param $base64
     * @return array
     */
    protected function upload($fieldName, $config, $base64 = 'upload')
    {
        $up = new Uploader($fieldName, $config, $base64, $this->rootPath);
        $info = $up->getFileInfo();
        if ($this->thumbnail && $info['state'] == 'SUCCESS' && in_array($info['type'], ['.png', '.jpg', '.bmp', '.gif'])) {
            $info['thumbnail'] = $this->rootUrl . $this->imageHandle($info['url']);
        }
        $info['url'] = $this->rootUrl . $info['url'];
        $info['original'] = htmlspecialchars($info['original']);
        $info['width'] = $info['height'] = 500;
        return $info;
    }

    /**
     * 自动处理图片
     * @param $fullName
     * @return mixed|string
     */
    protected function imageHandle($fullName)
    {
        if (substr($fullName, 0, 1) != '/')
            $fullName = '/' . $fullName;

        $file = $fullName;

        //先处理缩略图
        if ($this->thumbnail && !empty($this->thumbnail['height']) && !empty($this->thumbnail['width'])) {
            $file = pathinfo($file);
            $file = $file['dirname'] . '/' . $file['filename'] . '.thumbnail.' . $file['extension'];
            Image::thumbnail($this->webroot . $fullName, intval($this->thumbnail['width']), intval($this->thumbnail['height']))
                ->save($this->webroot . $file);
        }
        //再处理缩放，默认不缩放
        //...缩放效果非常差劲-，-
        if (isset($this->zoom['height']) && isset($this->zoom['width'])) {
            $size = $this->getSize($this->webroot . $fullName);
            if ($size && $size[0] > 0 && $size[1] > 0) {
                $ratio = min([$this->zoom['height'] / $size[0], $this->zoom['width'] / $size[1], 1]);
                Image::thumbnail($this->webroot . $fullName, ceil($size[0] * $ratio), ceil($size[1] * $ratio))
                    ->save($this->webroot . $fullName);
            }
        }
        //最后生成水印
        if (isset($this->watermark['path']) && file_exists($this->watermark['path'])) {
            if (!isset($this->watermark['start']))
                $this->watermark['start'] = [0, 0];
            Image::watermark($file, $this->watermark['path'], $this->watermark['start'])
                ->save($file);
        }

        return $file;
    }

    /**
     * 获取图片的大小
     * 主要用于获取图片大小并
     * @param $file
     * @return array
     */
    protected function getSize($file)
    {
        if (!file_exists($file))
            return [];

        $info = pathinfo($file);
        $image = null;
        switch (strtolower($info['extension'])) {
            case 'gif':
                $image = imagecreatefromgif($file);
                break;
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($file);
                break;
            case 'png':
                $image = imagecreatefrompng($file);
                break;
            default:
                break;
        }
        if ($image == null)
            return [];
        else
            return [imagesx($image), imagesy($image)];
    }

    /**
     * 文件和图片管理action使用
     * @param $allowFiles
     * @param $listSize
     * @param $path
     * @return array
     */
    protected function manage($allowFiles, $listSize, $path)
    {
        $allowFiles = substr(str_replace('.', '|', join('', $allowFiles)), 1);
        /* 获取参数 */
        $size = isset($_GET['size']) ? $_GET['size'] : $listSize;
        $start = isset($_GET['start']) ? $_GET['start'] : 0;
        $end = $start + $size;

        /* 获取文件列表 */
        $path = $this->webroot . (substr($path, 0, 1) == '/' ? '' : '/') . $path;
        $files = $this->getFiles($path, $allowFiles);
        if (!count($files)) {
            $result = [
                'state' => 'no match file',
                'list' => [],
                'start' => $start,
                'total' => count($files),
            ];
            return $result;
        }
        /* 获取指定范围的列表 */
        $len = count($files);
        for ($i = min($end, $len) - 1, $list = []; $i < $len && $i >= 0 && $i >= $start; $i--) {
            $list[] = $files[$i];
        }
        /* 返回数据 */
        $result = [
            'state' => 'SUCCESS',
            'list' => $list,
            'start' => $start,
            'total' => count($files),
        ];
        return $result;
    }

    /**
     * 遍历获取目录下的指定类型的文件
     * @param $path
     * @param $allowFiles
     * @param array $files
     * @return array|null
     */
    protected function getFiles($path, $allowFiles, &$files = [])
    {
        if (!is_dir($path)) return null;
        if (in_array(basename($path), $this->ignoreDir)) return null;
        if (substr($path, strlen($path) - 1) != '/') $path .= '/';
        $handle = opendir($path);
        //baseUrl用于兼容使用alias的二级目录部署方式
        $baseUrl = $this->rootUrl;
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getFiles($path2, $allowFiles, $files);
                } else {
                    if ($this->action->id == 'list-image' && $this->thumbnail) {
                        $pat = "/\.thumbnail\.(" . $allowFiles . ")$/i";
                    } else {
                        $pat = "/\.(" . $allowFiles . ")$/i";
                    }
                    if (preg_match($pat, $file)) {
                        $files[] = [
                            'url' => $baseUrl . substr($path2, strlen($this->webroot)),
                            'mtime' => filemtime($path2)
                        ];
                    }
                }
            }
        }
        return $files;
    }

    /**
     * 最终显示结果，自动输出 JSONP 或者 JSON
     * @param array $result
     * @return array
     */
    protected function show($result)
    {
        $callback = Yii::$app->request->get('callback', null);

        if ($callback && is_string($callback)) {
            Yii::$app->response->format = yii\web\Response::FORMAT_JSONP;
            return [
                'callback' => $callback,
                'data' => $result
            ];
        }

        Yii::$app->response->format = yii\web\Response::FORMAT_JSON;
        return $result;
    }
}
