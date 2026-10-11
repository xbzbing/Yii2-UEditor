Yii2-UEditor-Extension
======================
Yii2的百度UEditor扩展


由于bower上的包是纯源码，需要用grunt打包后才能使用，因此扩展自带了1.4.3版本的UEditor资源包。

扩展特点：

1. 支持多实例
2. 支持缩略图（默认关闭）
3. 支持缩放（默认关闭）
4. 支持水印（默认关闭）
5. 图片管理加载优化


环境要求
------------

| 依赖 | 约束 | 说明 |
|---|---|---|
| PHP | `>=7.4` | 与 Yii2 2.0.40+ 的要求一致 |
| yiisoft/yii2 | `~2.0.14` | 扩展仅使用 `AssetBundle`、`InputWidget`、`Controller` 等全系稳定的 API |
| yiisoft/yii2-imagine | `~2.1.0` | 缩略图与水印功能所需 |

关于 Yii2 版本的两点说明：

- 扩展只用到 2.0 全系稳定的 API，因此约束的下界是 `2.0.14`，上界不设限。
- 不过在 Composer 2.10 及以上版本中，安全策略（`audit.block-insecure`，默认开启）会拦截存在已知漏洞的版本。Yii2 2.0.54 及以下受 CVE-2026-39850（本地文件包含）影响，因此**新装时实际解析到的版本不会低于 2.0.55**。这是 Composer 的行为，与本扩展的版本约束无关。若需安装更早的 Yii2，请在项目根包中自行关闭该策略，并自行评估安全风险。

安装
------------

推荐使用 [composer](http://getcomposer.org/download/) 来安装扩展.

使用如下命令安装：

```
php composer.phar require --prefer-dist crazydb/yii2-ueditor "*"
```

或者将 `"crazydb/yii2-ueditor": "*"` 添加到项目的 `composer.json` 文件.

### 需要在宿主项目中配置 asset-packagist

Yii2 依赖若干 `bower-asset/*` 前端包（jQuery、inputmask 等），这些包不在 Packagist 上，需要通过 [asset-packagist](https://asset-packagist.org/) 提供。

**这一步必须配置在宿主项目的 `composer.json` 中**。扩展自身声明的 `repositories` 对 Composer 无效，因为只有根包的 `repositories` 生效，所以无法由扩展代为声明：

```json
{
    "repositories": {
        "asset-packagist.org": {
            "type": "composer",
            "url": "https://asset-packagist.org"
        }
    }
}
```

未配置时，安装会以 `could not be found` 报错告终，提示找不到 `bower-asset/jquery` 等包。

### 覆盖 `@bower` 别名

asset-packagist 将前端包安装到 `vendor/bower-asset/`，而 Yii2 默认的 `@bower` 别名指向 `vendor/bower/`（旧的 bower/npm 布局）。若你的项目未对齐这两者，编辑器会因 jQuery 资源无法发布而报错：

```
The file or directory to be published does not exist: .../vendor/bower/jquery/dist
```

在应用配置中覆盖该别名即可：

```php
'aliases' => [
    '@bower' => '@vendor/bower-asset',
],
```


使用方法
-----

安装完毕后，进行简单的配置即可使用。


### 后端支持

1) 继承 `crazydb\ueditor\UEditorController` 来实现自己的后端。（推荐）

```php
class EditorController extends crazydb\ueditor\UEditorController
{
    public function init(){
        parent::init();
        //do something
        //这里可以对扩展的访问权限进行控制
    }
    
    public function actionConfig(){
        //do something
        //这里可以对 config 请求进行自定义响应
    }
    
    // more modify ...
    // 更多的修改
}
```

通过继承来编写自己的 Controller，可以精细的控制每个 action 的响应，推荐使用这种方式使用。

需要注意的是在 View 模板文件中使用扩展的时候需要指定 `serverUrl` 为自己编写的 controller 地址。

2)  通过配置 `controllerMap` 使用默认的后端。

修改配置文件，基础模板的配置文件是 `config/web.php`， 高级模板的配置文件是 `config/main.php`。

```php
    'controllerMap' => [
        'ueditor' => [
            'class' => 'crazydb\ueditor\UEditorController',
        ]
    ],
```

简单配置即可使用，还可以配置更多选项：

