<?php

namespace ShiftOneLabs\LaravelSqsFifoQueue\Tests;

use Dotenv\Dotenv;
use ReflectionMethod;
use ReflectionProperty;
use Illuminate\Queue\Queue;
use PHPUnit_Framework_TestCase;
use Illuminate\Encryption\Encrypter;
use Illuminate\Queue\Capsule\Manager as Capsule;
use \ShiftOneLabs\LaravelSqsFifoQueue\LaravelSqsFifoQueueServiceProvider;

class TestCase extends PHPUnit_Framework_TestCase
{
    /**
     * The Illuminate Container used by the queue.
     *
     * @var \Illuminate\Container\Container
     */
    protected $app;

    /**
     * The Queue Capsule instance for the tests.
     *
     * @var \Illuminate\Queue\Capsule\Manager
     */
    protected $queue;

    /**
     * Initial setup for all tests.
     *
     * @return void
     */
    public function setUp()
    {
        $this->loadEnvironment();
        $this->setUpCapsule();
        $this->setUpQueueConnection();
        $this->registerServiceProvider();
    }

    /**
     * Load the environment variables from the .env file.
     *
     * @return void
     */
    public function loadEnvironment()
    {
        (new Dotenv(__DIR__.'/..'))->load();
    }

    /**
     * Setup the Queue Capsule.
     *
     * @return void
     */
    public function setUpCapsule()
    {
        $queue = new Capsule();
        $queue->setAsGlobal();

        $this->queue = $queue;
        $this->app = $queue->getContainer();

        $this->app->instance('queue', $queue->getQueueManager());

        $this->setUpEncrypter();
    }

    /**
     * Setup the Encrypter for Laravel 4.2 - 5.2.
     *
     * @return void
     */
    public function setUpEncrypter()
    {
        // Laravel >= 4.2 && <= 5.2 need an encrypter instance to create the connection.
        if (method_exists(Queue::class, 'setEncrypter')) {
            if (!defined('MCRYPT_RIJNDAEL_128')) {
                define('MCRYPT_RIJNDAEL_128', 'rijndael-128');
            }

            if (!defined('MCRYPT_MODE_CBC')) {
                define('MCRYPT_MODE_CBC', 'cbc');
            }

            $this->app->instance('encrypter', new Encrypter(str_random(16)));
        }
    }

    /**
     * Register the service provider for the package.
     *
     * @return void
     */
    public function registerServiceProvider()
    {
        $provider = new LaravelSqsFifoQueueServiceProvider($this->app);

        $provider->register();
    }

    /**
     * Setup the database connection.
     *
     * @return void
     */
    public function setUpQueueConnection()
    {
        $queue = $this->queue;

        $queue->addConnection([
            'driver' => 'sync',
        ]);

        $queue->addConnection([
            'driver' => 'sqs-fifo',
            'key' => getenv('SQS_KEY'),
            'secret' => getenv('SQS_SECRET'),
            'prefix' => getenv('SQS_PREFIX'),
            'queue' => getenv('SQS_QUEUE'),
            'region' => getenv('SQS_REGION'),
            'group' => 'default',
            'deduplicator' => 'unique',
        ], 'sqs-fifo');
    }

    /**
     * Use reflection to call a restricted (private/protected) method on an object.
     *
     * @param  object  $object
     * @param  string  $method
     * @param  array  $args
     *
     * @return mixed
     */
    protected function callRestrictedMethod($object, $method, array $args = [])
    {
        $reflectionMethod = new ReflectionMethod($object, $method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($object, $args);
    }

    /**
     * Use reflection to set the value of a restricted (private/protected)
     * property on an object.
     *
     * @param  object  $object
     * @param  string  $property
     * @param  mixed  $value
     *
     * @return void
     */
    protected function setRestrictedValue($object, $property, $value)
    {
        $reflectionProperty = new ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);

        if ($reflectionProperty->isStatic()) {
            $reflectionProperty->setValue($value);
        } else {
            $reflectionProperty->setValue($object, $value);
        }
    }
}