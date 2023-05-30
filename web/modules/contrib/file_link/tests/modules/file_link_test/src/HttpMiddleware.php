<?php

namespace Drupal\file_link_test;

use Drupal\Core\Site\Settings;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A middleware for guzzle to test requests.
 */
class HttpMiddleware {

  /**
   * Recoding storage.
   *
   * @var array
   */
  public static $recorder = [];

  /**
   * Records requests.
   *
   * @param string $key
   *   An identifier key.
   *
   * @return int
   *   The recorder value.
   */
  public static function getRequestCount(string $key): int {
    if (!isset(static::$recorder[$key])) {
      static::$recorder[$key] = 0;
    }
    return static::$recorder[$key];
  }

  /**
   * Invoked method that returns a promise.
   */
  public function __invoke() {
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        $uri = $request->getUri();
        $settings = Settings::get('file_link_test_middleware', []);
        // Check if the request is made to one of our fixtures.
        $key = $uri->getScheme() . '://' . $uri->getHost() . $uri->getPath();

        if (array_key_exists($key, $settings)) {
          if (!isset(static::$recorder[$key])) {
            static::$recorder[$key] = 0;
          }
          static::$recorder[$key]++;

          return $this->createPromise($request, $settings[$key]);
        }

        // Otherwise, no intervention. We defer to the handler stack.
        return $handler($request, $options);
      };
    };
  }

  /**
   * Creates a promise for the file_link fixture request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The HTTP request.
   * @param array $fixture
   *   The fixture.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   A promise instance.
   */
  protected function createPromise(RequestInterface $request, array $fixture): PromiseInterface {
    // Create a response from the fixture.
    $response = new Response($fixture['status'] ?? 200, $fixture['headers'] ?? [], $fixture['body'] ?? NULL);
    return new FulfilledPromise($response);
  }

}
