<?php
namespace xiaosongshu;
/**
 * 对外封装类，隐藏内部方法
 */
class Foreign
{
    private $f2m;

    private $_onInitSegment = null;
    private $_onMediaSegment = null;
    private $_onMediaInfo = null;
    private $_seekCallBack = null;

    /**
     * @param array $config 配置数组，例如 ['_isLive' => false]
     */
    public function __construct($config = [])
    {
        $this->f2m = new Flv2Fmp4($config);
    }

    /**
     * 跳转
     * @param int $basetime
     */
    public function seek($basetime)
    {
        $this->f2m->seek($basetime);
    }

    /**
     * 传入 FLV 二进制数据
     * @param string $arraybuff
     * @return int
     */
    public function setflv($arraybuff)
    {
        return $this->f2m->setflv($arraybuff, 0);
    }

    /**
     * 本地调试：返回解析后的 tag 数组
     * @param string $arraybuff
     * @return array
     */
    public function setflvloc($arraybuff)
    {
        return $this->f2m->setflvloc($arraybuff);
    }

    /**
     * 设置初始化片段回调
     */
    public function setOnInitSegment($fun)
    {
        $this->_onInitSegment = $fun;
        $this->f2m->onInitSegment = $fun;
    }

    /**
     * 设置媒体片段回调
     */
    public function setOnMediaSegment($fun)
    {
        $this->_onMediaSegment = $fun;
        $this->f2m->onMediaSegment = $fun;
    }

    /**
     * 设置元数据回调
     */
    public function setOnMediaInfo($fun)
    {
        $this->_onMediaInfo = $fun;
        $this->f2m->onMediaInfo = $fun;
    }

    /**
     * 设置 seek 完成回调
     */
    public function setSeekCallBack($fun)
    {
        $this->_seekCallBack = $fun;
        $this->f2m->seekCallBack = $fun;
    }

    // 魔术方法，支持属性风格的 setter（可选）
    public function __set($name, $value)
    {
        if ($name === 'onInitSegment') {
            $this->setOnInitSegment($value);
        } elseif ($name === 'onMediaSegment') {
            $this->setOnMediaSegment($value);
        } elseif ($name === 'onMediaInfo') {
            $this->setOnMediaInfo($value);
        } elseif ($name === 'seekCallBack') {
            $this->setSeekCallBack($value);
        }
    }
}