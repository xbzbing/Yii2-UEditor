Yii2-UEditor-Extension
======================
Yii2的百度UEditor扩展


由于bower上的包是纯源码，需要用grunt打包后才能使用，因此扩展自带了1.4.3版本的UEditor资源包。


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist crazydb/yii2-ueditor "*"
```

or add

```
"crazydb/yii2-ueditor": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :


###1. Config controllerMap as the follow.


```
    'controllerMap' => [
        'ueditor' => [
            'class' => 'crazydb\ueditor\UEditorController',
        ]
    ],
```

Detailed configuration:

```
    'controllerMap' => [
        'ueditor' => [
            'class' => 'crazydb\ueditor\UEditorController',
            'thumbnail' => null,//If 'thumbnail' is set to null or false, UEditorWidget will NOT create any thumbnails.
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

You can extend UEditorController to do more changes.

###2. In the view:

1) For ActiveRecord


```
<?= \crazydb\ueditor\UEditor::widget([
    'model' => $model,
    'attribute' => 'content',
]) ?>
```

Detailed configuration:

```
<?= \crazydb\ueditor\UEditor::widget([
    'model' => $model,
    'attribute' => 'content',
    'config' => [
        //client config @see http://fex-team.github.io/ueditor/#start-config
        'serverUrl' => ['/ueditor'],//Ensure that the 'serverUrl' can match the 'controllerMap' configuration.
        'lang' => 'zh-cn'
    ]
]) ?>
```

or

```
<?= $form->field($model, 'content')->widget(\crazydb\ueditor\UEditor::className()) ?>
```

2) For normal form


```
<?= \crazydb\ueditor\UEditor::widget([
    'name' => $name,
    'value' => $value,
]) ?>
```

Links
-----
@see https://github.com/fex-team/ueditor
