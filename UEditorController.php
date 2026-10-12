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
use yii\base\InvalidConfigException;
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
     * 默认不开启
     * ['height' => 200, 'width' => 200]表示生成200*200的缩略图，如果设置为空数组则不生成缩略图
     * @var array
     */
    public $thumbnail = [];

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
     * ['path'=>'水印图片位置','position'=>0]
     * 默认位置为 9，可不配置
     * position in [1 ,9]，表示从左上到右下的9个位置。
     * @var array
     */
    public $watermark = [];

    /**
     * 是否允许内网采集
     * 如果为 false 则远程图片获取不获取内网图片，防止 SSRF。
     * 默认为 false
     * @var bool
     */
    public $allowIntranet = false;

    /**
     * 默认 action
     * @var string
     */
    public $defaultAction = 'index';

    /**
     * 上传文件的保存根目录，默认为网站根目录 `@webroot`。
     *
     * 配置它可以把上传文件存到 web 根之外（例如单独挂载的静态资源盘）。
     * 此时必须同时配置 {@see $baseUrl}，否则 URL 无法正确拼出。
     * @var string
     */
    public $basePath;

    /**
     * `$basePath` 对应的 URL 前缀，默认为 `@web`。
     *
     * 仅当把文件存到 `@webroot` 之外时才需要显式配置，例如 `$basePath = '@app/uploads'`
     * 且该目录通过 `/uploads` 对外提供访问时，设为 `/uploads`。
     * @var string
     */
    public $baseUrl;

    /**
     * @deprecated 请改用 {@see $basePath}。保留以便既有子类覆写 `$webroot` 时仍能生效。
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

        //保存根目录：优先取配置的 basePath；未配置时回退到子类覆写的 $webroot，再回退到 @webroot
        if (empty($this->basePath)) {
            $this->basePath = !empty($this->webroot) ? $this->webroot : Yii::getAlias('@webroot');
        }
        //必须在这里把别名解析成真实路径：下游（Uploader 的 rootPath、Image、文件列表）
        //都是直接拼字符串，不会自己解析 @xxx
        $this->basePath = Yii::getAlias($this->basePath);
        //同步旧属性，供仍读取 $webroot 的既有子类使用
        $this->webroot = $this->basePath;

        //URL 前缀：默认取 @web（Yii2 的 web 应用在 bootstrap 时由 request->baseUrl 派生）。
        //@web 未定义时回退到当前请求的 baseUrl，与改动前的行为一致。
        $baseUrlConfigured = !empty($this->baseUrl);
        if (!$baseUrlConfigured) {
            $this->baseUrl = Yii::getAlias('@web', false) ?: Yii::$app->request->baseUrl;
        }

        //把文件存到 @webroot 之外时，URL 前缀无法再自动推导，必须显式给出
        if ($this->basePath !== Yii::getAlias('@webroot') && !$baseUrlConfigured) {
            throw new InvalidConfigException('配置了 basePath 时必须同时配置 baseUrl，否则无法生成可访问的 URL。');
        }

        if (!is_array($this->thumbnail))
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
            'oriName' => 'remote.png',
            'rootPath' => $this->basePath
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
            $item = new Uploader($imgUrl, $config, 'remote');
            if ($this->allowIntranet)
                $item->setAllowIntranet(true);
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
        //让 Uploader 与控制器使用同一个 web 根目录，避免子类覆写 webroot 后保存路径不一致
        $config['rootPath'] = $this->basePath;
        $up = new Uploader($fieldName, $config, $base64);

        if ($this->allowIntranet)
            $up->setAllowIntranet(true);

        $info = $up->getFileInfo();
        if (($this->thumbnail or $this->zoom or $this->watermark) && $info['state'] == 'SUCCESS' && in_array($info['type'], ['.png', '.jpg', '.bmp', '.gif'])) {
            $info['thumbnail'] = $this->baseUrl . $this->imageHandle($info['url']);
        }
        $info['url'] = $this->baseUrl . $info['url'];
        $info['original'] = htmlspecialchars($info['original']);
        $info['width'] = $info['height'] = 500;
        return $info;
    }

    /**
     * 自动处理图片
     * @param $file
     * @return mixed|string
     */
    protected function imageHandle($file)
    {
        if (substr($file, 0, 1) != '/')
            $file = '/' . $file;


        //先处理缩略图
        if ($this->thumbnail && !empty($this->thumbnail['height']) && !empty($this->thumbnail['width'])) {
            $file_path = pathinfo($file);
            $thumbnailFile = $file_path['dirname'] . '/' . $file_path['filename'] . '.thumbnail.' . $file_path['extension'];
            Image::thumbnail($this->basePath . $file, intval($this->thumbnail['width']), intval($this->thumbnail['height']))
                ->save($this->basePath . $thumbnailFile);
        }
        //再处理缩放，默认不缩放
        //...缩放效果非常差劲-，-
        if (isset($this->zoom['height']) && isset($this->zoom['width'])) {
            $size = $this->getSize($this->basePath . $file);
            if ($size && $size[0] > 0 && $size[1] > 0) {
                $ratio = min([$this->zoom['height'] / $size[0], $this->zoom['width'] / $size[1], 1]);
                Image::thumbnail($this->basePath . $file, ceil($size[0] * $ratio), ceil($size[1] * $ratio))
                    ->save($this->basePath . $file);
            }
        }
        //最后生成水印
        if (isset($this->watermark['path']) && file_exists($this->watermark['path'])) {
            if (!isset($this->watermark['position']) or $this->watermark['position'] > 9 or $this->watermark['position'] < 0 or !is_numeric($this->watermark['position']))
                $this->watermark['position'] = 9;
            $size = $this->getSize($this->basePath . $file);
            $waterSize = $this->getSize($this->watermark['path']);
            if ($size[0] > $waterSize[0] and $size[1] > $waterSize[1]) {
                $halfX = $size[0] / 2;
                $halfY = $size[1] / 2;
                $halfWaterX = $waterSize[0] / 2;
                $halfWaterY = $waterSize[1] / 2;
                switch (intval($this->watermark['position'])) {
                    case 1:
                        $x = 0;
                        $y = 0;
                        break;
                    case 2:
                        $x = $halfX - $halfWaterX;
                        $y = 0;
                        break;
                    case 3:
                        $x = $size[0] - $waterSize[0];
                        $y = 0;
                        break;
                    case 4:
                        $x = 0;
                        $y = $halfY - $halfWaterY;
                        break;
                    case 5:
                        $x = $halfX - $halfWaterX;
                        $y = $halfY - $halfWaterY;
                        break;
                    case 6:
                        $x = $size[0] - $waterSize[0];
                        $y = $halfY - $halfWaterY;
                        break;
                    case 7:
                        $x = 0;
                        $y = $size[1] - $waterSize[1];
                        break;
                    case 8:
                        $x = $halfX - $halfWaterX;
                        $y = $size[1] - $waterSize[1];
                        break;
                    case 9:
                    default:
                        $x = $size[0] - $waterSize[0];
                        $y = $size[1] - $waterSize[1];
                }
                Image::watermark($this->basePath . $file, $this->watermark['path'], [$x, $y])
                    ->save($this->basePath . $file);
            }
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
        $path = $this->basePath . (substr($path, 0, 1) == '/' ? '' : '/') . $path;
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
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getFiles($path2, $allowFiles, $files);
                } else {
                    if ($this->action->id == 'list-image') {
                        $pat = "/\.thumbnail\.(" . $allowFiles . ")$/i";
                    } else {
                        $pat = "/\.(" . $allowFiles . ")$/i";
                    }
                    if (preg_match($pat, $file)) {
                        $files[] = [
                            //baseUrl 用于兼容使用 alias 的二级目录部署方式
                            'url' => $this->baseUrl . substr($path2, strlen($this->basePath)),
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
