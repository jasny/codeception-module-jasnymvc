<?php

namespace Jasny\Codeception;

use Jasny\Router;
use Codeception\Configuration;
use Codeception\Lib\Framework;
use Codeception\TestInterface;
use Jasny\Codeception\Connector;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Interop\Container\ContainerInterface;

/**
 * Module for running functional tests using Jasny MVC
 */
class Module extends Framework
{
    /**
     * Required configuration fields
     * @var array
     */
    protected $requiredFields = ['container'];
    
    /**
     * @var Router
     */
    public $router;
    
    /**
     * @var ServerRequestInterface 
     */
    public $baseRequest;
    
    /**
     * @var ResponseInterface 
     */
    public $baseResponse;
    
    
    /**
     * Load the container by including the file.
     * @codeCoverageIgnore
     * 
     * @param string $file
     * @return ContainerInterface
     */
    protected function loadContainer($file)
    {
        return include $file;
    }
    
    /**
     * Get the container.
     * 
     * @return ContainerInterface
     */
    protected function initContainer()
    {
        $container = $this->loadContainer(Configuration::projectDir() . $this->config['container']);

        if (!$container instanceof ContainerInterface) {
            throw new \UnexpectedValueException("Failed to get a container from '{$this->config['container']}'");
        }

        return $container;
    }
    
    /**
     * Check if the response writes to the output buffer
     * 
     * @return boolean
     */
    protected function usesOutputBuffer()
    {
        return isset($this->baseResponse) && $this->baseResponse->getBody()->getMetadata('uri') === 'php://output';
    }
    
    /**
     * Enable output buffering
     * 
     * @throws \RuntimeException
     */
    protected function startOutputBuffering()
    {
        if ($this->obGetLevel() === 0) {
            $this->obStart();
        }

        if ($this->obGetLevel() < 1) {
            throw new \RuntimeException("Failed to start output buffering");
        }
    }
    
    /**
     * Disable output buffering
     */
    protected function stopOutputBuffering()
    {
        $this->obClean();
    }

    
    /**
     * Initialize the module
     */
    public function _initialize()
    {
        $container = $this->initContainer();
        
        $this->router = $container->get(Router::class);

        if ($container->has(ServerRequestInterface::class)) {
            $this->baseRequest = $container->get(ServerRequestInterface::class);
        }
        
        if ($container->has(ResponseInterface::class)) {
            $this->baseResponse = $container->get(ResponseInterface::class);
        }
    }
    
    /**
     * Call before suite
     * 
     * @param array $settings
     */
    public function _beforeSuite($settings = [])
    {
        parent::_beforeSuite($settings);
        
        if ($this->usesOutputBuffer()) {
            $this->startOutputBuffering();
        }
    }
    
    /**
     * Call after suite
     */
    public function _afterSuite()
    {
        if ($this->usesOutputBuffer()) {
            $this->stopOutputBuffering();
        }
        
        parent::_afterSuite();
    }
    
    /**
     * Before each test
     * 
     * @param TestInterface $test
     */
    public function _before(TestInterface $test)
    {
        $this->client = new Connector();
        $this->client->setRouter($this->router);

        if (isset($this->baseRequest)) {
            $this->client->setBaseRequest($this->baseRequest);
        }
        
        if (isset($this->baseResponse)) {
            $this->client->setBaseResponse($this->baseResponse);
        }
        
        parent::_before($test);
    }
    
    /**
     * After each test
     * 
     * @param TestInterface $test
     */
    public function _after(TestInterface $test)
    {
        if ($this->sessionStatus() === PHP_SESSION_ACTIVE) {
            $this->sessionAbort();
        }

        if (isset($this->client)) {
            $this->client->reset();
            
            if (isset($this->baseRequest)) {
                $this->baseRequest = $this->client->getBaseRequest();
            }
            
            if (isset($this->baseResponse)) {
                $this->baseResponse = $this->client->getBaseResponse();
            }
        }


        parent::_after($test);
    }
    
    
    /**
     * Wrapper around `ob_start()`
     * @codeCoverageIgnore
     */
    protected function obStart()
    {
        ob_start();
    }
    
    /**
     * Wrapper around `ob_get_level()`
     * @codeCoverageIgnore
     * 
     * @return int
     */
    protected function obGetLevel()
    {
        return ob_get_level();
    }
    
    /**
     * Wrapper around `ob_clean()`
     * @codeCoverageIgnore
     */
    protected function obClean()
    {
        ob_clean();
    }
    
    /**
     * Wrapper around `session_status()`
     * @codeCoverageIgnore
     * 
     * @return int
     */
    protected function sessionStatus()
    {
        return session_status();
    }
    
    /**
     * Wrapper around `session_abort()`
     * @codeCoverageIgnore
     */
    protected function sessionAbort()
    {
        return session_abort();
    }
}