```
    'controllerMap' => [
        'ueditor' => [
            'class' => 'crazydb\ueditor\UEditorController',
            'thumbnail' => false,//如果将'thumbnail'设置为空，将不生成缩略图。
            'watermark' => [    //默认不生存水印
                'path' => '', //水印图片路径
                'position' => 9 //position in [1, 9]，表示从左上到右下的 9 个位置，即如1表示左上，5表示中间，9表示右下。
            ],
            'zoom' => ['height' => 500, 'width' => 500], //缩放，默认不缩放
            'config' => [
                //server config @see http://fex-team.github.io/ueditor/#server-config
                'imagePathFormat' => '/upload/image/{yyyy}{mm}{dd}/{time}{rand:6}',
                'scrawlPathFormat' => '/upload/image/{yyyy}{mm}{dd}/{time}{rand:6}',
                'snapscreenPathFormat' => '/upload/image/{yyyy}{mm}{dd}/{time}{rand:6}',
                'catcherPathFormat' => '/upload/image/{yyyy}{mm}{dd}/{time}{rand:6}',
                'videoPathFormat' => '/upload/video/{yyyy}{mm}{dd}/{time}{rand:6}',
                'filePathFormat' => '/upload/file/{yyyy}{mm}{dd}/{rand:4}_{filename}',
                'imageManagerListPath' => '/upload/image/',
                'fileManagerListPath' => '/upload/file/',
            ]
        ]
    ],
```

后端配置参考官网[后端配置说明](http://fex-team.github.io/ueditor/#server-config "后端配置")。

扩展默认生成 `200x200` 的缩略图，缩略图大小可以通过 `thumbnail` 来控制。

```
    'thumbnail' => ['height' => 200, 'width' => 200]
```

如果将 `thumbnail` 设置为 `false`，就不会生成缩略图。

更多配置可以查看 `UEditorController`的 public 属性。


### 在模板中使用

1) 使用 ActiveRecord

就像使用普通的扩展一样。

```
<?= $form->field($model, 'content')->widget(\crazydb\ueditor\UEditor::className()) ?>
```
或者

```
<?= \crazydb\ueditor\UEditor::widget([
    'model' => $model,
    'attribute' => 'content',
]) ?>
```

还可以配置更多属性：

```
<?= \crazydb\ueditor\UEditor::widget([
    'model' => $model,
    'attribute' => 'content',
    'config' => [
        //client config @see http://fex-team.github.io/ueditor/#start-config
        'serverUrl' => ['/ueditor/index'],//确保serverUrl正确指向后端地址
        'lang' => 'zh-cn',
        'iframeCssUrl' => Yii::getAlias('@web') . '/static/css/ueditor.css',// 自定义编辑器内显示效果
    ]
]) ?>
```

需要注意的是 `serverUrl` 属性，默认后端地址是 `/ueditor/index`，如果配置了多个后端，则需要分别指定。

2) 当作普通表单使用


```
<?= \crazydb\ueditor\UEditor::widget([
    'name' => $name,
    'value' => $value,
]) ?>
```

### 第三方组件授权

扩展自带以下第三方组件（位于 `assets/third-party/`）。这些文件会随 AssetBundle 一起发布给使用方，因此在此声明其授权与来源：

| 组件 | 版本 | 授权 | 用途 |
|---|---|---|---|
| jQuery | 3.7.1 | MIT | 对话框 iframe 与前台内容页渲染所需 |
| ECharts | 5.6.0 | Apache-2.0 | 图表对话框与前台图表渲染；`LICENSE` 与 `NOTICE` 随包分发 |
| CodeMirror | 2.2 | MIT | 代码对话框 |
| SyntaxHighlighter | 1.5.1（XRegExp 1.5.1） | MIT | 前台代码高亮 |
| video-js | 4.3.0 | Apache-2.0 | 前台视频解析 |
| WebUploader | 0.1.2 | MIT | 上传组件 |

上表版本与授权均取自各文件头部的版权声明。

图表引擎自 1.7 起由 Highcharts 3.0.6 替换为 ECharts 5.6.0，以消除 Highcharts 的商业授权风险。
替换对已发布内容零迁移：图表仍以 `<table data-chart="…">` 形式存在，仅渲染层更改。

**允许的视觉差异**（更换图表引擎导致，属预期）：
- 饼图标签样式与高亮行为略有不同；
- 数值轴的 0 基线参考线不再绘制（Highcharts 的 `plotLines` 在 ECharts 中无对应项）；
- 不再显示 Highcharts 的右下角 Credits 链接，也不再提供导出按钮（原集成中二者本就被禁用）。

### 目标浏览器

不包含 IE8 及以下版本。

图表引擎 ECharts 5.x 自该版本起放弃 IE8 支持并移除了 VML 渲染器。
代码中仍保留针对旧版 IE 的兼容分支（继承自 UEditor 1.4.3），但这些分支不在支持范围内。

### 其他

编辑器内默认情况下行高为1，大段中文编辑显示效果非常差，但是可以通过设置 `iframeCssUrl` 来修改编辑器内显示效果。

默认不支持内网 IP 图片远程采集，如果部署在内网且需要这个功能,请配置`UEditorController::allowIntranet`为`true`。


相关链接
-----
@see https://github.com/fex-team/ueditor

