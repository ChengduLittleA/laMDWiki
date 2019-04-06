# 多媒体文件使用

这是LaMDWiki的多媒体演示页面。

LaMDWiki支持所有的HTML5嵌入媒体格式，另外下面的多媒体模式在显示上有所优化。

- 图像
- HTML5音频
- 三维画布

## 图像的使用

### ![](mdwiki.png)

LaMDWiki的图像布局比原来的MDWiki方便。

```
![](img.jpg)     直接写，图像居中放置
###![](img.jpg)  写为3级标题的形式，则悬挂在右边。
####![](img.jpg) 写成4级标题的形式，则悬挂在左边。

```

## 音频

LaMDWiki支持在文本中插入多个音频，音频标签会被提取出来，屏幕的底部此时会出现一个播放器，点击即可播放。目前的快速插入写法只支持ogg格式的音频文件。

### 添加音频

!@@[Cuban Baion](cuban_baion.ogg)

像下面这样写就能将任何本地音频添加到文章中。

```
!@@[Name](Path/To/Audio/File.ogg)
```

## 三维画布

LaMDWiki目前使用的是（还不算那么轻量的）完整版ThreeJs。

### 如何使用

仍然使用LaMarkdownConf格式，像这样在你希望插入三维内容的文件中加入3D配置块。

```
<!-- 3D -->

- File       = Path/To/File.glb  (目前只支持GLTF格式)
- Mode       = Block             (可选参数，Block/Inline/Background，Inline在没有Hook的情况无效)
- Hook       = SomeTitle         (钉到某个标题的下方，比如这个猴子就钉到了“三维画布”上)
- Padding    = 0                 (Block 和 Inline 时是否显示画布边距。默认开启。 0/1)
- Hang       = 1                 (悬挂在正文右边，比如这个猴子。仅对Inline模式有效。 0/1)
- LockCenter = 1                 (不允许画布平移。默认关闭)
- Expand     = 0                 (在Block模式下将画布扩展成整个浏览器宽度。默认关闭。 0/1)

<!-- end of 3D -->
```


<!-- 3D -->

Scene
- File=monkey.glb
- Mode=Block
- Hook=三维画布
- Padding=1
- Hang=1
- LockCenter=1

Scene
- File=kriss.glb
- Mode=Background
- Expand=1
- Padding=0
- Hang=1

<!-- end of 3D -->