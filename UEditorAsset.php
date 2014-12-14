<?php
/**
 * UEditor Widget扩展
 * @author xbzbing<xbzbing@gmail.com>
 * @link www.crazydb.com
 *
 * UEditor版本v1.4.3
 * Yii版本2.0
 */
namespace crazydb\ueditor;

use yii;
use yii\web\AssetBundle;

/**
 * Class UEditorAsset
 * 负责UEditor的资源文件引入，由于UEditor没有在bower注册包，因此扩展包含了UEditor的核心文件。
 * @package crazydb\ueditor
 */
class UEditorAsset extends AssetBundle
{
    public $sourcePath = '@crazydb/ueditor/assets';

    /**
     * UEditor加载需要的JS文件。
     * ueditor.config.js中是默认配置项，不建议直接引入。
     * @var array
     */
    public $js = [
        'ueditor.all.min.js',
    ];

    /**
     * UEditor加载需要的CSS文件。
     * UEditor 会自动加载默认皮肤，CSS这里不必指定
     * @var array
     */
    public $css = [
    ];
}
