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


1. Config controllerMap as the follow.


```
    'controllerMap' => [
        'ueditor' => [
            'class' => 'crazydb\ueditor\UEditorController',
            'zoom' => ['height' => 500, 'width' => 500]
        ]
    ],
```


2. In the view:

1) For ActiveRecord


```
<?=\crazydb\ueditor\UEditor::widget([
    'model' => $model,
    'attribute' => 'content',
])?>
```

or

```
<?=$form->field($model, 'content')->widget(UEditor::className())?>
```

2) For normal form


```
<?\crazydb\ueditor\UEditor::widget([
    'name' => $name,
    'value' => $value,
])>
```

Links
-----
@see https://github.com/fex-team/ueditor
