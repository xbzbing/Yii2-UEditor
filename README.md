Yii2-UEditor-Extension
======================
Yii2的百度UEditor扩展

网速太烂-，-还未正式发布，请稍等。。。

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

1) With ActiveRecord


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

2) In Normal Form


```
<?\crazydb\ueditor\UEditor::widget([
    'name' => $name,
    'value' => $value,
])>
```

Links
-----
@see https://github.com/fex-team/ueditor
