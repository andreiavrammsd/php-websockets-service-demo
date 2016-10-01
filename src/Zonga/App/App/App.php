<?php

namespace Zonga\App\App;

use Phalcon\Cli\Dispatcher;
use Phalcon\Di as CliDi;
use Phalcon\Loader;
use Ratchet\ConnectionInterface;
use Zonga\App\Request\RequestInterface;
use Zonga\App\Response\ResponseInterface;
use Zonga\App\Router\RouterInterface;
use Zonga\App\Session\SessionInterface;
use Zonga\App\Storage\StorageInterface;

class App implements AppInterface
{
    /**
     * @var CliDI
     */
    private $di;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct()
    {
        $this->di = new CliDI();

        $root = dirname(dirname(__DIR__));

        $loader = new Loader();
        $loader
            ->registerDirs(
                [
                    $root . '/Tasks',
                ]
            )
            ->register();

        $config = include $root . '/config/config.php';
        $this->di->set('config', new \Phalcon\Config($config));

        foreach ($config['services'] as $name => $definition) {
            $this->di->setShared($name, $definition);
        }

        $this->dispatcher = new Dispatcher();
        $this->dispatcher->setDI($this->di);
        $this->storage = $this->di->get('storage');
        $this->request = $this->di->get('request');
        $this->router = $this->di->get('router');
        $this->response = $this->di->get('response');
        $this->session = $this->di->get('session');
    }

    /**
     * @param string $sessionId
     */
    public function setSession(string $sessionId)
    {
        $this->session->setSession($sessionId);
    }

    /**
     * @param int $resourceId
     */
    public function addClient(int $resourceId)
    {
        $username = $this->session->getUsername();
        $sessionId = $this->session->getSessionId();
        $key = $this->getClientStorageKey($username, $sessionId);
        $client = [
            'host' => gethostname(),
            'connection' => $resourceId,
            'session' => $sessionId,
            'username' => $username,
            'time' => time(),
        ];
        $this->storage->set($key, $client);
    }

    /**
     * @param int $resourceId
     */
    public function removeClient(int $resourceId)
    {
        if ($this->session->isValid()) {
            $username = $this->session->getUsername();
            $sessionId = $this->session->getSessionId();
            $key = $this->getClientStorageKey($username, $sessionId);
            $this->storage->delete($key);
        }
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function setConnection(ConnectionInterface $connection)
    {
        $this->di->set('connection', $connection);
    }

    /**
     * @param string $message
     */
    public function handle(string $message)
    {
        try {
            $this->request->setRequest($message);
            $this->request->validateRequest();

            $event = $this->request->getEvent();
            $payload = $this->request->getPayload();

            $this->router->setEvent($event);
            $task = $this->router->getTask();
            $action = $this->router->getAction();

            $this->dispatcher->setTaskName($task);
            $this->dispatcher->setActionName($action);
            $this->dispatcher->setParams($payload);
            $this->dispatcher->dispatch();

            $response = $this->dispatcher->getReturnedValue();
            $error = null;
        } catch (AppException $e) {
            $response = [
                'message' => $e->getMessage(),
            ];
            $error = (new \ReflectionClass($e))->getShortName();
        } catch (\Exception $e) {
            $response = [
                'message' => 'Internal Server Error',
            ];
            $error = 'InternalServerError';
        }
        
        $this->response->setPayload($response);
        $this->response->setError($error);
    }

    /**
     * @return string
     */
    public function getResponse(): string
    {
        return $this->response->getContent();
    }

    /**
     * @param string $username
     * @param string $session
     * @return string
     */
    private function getClientStorageKey(string $username, string $session) : string
    {
        return sprintf('socket_clients:%s:%s', $username, $session);
    }
}
