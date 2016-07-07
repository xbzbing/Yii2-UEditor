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


安装
------------

推荐使用 [composer](http://getcomposer.org/download/) 来安装扩展.

使用如下命令安装：

```
php composer.phar require --prefer-dist crazydb/yii2-ueditor "*"
```

或者将 `"crazydb/yii2-ueditor": "*"` 添加到项目的 `composer.json` 文件.


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

### 其他

编辑器内默认情况下行高为1，大段中文编辑显示效果非常差，但是可以通过设置 `iframeCssUrl` 来修改编辑器内显示效果。

默认不支持内网 IP 图片远程采集，如果部署在内网且需要这个功能,请配置`UEditorController::allowIntranet`为`true`。


相关链接
-----
@see https://github.com/fex-team/ueditor
