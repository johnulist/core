<?php
/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Api;

use Flarum\Http\Controller\ControllerInterface;
use Flarum\Core\User;
use Illuminate\Contracts\Container\Container;
use Exception;
use InvalidArgumentException;
use Zend\Diactoros\ServerRequestFactory;

class Client
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Execute the given API action class, pass the input and return its response.
     *
     * @param string|ControllerInterface $controller
     * @param User $actor
     * @param array $queryParams
     * @param array $body
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function send($controller, User $actor, array $queryParams = [], array $body = [])
    {
        $request = ServerRequestFactory::fromGlobals(null, $queryParams, $body)->withAttribute('actor', $actor);

        if (is_string($controller)) {
            $controller = $this->container->make($controller);
        }

        if (! ($controller instanceof ControllerInterface)) {
            throw new InvalidArgumentException('Endpoint must be an instance of '
                . ControllerInterface::class);
        }

        try {
            $response = $controller->handle($request);
        } catch (Exception $e) {
            $response = $this->container->make('Flarum\Api\Middleware\HandleErrors')->handle($e);
        }

        return $response;
    }
}
