<?php

/*
 * This file is part of the FOSHttpCache package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\HttpCache\Tests\Unit\Test\Proxy;

use FOS\HttpCache\Test\Proxy\VarnishProxy;
use PHPUnit\Framework\TestCase;

class VarnishProxyTest extends TestCase
{
    public function testInvalidConfigFileThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot find config file: nope.vcl');

        new VarnishProxy('nope.vcl');
    }

    public function allowInlineFlagProvider()
    {
        return [[true], [false]];
    }

    /**
     * @dataProvider allowInlineFlagProvider
     */
    public function testStart($inlineC)
    {
        $proxy = new VarnishProxyMock('config.vcl');
        $proxy->setBinary('/usr/sbin/varnishd');
        $proxy->setConfigDir('/my/varnish/dir');
        $proxy->setIp('192.168.0.1');
        $proxy->setManagementPort(1331);
        $proxy->setCacheDir('/tmp/cache/dir');
        $proxy->setAllowInlineC($inlineC);
        $this->assertEquals($inlineC, $proxy->getAllowInlineC());
        $proxy->start();

        $this->assertEquals('/usr/sbin/varnishd', $proxy->command);
        $vclPath = ((int) getenv('VARNISH_VERSION')) >= 5 ? 'vcl_path' : 'vcl_dir';
        $arguments = [
            '-a', '192.168.0.1:6181',
            '-T', '192.168.0.1:1331',
            '-f', 'config.vcl',
            '-n', '/tmp/cache/dir',
            '-p', $vclPath.'=/my/varnish/dir',
            '-P', '/tmp/foshttpcache-varnish.pid',
        ];
        if ($inlineC) {
            $arguments[] = '-p';
            $arguments[] = 'vcc_allow_inline_c=on';
        }

        $this->assertEquals($arguments, $proxy->arguments);
    }

    public function testWaitThrowsException()
    {
        $proxy = new VarnishProxyMock('config.vcl');
        $proxy->wait = false;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Caching proxy cannot be reached at 127.0.0.1:6181');
        $proxy->start();
    }
}

class VarnishProxyMock extends VarnishProxy
{
    public $command;

    public $arguments;

    public $wait = true;

    public function setConfigFile($configFile)
    {
        $this->configFile = $configFile;
    }

    protected function runCommand($command, array $arguments)
    {
        $this->command = $command;
        $this->arguments = $arguments;
    }

    protected function wait($timeout, $callback)
    {
        return $this->wait;
    }
}
