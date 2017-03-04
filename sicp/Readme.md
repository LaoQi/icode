# SICP读书笔记

### MIT scheme
[https://www.gnu.org/software/mit-scheme/](https://www.gnu.org/software/mit-scheme/)
### 基本操作  
转抄自：[http://www.cnblogs.com/Henrya2/archive/2009/02/21/1395615.html](http://www.cnblogs.com/Henrya2/archive/2009/02/21/1395615.html)

```
程序
C-x C-z 挂起程序
C-c C-x 退出程序
C-c k   关闭buffer
C-l     重画屏幕
C-g     结束命令，或者假死中恢复，也可以按3次ESC

文件
C-x C-s 保存
C-x C-w 另存为
C-x C-f 打开文件
C-x C-r 只读方式打开
C-x C-v 读入另外一个文件代替当前buffer的文件
C-x s   保存所有
C-x i   将文件的内容插入
M-x revert-buffer    恢复到原始状态

跳转
前/后     单位
C-f/b    字
M-f/b    词
C-a/e    行内
M-a/e    句
M-</>    文档
C-p/n    行间
M-{/}    段落
C-x ]/[  页
C-x C-x  文件内，mark之间

M-g g  跳到指定行
M-x goto-char 跳到指定字符

编辑
M-u       后面单词变为大写
M-l       后面单词变为小写
M-c       后面单词的首字母变大写
M-/       补全
C-j       从当前位置分成两行,相当于RET + tab
M-(       插入()
C-q tab   插入tab
C-q C-m   插入^M
M-;       插入注释
C-o       回车

删除
M-d   后一词
C-d   后一字
M-del 前一词
M-k   到句尾
M-"   前面的所有空白
M-z   删到指定字母处
C-k   删除到行尾

文本换位
C-t        字符
M-t        单词
C-x C-t    行
M-x transpose-* 其他命令

撤销
C-/
C-x u
C-_
C-z

重做
C-g M-x undo
C-g C-/
C-g C-z
C-g C-_

粘贴
C-y
C-v

tab/空格转换
M-x tabify
M-x untabify

让选择的区块自动对齐
M-x indent-region

其他命令
C-u <数字> <命令> 重复命令n次
M-<数字>   <命令> 同上
M-!     运行shell命令
C-u M-! 执行一条外部命令，并输出到光标位置
M-x cd  改变工作目录
M-x pwd 当前工作目录

C-" 启动输入法
M-` 菜单
F10 菜单
M-x eval-buffer 在.emacs的buffer中运行，重新加载emacs配置

查找替换
----------------------------------------------------------------------
C-r 向上查找
C-s 向下查找
C-s C-w 向下查找，光标位置的单词作为查找字符串
C-s C-y 向下查找，光标位置到行尾作为查找字符串
C-s RET <查找字符串> RET   非递增查找
C-s RET C-w              不受换行、空格、标点影响
C-M-s                    正则式向下查找
用向上查找命令就将上面命令的s替换为r

M-%   替换
C-M-% 正则式替换
 y 替换当前的字符串并移动到下一个字符串
 n 不替换当前字符串，直接移动到下一个字符串
 ! 进行全局替换，并要求不再显示
 . 替换当前字符串，然后退出查找替换操作
 q 退出查找替换操作，光标定位到操作开始时的位置

其他命令
M-x replace-*
M-x search-*

窗口
C-x 0 关掉当前窗口
C-x 1 关掉其他窗口
C-x o 切换窗口
C-x 2 水平两分窗口
C-x 3 垂直两分窗口
C-x 5 2 新frame

buffer
C-x C-b        查看
C-x b          切换
C-x C-q        设为只读
C-x k          删除
C-x left/right 切换

翻页
C-v 下一页
M-v 上一页

选择
M-h     选择段落
C-x h   全部选择

普通区块
C-SPC   M-x set-mark-command 单个位置set mark
C-@     同上
M-@     对word进行set Mark
M-w     先set Mark，移到光标，M-w就可以复制
C-w     剪切

矩形区块
用这些快捷键要先关闭cua-mode
C-x r t      用串填充矩形区域
C-x r o      插入空白的矩形区域
C-x r y      插入之前删除的矩形区域, 粘贴时，矩形左上角对齐光标
C-x r k      删除矩形区域
C-x r c      将当前矩形区域清空

寄存器
----------------------------------------------------------------------
光标位置和窗口状态
C-x r SPC <寄存器名>                   存贮光标位置
C-x r w <寄存器名>                     保存当前窗口状态
C-x r f <寄存器名>                     保存所有窗口状态
C-x r j <寄存器名>                     光标跳转

文本和数字
C-x r s <寄存器名>                     将连续区块拷贝到寄存器中
C-x r r <寄存器名>                     将矩形区块拷贝到寄存器中
C-u <数字> C-x r n <寄存器名>           将数字拷贝到寄存器中
C-x r i <寄存器名>                     在缓冲区中插入寄存器内容
M-x view-register                     查看寄存器内容
M-x list-registers                    查看寄存器列表

宏模式
C-x (                    开始一个宏的定义
C-x )                    结束一个宏的定义
C-x e                    执行宏
M-x name-last-kbd-macro  给最后一个宏命名
M-x insert-kbd-macro     在当前文件中插入一个已定义并命名过的宏

书签
C-x r m <name>           设置书签
C-x r b <name>           跳转到书签
C-x r l                  书签列表
M-x bookmark-delete      删除书签
M-x bookmark-load        读取存储书签文件
M-x bookmark-save        保存到文件

目录模式
----------------------------------------------------------------------
C-x d     M-x dired     启动目录模式
C-x C-d   简单目录
h         帮助
?         简单帮助
请参考http://www.emacs.cn/Doc/Dired

帮助
C-h k    显示你将按下的键执行的function.
C-h f    列出function的功能说明。
C-h b    列出目前所有的快捷键。
C-h m   列出目前的mode的特殊说明.
C-c C-h 列出以C-c 开头的所有快捷键. 

```
