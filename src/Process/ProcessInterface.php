<?php

namespace FastServer\Process;

interface ProcessInterface
{
    /**
     * 发送信号给进程
     *
     * @param int $signal
     */
    public function signal($signal);

    /**
     * 获取输入流
     *
     * @return resource
     */
    public function getInput();

    /**
     * 获取输出流
     *
     * @return resource
     */
    public function getOutput();

    /**
     * 启动进程
     *
     * @param bool $blocking
     */
    public function start($blocking = true);

    /**
     * 关闭进程
     */
    public function kill();

    /**
     * 强制关闭进程
     */
    public function stop();

    /**
     * 进程执行内容
     */
    public function run();
}